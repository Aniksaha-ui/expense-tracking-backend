<?php

namespace App\Services;

use App\Models\JiraIntegration;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JiraAchievementService
{
    public function verify(JiraIntegration $integration): array
    {
        $connection = $this->resolveConnection($integration);
        $projects = $connection['client']->get($connection['api_base'].'/rest/api/3/project/search', ['maxResults' => 50]);
        if (!$projects->successful()) throw new \RuntimeException('Jira project access check failed: '.($projects->json('errorMessages.0') ?: $projects->status()));
        return ['account_id' => data_get($connection, 'me.accountId'), 'display_name' => data_get($connection, 'me.displayName'), 'email' => data_get($connection, 'me.emailAddress') ?? $integration->email, 'auth_method' => $connection['auth_method'], 'visible_project_count' => count($projects->json('values', [])), 'projects' => collect($projects->json('values', []))->take(10)->map(fn ($project) => ['key' => $project['key'] ?? '', 'name' => $project['name'] ?? ''])->values()];
    }

    public function preview(JiraIntegration $integration, string $startDate, string $endDate): array
    {
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->addDay()->startOfDay();
        // Restrict to the authenticated Jira account. Jira Cloud can hide account emails,
        // so currentUser() is safer and always maps to the email/token saved for this integration.
        $owner = '(assignee = currentUser())';
        // Achievements must only reflect delivered work. statusCategory is Jira's
        // portable completed-state classification, unlike site-specific status names.
        $completed = '(statusCategory = Done)';
        $period = '((created >= "' . $start->toDateString() . '" AND created < "' . $end->toDateString() . '") OR (updated >= "' . $start->toDateString() . '" AND updated < "' . $end->toDateString() . '"))';
        $jql = trim($integration->jql ?? '')
            ? '(' . $integration->jql . ') AND ' . $owner . ' AND ' . $completed . ' AND ' . $period
            : $owner . ' AND ' . $completed . ' AND ' . $period;
        $connection = $this->resolveConnection($integration);
        $issues = $this->fetchAllIssues($connection, $jql);
        Log::info('jira achievement source', ['start_date' => $start->toDateString(), 'end_date' => $end->subDay()->toDateString(), 'jql' => $jql, 'issue_count' => count($issues)]);
        if (!$issues) return ['issues' => [], 'achievements' => [], 'diagnostics' => ['jql' => $jql, 'start_date' => $start->toDateString(), 'end_date' => $end->subDay()->toDateString(), 'issue_count' => 0]];
        $achievements = collect($this->analyze($issues, $start->toDateString(), $end->subDay()->toDateString()))->map(function (array $draft) use ($issues, $integration, $jql, $end) {
            $sourceKeys = $draft['issue_keys'] ?? array_column($issues, 'key');
            return [
                'source_key' => implode(', ', $sourceKeys),
                'source_issue_keys' => $sourceKeys,
                'type' => 'workplace',
                'title' => $draft['title'],
                'description' => $draft['description'],
                'achieved_on' => $end->subDay()->toDateString(),
                'organization' => 'Jira',
                'reference_url' => rtrim($integration->base_url, '/') . '/issues/?jql=' . rawurlencode($jql),
            ];
        })->values()->all();
        return ['issues' => $issues, 'achievements' => $achievements, 'diagnostics' => ['jql' => $jql, 'start_date' => $start->toDateString(), 'end_date' => $end->subDay()->toDateString(), 'issue_count' => count($issues)]];
    }

    private function fetchAllIssues(array $connection, string $jql): array
    {
        $url = $connection['api_base'] . '/rest/api/3/search/jql';
        $token = null;
        $all = [];
        for ($page = 0; $page < 100; $page++) {
            $body = ['jql' => $jql, 'maxResults' => 100, 'expand' => 'names', 'fields' => ['summary', 'description', 'comment', 'labels', 'issuelinks', 'assignee', 'reporter', 'components', 'created', 'updated', 'duedate', 'project', 'issuetype', 'status', 'parent', 'timetracking', 'timeoriginalestimate', 'timespent']];
            if ($token) $body['nextPageToken'] = $token;
            $response = $connection['client']->post($url, $body);
            if (!$response->successful()) throw new \RuntimeException('Jira request failed: ' . ($response->json('errorMessages.0') ?: $response->body() ?: $response->status()));
            $names = $response->json('names', []);
            foreach ($response->json('issues', []) as $issue) {
                $fields = $issue['fields'] ?? [];
                $all[] = [
                    'key' => $issue['key'] ?? '',
                    'summary' => $fields['summary'] ?? '',
                    'description' => $this->adfToText($fields['description'] ?? null),
                    'comments' => collect(data_get($fields, 'comment.comments', []))
                        ->map(fn (array $comment) => [
                            'author' => data_get($comment, 'author.displayName'),
                            'created' => $comment['created'] ?? null,
                            'body' => $this->adfToText($comment['body'] ?? null),
                        ])
                        ->filter(fn (array $comment) => $comment['body'] !== '')
                        ->values()
                        ->all(),
                    'labels' => array_values($fields['labels'] ?? []),
                    'links' => $this->issueLinks($fields['issuelinks'] ?? []),
                    'assignee' => data_get($fields, 'assignee.displayName'),
                    'reporter' => data_get($fields, 'reporter.displayName'),
                    'components' => collect($fields['components'] ?? [])->pluck('name')->filter()->values()->all(),
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
        return collect($all)->filter(fn($item) => $item['key'] && $item['summary'])->unique('key')->values()->all();
    }

    /** Convert Jira's Atlassian Document Format to safe, analysis-ready plain text. */
    private function adfToText(mixed $value): string
    {
        if (is_string($value)) return trim($value);
        if (!is_array($value)) return '';

        $parts = [];
        $walk = function (mixed $node) use (&$walk, &$parts): void {
            if (!is_array($node)) return;
            if (($node['type'] ?? null) === 'text' && is_string($node['text'] ?? null)) $parts[] = $node['text'];
            foreach ($node['content'] ?? [] as $child) $walk($child);
            if (in_array($node['type'] ?? null, ['paragraph', 'heading', 'listItem', 'blockquote'], true)) $parts[] = "\n";
        };
        $walk($value);
        return trim(preg_replace('/[ \t]+\n/', "\n", preg_replace('/\n{3,}/', "\n\n", implode('', $parts))));
    }

    private function issueLinks(array $links): array
    {
        return collect($links)->map(function (array $link) {
            $issue = $link['outwardIssue'] ?? $link['inwardIssue'] ?? [];
            return [
                'relationship' => data_get($link, 'type.outward') ?? data_get($link, 'type.inward'),
                'key' => $issue['key'] ?? null,
                'summary' => data_get($issue, 'fields.summary'),
            ];
        })->filter(fn (array $link) => $link['key'])->values()->all();
    }

    /**
     * Supports both legacy API tokens (Basic auth on the Jira site URL) and
     * newer scoped API tokens (Bearer auth through api.atlassian.com).
     */
    private function resolveConnection(JiraIntegration $integration): array
    {
        $siteUrl = rtrim($integration->base_url, '/');
        $token = trim($integration->api_token);
        $basicClient = Http::acceptJson()->withBasicAuth(trim($integration->email), $token)->timeout(30);
        $me = $basicClient->get($siteUrl.'/rest/api/3/myself');

        if ($me->successful()) return ['client' => $basicClient, 'api_base' => $siteUrl, 'me' => $me->json(), 'auth_method' => 'basic'];
        if ($me->status() !== 401) throw new \RuntimeException('Jira authentication failed: '.($me->json('errorMessages.0') ?: $me->status()));

        $scopedClient = Http::acceptJson()->withToken($token)->timeout(30);
        $resources = $scopedClient->get('https://api.atlassian.com/oauth/token/accessible-resources');
        if (!$resources->successful()) {
            throw new \RuntimeException('Jira rejected the saved credentials with both classic and scoped authentication. Save a newly copied Atlassian API token created for the same Atlassian account email.');
        }

        $expectedHost = parse_url($siteUrl, PHP_URL_HOST);
        $resource = collect($resources->json())->first(fn (array $item) => parse_url($item['url'] ?? '', PHP_URL_HOST) === $expectedHost);
        if (!$resource || empty($resource['id'])) throw new \RuntimeException('The scoped API token does not have access to this Jira site. Create it for this Jira site with Jira read access.');

        $apiBase = 'https://api.atlassian.com/ex/jira/'.$resource['id'];
        $scopedMe = $scopedClient->get($apiBase.'/rest/api/3/myself');
        if (!$scopedMe->successful()) throw new \RuntimeException('Jira authentication failed: '.($scopedMe->json('errorMessages.0') ?: $scopedMe->json('error') ?: $scopedMe->status()));

        return ['client' => $scopedClient, 'api_base' => $apiBase, 'me' => $scopedMe->json(), 'auth_method' => 'scoped'];
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
        $fallback = collect($issues)
            ->groupBy(fn (array $issue) => ($issue['projectKey'] ?? 'jira').':'.($issue['epicKey'] ? 'epic:'.$issue['epicKey'] : 'issue:'.$issue['key']))
            ->map(function ($group) {
                $first = $group->first();
                return [
                    'title' => ($first['epicKey'] ? 'Epic '.$first['epicKey'] : ($first['projectName'] ?? 'Jira')).' - '.($first['projectName'] ?? 'Jira').' - '.$first['summary'],
                    'description' => $group->count() === 1
                        ? $first['summary']
                        : 'Related Jira work:' . "\n" . $group->map(fn ($issue) => '• '.$issue['key'].' — '.$issue['summary'])->implode("\n"),
                    'issue_keys' => $group->pluck('key')->all(),
                ];
            })->values()->all();
        $key = trim((string) config('services.financial_overview_ai.api_key'));
        if ($key === '') return $fallback;
        try {
            $response = Http::withToken($key)->acceptJson()->timeout(45)->post('https://api.openai.com/v1/responses', ['model' => config('services.financial_overview_ai.model', 'gpt-4o-mini'), 'instructions' => 'Create achievement drafts only from the supplied completed Jira work. Never create an overall period summary. Each achievement must represent exactly one distinct delivered feature or workstream in one Jira project; never group tickets from different projectKey values. Group related completed tickets only when their epic, title, description, components, labels, linked issues, or task category show they are the same feature. Do not merge tickets merely because they share a project, status, person, or date. Write each title in exactly this hyphen-separated pattern: "Epic or workstream - Project, page, or feature area - Concise completed work summary". Example: "Report panel revamp - Bank page - Added and refined the bank reporting interface". Use the actual epic name when provided; otherwise use a clear workstream name. The factual 1–3 sentence description must name the project and synthesize all grouped tickets, explaining what was completed without inventing impact, metrics, people, or details. Every source ticket is already completed, so write in completed tense. Do not create duplicates. Each Jira key must appear in exactly one achievement. Return JSON only.', 'input' => json_encode(['start_date' => $startDate, 'end_date' => $endDate, 'completed_jira_work_items_created_or_updated_in_range' => $issues], JSON_THROW_ON_ERROR), 'text' => ['format' => ['type' => 'json_schema', 'name' => 'unique_jira_work_summaries', 'strict' => true, 'schema' => ['type' => 'object', 'properties' => ['achievements' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 10, 'items' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'description' => ['type' => 'string'], 'issue_keys' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'string']]], 'required' => ['title', 'description', 'issue_keys'], 'additionalProperties' => false]]], 'required' => ['achievements'], 'additionalProperties' => false]]], 'max_output_tokens' => 2000]);
            if (!$response->successful()) throw new \RuntimeException('OpenAI HTTP ' . $response->status());
            $parsed = json_decode((string) ($response->json('output_text') ?? data_get($response->json(), 'output.0.content.0.text')), true, flags: JSON_THROW_ON_ERROR);
            $validKeys = array_flip(array_column($issues, 'key'));
            $drafts = collect($parsed['achievements'] ?? [])->filter(fn($item) => is_string($item['title'] ?? null) && is_string($item['description'] ?? null) && is_array($item['issue_keys'] ?? null))->map(function ($item) use ($validKeys) {
                return ['title' => trim($item['title']), 'description' => trim($item['description']), 'issue_keys' => array_values(array_unique(array_filter($item['issue_keys'], fn ($key) => is_string($key) && isset($validKeys[$key]))))];
            })->filter(fn($item) => $item['title'] && $item['description'] && $item['issue_keys'])->values()->all();
            $usedKeys = [];
            $drafts = collect($drafts)->map(function (array $draft) use (&$usedKeys) {
                $draft['issue_keys'] = array_values(array_filter($draft['issue_keys'], fn (string $key) => !isset($usedKeys[$key])));
                foreach ($draft['issue_keys'] as $key) $usedKeys[$key] = true;
                return $draft;
            })->filter(fn (array $draft) => $draft['issue_keys'])->values()->all();
            if (!$drafts) throw new \RuntimeException('OpenAI returned no achievement drafts.');
            Log::info('chatgpt response', ['status' => 'success', 'issue_count' => count($issues), 'draft_count' => count($drafts), 'drafts' => $drafts]);
            return $drafts;
        } catch (\Throwable $exception) {
            Log::warning('chatgpt response', ['status' => 'failed', 'issue_count' => count($issues), 'error' => $exception->getMessage()]);
            return $fallback;
        }
    }
}
