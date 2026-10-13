<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\FinancialGoals\StoreFinancialGoalRequest;
use App\Http\Requests\Api\FinancialGoals\UpdateFinancialGoalRequest;
use App\Http\Requests\Api\FinancialIntelligence\FinancialIntelligenceRequest;
use App\Http\Requests\Api\FinancialIntelligence\FinancialQuestionRequest;
use App\Services\FinancialIntelligenceService;
use App\Services\FinancialIntelligenceAiService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class FinancialIntelligenceController extends Controller
{
    public function __construct(private readonly FinancialIntelligenceService $financialIntelligence, private readonly FinancialIntelligenceAiService $financialIntelligenceAi) {}

    public function dashboard(FinancialIntelligenceRequest $request): JsonResponse
    {
        return $this->respond(fn () => $this->financialIntelligence->dashboard(auth()->id(), $request->validated()));
    }

    public function forecast(FinancialIntelligenceRequest $request): JsonResponse
    {
        $input = $request->validated();
        return $this->respond(fn () => $this->financialIntelligence->forecast(auth()->id(), CarbonImmutable::parse($input['to_date'] ?? now())->endOfDay(), $input));
    }

    public function goals(): JsonResponse
    {
        return $this->respond(fn () => $this->financialIntelligence->goals(auth()->id()));
    }

    public function storeGoal(StoreFinancialGoalRequest $request): JsonResponse
    {
        return $this->respond(fn () => $this->financialIntelligence->saveGoal(auth()->id(), $request->validated()), 'Financial goal created successfully.', 201);
    }

    public function updateGoal(UpdateFinancialGoalRequest $request, int $goalId): JsonResponse
    {
        return $this->respond(fn () => $this->financialIntelligence->saveGoal(auth()->id(), $request->validated(), $goalId), 'Financial goal updated successfully.');
    }

    public function destroyGoal(int $goalId): JsonResponse
    {
        return $this->respond(function () use ($goalId): array { $this->financialIntelligence->deleteGoal(auth()->id(), $goalId); return []; }, 'Financial goal deleted successfully.');
    }

    public function ask(FinancialQuestionRequest $request): JsonResponse
    {
        $input = $request->validated();
        return $this->respond(function () use ($input): array {
            $dashboard = $this->financialIntelligence->dashboard(auth()->id(), $input);
            return [...$this->financialIntelligenceAi->answer($input['question'], $dashboard), 'grounded_metrics' => $dashboard['metrics'], 'insights' => $dashboard['insights']];
        });
    }

    private function respond(callable $action, string $message = 'Fetch information successfully', int $status = 200): JsonResponse
    {
        try { return $this->successResponse($action(), $message, $status); }
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException) { return $this->errorResponse('Financial goal not found.', status: 404); }
        catch (\Throwable $exception) { return $this->errorResponse($exception->getMessage(), status: 500); }
    }
}
