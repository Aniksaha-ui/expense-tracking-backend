<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JiraIntegration;
use App\Services\JiraAchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class JiraAchievementController extends Controller
{
    public function show(): JsonResponse
    {
        $item = JiraIntegration::where('user_id', auth()->id())->first();
        return $this->successResponse($item ? ['configured' => true, 'base_url' => $item->base_url, 'email' => $item->email, 'jql' => $item->jql] : ['configured' => false]);
    }
    public function save(Request $request): JsonResponse
    {
        $data = $request->validate(['base_url' => ['required', 'url', 'max:2048'], 'email' => ['required', 'email', 'max:255'], 'api_token' => ['nullable', 'string', 'max:1000'], 'jql' => ['nullable', 'string', 'max:4000']]);
        $existing = JiraIntegration::where('user_id', auth()->id())->first();
        if (!$existing && empty($data['api_token'])) return $this->errorResponse('An API token is required for the first connection.', status: 422);
        if ($existing && empty($data['api_token'])) unset($data['api_token']);
        JiraIntegration::updateOrCreate(['user_id' => auth()->id()], $data);
        return $this->successResponse([], 'Jira connection saved.');
    }
    public function preview(Request $request, JiraAchievementService $jira): JsonResponse
    {
        $data = $request->validate(['start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after_or_equal:start_date']]);
        $integration = JiraIntegration::where('user_id', auth()->id())->first();
        if (!$integration) return $this->errorResponse('Save your Jira connection before fetching a preview.', status: 422);
        try {
            $preview = $jira->preview($integration, $data['start_date'], $data['end_date']);
            Log::info('jira achievement preview', ['user_id' => auth()->id(), 'start_date' => $data['start_date'], 'end_date' => $data['end_date'], 'generated_count' => count($preview['achievements'])]);
            return $this->successResponse($preview);
        } catch (\Throwable $exception) {
            Log::error('jira achievement preview', ['user_id' => auth()->id(), 'start_date' => $data['start_date'], 'end_date' => $data['end_date'], 'error' => $exception->getMessage()]);
            return $this->errorResponse('Unable to fetch Jira achievements: ' . $exception->getMessage(), status: 422);
        }
    }
}
