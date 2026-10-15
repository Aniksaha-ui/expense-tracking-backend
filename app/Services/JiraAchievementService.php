<?php

namespace App\Services;

use App\Models\JiraIntegration;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JiraAchievementService
{
    public function preview(JiraIntegration $integration, int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $end = $start->addMonth();
        // Jira projects often mark work Done without a resolution date, so include Done work resolved OR updated in the selected month.
        $period = 'statusCategory = Done AND ((resolved >= "'.$start->toDateString().'" AND resolved < "'.$end->toDateString().'") OR (updated >= "'.$start->toDateString().'" AND updated < "'.$end->toDateString().'"))';
        $jql = trim($integration->jql ?? '') ? '('.$integration->jql.') AND '.$period : $period;
        $issues = $this->fetchAllIssues($integration, $jql);
        Log::info('jira achievement source', ['year' => $year, 'month' => $month, 'jql' => $jql, 'issue_count' => count($issues)]);
        if (!$issues) return [];
        return collect($this->analyze($issues, $year, $month))->map(fn (array $draft) => ['source_key' => implode(', ', array_column($issues, 'key')), 'type' => 'workplace', 'title' => $draft['title'], 'description' => $draft['description'], 'achieved_on' => $end->subDay()->toDateString(), 'organization' => 'Jira', 'reference_url' => rtrim($integration->base_url, '/').'/issues/?jql='.rawurlencode($jql)])->values()->all();
    }

    private function fetchAllIssues(JiraIntegration $integration, string $jql): array
    {
        $url = rtrim($integration->base_url, '/').'/rest/api/3/search/jql';
        $token = null; $all = [];
        for ($page = 0; $page < 20; $page++) {
            $body = ['jql' => $jql, 'maxResults' => 100, 'fields' => ['summary', 'resolutiondate', 'updated', 'project', 'issuetype']];
            if ($token) $body['nextPageToken'] = $token;
            $response = Http::acceptJson()->withBasicAuth($integration->email, $integration->api_token)->timeout(30)->post($url, $body);
            if (!$response->successful()) throw new \RuntimeException('Jira request failed: '.($response->json('errorMessages.0') ?: $response->body() ?: $response->status()));
            foreach ($response->json('issues', []) as $issue) $all[] = ['key' => $issue['key'] ?? '', 'summary' => data_get($issue, 'fields.summary', ''), 'project' => data_get($issue, 'fields.project.name', 'Jira'), 'type' => data_get($issue, 'fields.issuetype.name', 'Task')];
            $token = $response->json('nextPageToken');
            if (!$token || $response->json('isLast', false)) break;
        }
        return collect($all)->filter(fn ($item) => $item['key'] && $item['summary'])->unique('key')->values()->all();
    }

    private function analyze(array $issues, int $year, int $month): array
    {
        $monthName = CarbonImmutable::create($year, $month, 1)->format('F Y');
        $fallback = [['title' => 'Jira delivery — '.$monthName, 'description' => 'Completed '.count($issues).' Jira work items in '.$monthName.":\n".collect($issues)->map(fn ($item) => '• '.$item['key'].' — '.$item['summary'])->implode("\n")]];
        $key = trim((string) config('services.financial_overview_ai.api_key'));
        if ($key === '') return $fallback;
        try {
            $response = Http::withToken($key)->acceptJson()->timeout(45)->post('https://api.openai.com/v1/responses', ['model' => config('services.financial_overview_ai.model', 'gpt-4o-mini'), 'instructions' => 'Using only the supplied completed Jira work, create 1 to 5 concise factual workplace achievement drafts. Group related tasks. Do not invent impact, metrics, people, or work. Return JSON only: {"achievements":[{"title":"...","description":"summary sentence\\n• grouped work"}]}.', 'input' => json_encode(['month' => sprintf('%04d-%02d', $year, $month), 'completed_jira_tasks_and_subtasks' => $issues], JSON_THROW_ON_ERROR), 'text' => ['format' => ['type' => 'json_schema', 'name' => 'achievement_drafts', 'strict' => true, 'schema' => ['type' => 'object', 'properties' => ['achievements' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 5, 'items' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'description' => ['type' => 'string']], 'required' => ['title', 'description'], 'additionalProperties' => false]]], 'required' => ['achievements'], 'additionalProperties' => false]]], 'max_output_tokens' => 1000]);
            if (!$response->successful()) throw new \RuntimeException('OpenAI HTTP '.$response->status());
            $parsed = json_decode((string) ($response->json('output_text') ?? data_get($response->json(), 'output.0.content.0.text')), true, flags: JSON_THROW_ON_ERROR);
            $drafts = collect($parsed['achievements'] ?? [])->filter(fn ($item) => is_string($item['title'] ?? null) && is_string($item['description'] ?? null))->map(fn ($item) => ['title' => trim($item['title']), 'description' => trim($item['description'])])->filter(fn ($item) => $item['title'] && $item['description'])->values()->all();
            if (!$drafts) throw new \RuntimeException('OpenAI returned no achievement drafts.');
            Log::info('chatgpt response', ['status' => 'success', 'issue_count' => count($issues), 'draft_count' => count($drafts), 'drafts' => $drafts]);
            return $drafts;
        } catch (\Throwable $exception) { Log::warning('chatgpt response', ['status' => 'failed', 'issue_count' => count($issues), 'error' => $exception->getMessage()]); return $fallback; }
    }
}
