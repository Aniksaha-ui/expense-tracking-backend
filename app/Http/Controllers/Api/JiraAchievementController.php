<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JiraIntegration;
use App\Models\JiraIssue;
use App\Models\JiraGeneratedAchievement;
use App\Services\JiraAchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
    public function verify(JiraAchievementService $jira): JsonResponse
    {
        $integration = JiraIntegration::where('user_id', auth()->id())->first();
        if (!$integration) return $this->errorResponse('Save your Jira connection before verifying it.', status: 422);
        try { return $this->successResponse($jira->verify($integration), 'Jira connection verified.'); }
        catch (\Throwable $exception) { Log::error('jira connection verification', ['user_id' => auth()->id(), 'error' => $exception->getMessage()]); return $this->errorResponse($exception->getMessage(), status: 422); }
    }
    public function syncAndGenerate(Request $request, JiraAchievementService $jira): JsonResponse
    {
        $data = $request->validate(['start_date'=>['required','date'], 'end_date'=>['required','date','after_or_equal:start_date'], 'organization'=>['nullable','string','max:160']]);
        $integration = JiraIntegration::where('user_id', auth()->id())->first(); if (!$integration) return $this->errorResponse('Save your Jira connection before syncing.', status:422);
        try { $preview=$jira->preview($integration,$data['start_date'],$data['end_date']); $issueIdsByKey=[]; foreach($preview['issues'] as $issue) { $row=JiraIssue::updateOrCreate(['user_id'=>auth()->id(),'issue_key'=>$issue['key']], ['summary'=>$issue['summary'],'payload'=>$issue,'jira_created_at'=>$issue['created']??null,'jira_updated_at'=>$issue['updated']??null,'last_synced_at'=>now()]); $issueIdsByKey[$issue['key']]=$row->id; }
            $records=DB::transaction(fn()=>collect($preview['achievements'])->map(function($item) use($data,$issueIdsByKey){ $record=JiraGeneratedAchievement::create(['user_id'=>auth()->id(),'type'=>'workplace','title'=>$item['title'],'achievement_date'=>$item['achieved_on'],'organization'=>$data['organization']??null,'description'=>$item['description'],'reference_url'=>$item['reference_url']??null,'generated_at'=>now()]); $record->issues()->sync(array_values(array_intersect_key($issueIdsByKey, array_flip($item['source_issue_keys'] ?? [])))); return $record->load('issues'); })); return $this->successResponse(['issues_synced'=>count($issueIdsByKey),'achievements'=>$records], 'Jira tasks synchronized and unique achievement drafts generated.',201);
        } catch(\Throwable $e) { Log::error('jira sync generate',['error'=>$e->getMessage()]); return $this->errorResponse($e->getMessage(),status:422); }
    }
    public function generatedIndex(): JsonResponse { return $this->successResponse(JiraGeneratedAchievement::where('user_id',auth()->id())->with('issues')->latest()->paginate(10)); }
    public function updateGenerated(Request $request, int $id): JsonResponse { $record=JiraGeneratedAchievement::where('user_id',auth()->id())->findOrFail($id); $data=$request->validate(['type'=>['sometimes','string','max:100'],'title'=>['sometimes','string','max:200'],'achievement_date'=>['sometimes','date'],'organization'=>['nullable','string','max:160'],'description'=>['sometimes','string','max:4000'],'reference_url'=>['nullable','string','max:2048'],'status'=>['sometimes','in:draft,approved']]); $record->update([...$data,'manually_edited'=>true]); return $this->successResponse($record->load('issues')); }
    public function deleteGenerated(int $id): JsonResponse { JiraGeneratedAchievement::where('user_id',auth()->id())->findOrFail($id)->delete(); return $this->successResponse([], 'Generated achievement deleted.'); }
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
