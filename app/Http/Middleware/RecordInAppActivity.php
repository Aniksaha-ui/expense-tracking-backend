<?php

namespace App\Http\Middleware;

use App\Services\NotificationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordInAppActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            || $response->getStatusCode() >= 400
            || ! $request->user()
            || $this->isNotificationRequest($request)) {
            return $response;
        }

        [$type, $title, $path] = $this->activityFor($request);
        app(NotificationService::class)->create(
            $request->user()->id,
            $type,
            $title,
            'Your change was saved successfully.',
            // Repeating the same successful request must not flood the inbox.
            // A changed payload produces a different key and remains visible.
            'activity:'.sha1($request->method().'|'.$request->path().'|'.json_encode($request->except(['_token']))),
            ['method' => $request->method(), 'path' => '/'.$request->path()],
            $path,
        );

        return $response;
    }

    private function isNotificationRequest(Request $request): bool
    {
        return $request->is('api/notifications*') || $request->is('api/notification-preferences*') || $request->is('api/cron-report-jobs*') || $request->is('api/cron-report-delivery-settings*');
    }

    private function activityFor(Request $request): array
    {
        $path = '/'.$request->path();

        foreach ([
            'bills' => ['bill_activity', 'Bill or subscription updated', '/bills'],
            'transactions' => ['transaction_activity', 'Transaction updated', '/transactions'],
            'transfers' => ['transfer_activity', 'Transfer updated', '/transfers'],
            'accounts' => ['account_activity', 'Account updated', '/accounts'],
            'budgets' => ['budget_activity', 'Budget updated', '/budgets'],
            'recurring-expenses' => ['recurring_activity', 'Recurring expense updated', '/recurring-expenses'],
            'financial-intelligence/goals' => ['goal_activity', 'Financial goal updated', '/financial-goals'],
            'achievements' => ['achievement_activity', 'Achievement updated', '/achievements'],
            'jira-achievements' => ['achievement_activity', 'Jira achievement workflow updated', '/achievements'],
        ] as $needle => [$type, $title, $actionPath]) {
            if (str_contains($path, $needle)) {
                return [$type, $this->titleForMethod($request->method(), $title), $actionPath];
            }
        }

        return ['activity', $this->titleForMethod($request->method(), 'Workspace item'), '/notifications'];
    }

    private function titleForMethod(string $method, string $subject): string
    {
        return match ($method) {
            'POST' => str_replace('updated', 'created', $subject),
            'DELETE' => str_replace('updated', 'deleted', $subject),
            default => $subject,
        };
    }
}
