<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CronReportDeliverySettings\RunCronReportJobRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
class CronReportJobController extends Controller { public function run(RunCronReportJobRequest $request, string $jobKey): JsonResponse { $command = match ($jobKey) { 'expense_reports' => 'expense-reports:email', 'cost_reduction_reports' => 'cost-reduction-reports:email', 'expense_intelligence_reports' => 'expense-intelligence-reports:email', default => null }; if (! $command) return $this->errorResponse('Unsupported cron report job.', status: 404); $parameters = ['--from' => $request->validated('from_date'), '--to' => $request->validated('to_date'), '--user' => auth()->id()]; if ($jobKey === 'expense_intelligence_reports') $parameters['frequency'] = $request->validated('frequency') ?? 'daily'; $exitCode = Artisan::call($command, $parameters); if ($exitCode !== 0) return $this->errorResponse('The report command did not complete successfully.', ['output' => Artisan::output()], 500); return $this->successResponse(['output' => Artisan::output(), 'message' => 'Report command completed successfully.'], 'Report command completed successfully.'); } }
