<?php

namespace App\Services;

use App\Models\JiraIntegration;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JiraAchievementService
{
    public function preview(JiraIntegration $integration, string $startDate, string $endDate): array
    {
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->addDay()->startOfDay();
        // Do not depend on Jira status names or resolution fields. Import every issue created or updated in the selected month.
        $period = '((created >= "'.$start->toDateString().'" AND created < "'.$end->toDateString().'") OR (updated >= "'.$start->toDateString().'" AND updated < "'.$end->toDateString().'"))';
        $jql = trim($integration->jql ?? '') ? '('.$integration->jql.') AND '.$period : $period;
        $issues = $this->fetchAllIssues($integration, $jql);
        Log::info('jira achievement source', ['start_date' => $start->toDateString(), 'end_date' => $end->subDay()->toDateString(), 'jql' => $jql, 'issue_count' => count($issues)]);
        if (!$issues) return ['issues' => [], 'achievements' => []];
        $achievements = collect($this->analyze($issues, $start->toDateString(), $end->subDay()->toDateString()))->map(fn (array $draft) => ['source_key' => implode(', ', array_column($issues, 'key')), 'type' => 'workplace', 'title' => $draft['title'], 'description' => $draft['description'], 'achieved_on' => $end->subDay()->toDateString(), 'organization' => 'Jira', 'reference_url' => rtrim($integration->base_url, '/').'/issues/?jql='.rawurlencode($jql)])->values()->all();
        return ['issues' => $issues, 'achievements' => $achievements];
    }

    private function fetchAllIssues(JiraIntegration $integration, string $jql): array
    {
        $url = rtrim($integration->base_url, '/').'/rest/api/3/search/jql';
        $token = null; $all = [];
        for ($page = 0; $page < 20; $page++) {
            $body = ['jql' => $jql, 'maxResults' => 100, 'expand' => ['names'], 'fields' => ['summary', 'created', 'updated', 'duedate', 'project', 'issuetype', 'status', 'parent', 'timetracking', 'timeoriginalestimate', 'timespent', '*navigable']];
            if ($token) $body['nextPageToken'] = $token;
            $response = Http::acceptJson()->withBasicAuth($integration->email, $integration->api_token)->timeout(30)->post($url, $body);
            if (!$response->successful()) throw new \RuntimeException('Jira request failed: '.($response->json('errorMessages.0') ?: $response->body() ?: $response->status()));
            $names = $response->json('names', []);
            foreach ($response->json('issues', []) as $issue) {
                $fields = $issue['fields'] ?? [];
                $all[] = [
                    'key' => $issue['key'] ?? '',
                    'summary' => $fields['summary'] ?? '',
                    'status' => data_get($fields, 'status.name'),
                    'type' => data_get($fields, 'issuetype.name', 'Task'),
                    'taskCategory' => $this->customFieldValue($fields, $names, ['Task Category', 'Task category']),
                    'epicKey' => data_get($fields, 'parent.key') ?? $this->customFieldValue($fields, $names, ['Epic Link', 'Epic']),
                    'projectKey' => data_get($fields, 'project.key'),
                    'projectName' => data_get($fields, 'project.name', 'Jira'),
                    'created' => $fields['created'] ?? null,
                    'updated' => $fields['updated'] ?? null,
                    'duedate' => $fields['duedate'] ?? null,
                    'originalEstimateSeconds' => $fields['timeoriginalestimate'] ?? data_get($fields, 'timetracking.originalEstimateSeconds', 0),
                    'timeSpentSeconds' => $fields['timespent'] ?? data_get($fields, 'timetracking.timeSpentSeconds', 0),
                ];
            }
            $token = $response->json('nextPageToken');
            if (!$token || $response->json('isLast', false)) break;
        }
        return collect($all)->filter(fn ($item) => $item['key'] && $item['summary'])->unique('key')->values()->all();
    }

    private function customFieldValue(array $fields, array $names, array $wantedNames): mixed
    {
        foreach ($names as $fieldId => $name) {
            if (in_array($name, $wantedNames, true) && array_key_exists($fieldId, $fields)) {
                $value = $fields[$fieldId];
                return is_array($value) ? ($value['value'] ?? $value['name'] ?? $value['key'] ?? json_encode($value)) : $value;
            }
        }
        return null;
    }

    private function analyze(array $issues, string $startDate, string $endDate): array
    {
        $rangeLabel = $startDate === $endDate ? $startDate : $startDate.' to '.$endDate;
        $fallback = [['title' => 'Jira work summary — '.$rangeLabel, 'description' => count($issues).' Jira work items were created or updated from '.$rangeLabel.":\n".collect($issues)->map(fn ($item) => '• '.$item['key'].' — '.$item['summary'])->implode("\n")]];
        $key = trim((string) config('services.financial_overview_ai.api_key'));
        if ($key === '') return $fallback;
        try {
            $response = Http::withToken($key)->acceptJson()->timeout(45)->post('https://api.openai.com/v1/responses', ['model' => config('services.financial_overview_ai.model', 'gpt-4o-mini'), 'instructions' => 'Using only the supplied Jira work items, create 1 to 5 concise factual workplace achievement drafts. Group related tasks. Do not claim work was completed unless the supplied text proves it. Do not invent impact, metrics, people, or work. Return JSON only: {"achievements":[{"title":"...","description":"summary sentence\\n• grouped work"}]}.', 'input' => json_encode(['start_date' => $startDate, 'end_date' => $endDate, 'jira_work_items_created_or_updated_in_range' => $issues], JSON_THROW_ON_ERROR), 'text' => ['format' => ['type' => 'json_schema', 'name' => 'achievement_drafts', 'strict' => true, 'schema' => ['type' => 'object', 'properties' => ['achievements' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 5, 'items' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'description' => ['type' => 'string']], 'required' => ['title', 'description'], 'additionalProperties' => false]]], 'required' => ['achievements'], 'additionalProperties' => false]]], 'max_output_tokens' => 1000]);
            if (!$response->successful()) throw new \RuntimeException('OpenAI HTTP '.$response->status());
            $parsed = json_decode((string) ($response->json('output_text') ?? data_get($response->json(), 'output.0.content.0.text')), true, flags: JSON_THROW_ON_ERROR);
            $drafts = collect($parsed['achievements'] ?? [])->filter(fn ($item) => is_string($item['title'] ?? null) && is_string($item['description'] ?? null))->map(fn ($item) => ['title' => trim($item['title']), 'description' => trim($item['description'])])->filter(fn ($item) => $item['title'] && $item['description'])->values()->all();
            if (!$drafts) throw new \RuntimeException('OpenAI returned no achievement drafts.');
            Log::info('chatgpt response', ['status' => 'success', 'issue_count' => count($issues), 'draft_count' => count($drafts), 'drafts' => $drafts]);
            return $drafts;
        } catch (\Throwable $exception) { Log::warning('chatgpt response', ['status' => 'failed', 'issue_count' => count($issues), 'error' => $exception->getMessage()]); return $fallback; }
    }
}
