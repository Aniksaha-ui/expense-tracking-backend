<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BudgetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $date = CarbonImmutable::parse($request->query('date', now()->toDateString()));
        return $this->successResponse(['date' => $date->toDateString(), 'budgets' => Budget::query()->with('category')->where('user_id', auth()->id())->get()->map(fn (Budget $budget) => $this->summary($budget, $date))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $budget = Budget::updateOrCreate(
            ['user_id' => auth()->id(), 'category_id' => $data['category_id'], 'period_type' => $data['period_type']],
            ['amount' => $data['amount'], 'carry_forward' => $data['carry_forward']]
        );
        return $this->successResponse($budget->load('category'), 'Budget saved successfully.', 201);
    }

    public function update(Request $request, int $budgetId): JsonResponse
    {
        $budget = Budget::query()->where('user_id', auth()->id())->findOrFail($budgetId);
        $data = $this->validated($request, false);
        $budget->update($data);
        return $this->successResponse($budget->load('category'), 'Budget updated successfully.');
    }

    public function destroy(int $budgetId): JsonResponse
    {
        Budget::query()->where('user_id', auth()->id())->findOrFail($budgetId)->delete();
        return $this->successResponse([], 'Budget deleted successfully.');
    }

    private function validated(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'category_id' => [$creating ? 'required' : 'sometimes', 'integer', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('user_id', auth()->id())->where('type', 'EXPENSE'))],
            'period_type' => [$creating ? 'required' : 'sometimes', 'in:weekly,monthly'],
            'amount' => [$creating ? 'required' : 'sometimes', 'numeric', 'min:0.01'],
            'carry_forward' => ['sometimes', 'boolean'],
        ]);
    }

    private function summary(Budget $budget, CarbonImmutable $date): array
    {
        [$start, $end] = $budget->period_type === 'weekly'
            ? [$date->startOfWeek(), $date->endOfWeek()]
            : [$date->startOfMonth(), $date->endOfMonth()];
        $spent = $this->spending($budget, $start, $end);
        $carry = $budget->carry_forward ? $this->previousRemainder($budget, $start) : 0.0;
        $effective = (float) $budget->amount + $carry;
        $remaining = $effective - $spent;
        $daysRemaining = max($date->startOfDay()->diffInDays($end->endOfDay(), false) + 1, 1);
        $percent = $effective > 0 ? ($spent / $effective) * 100 : 0;
        $warning = $percent >= 100 ? 'exceeded' : ($percent >= 80 ? 'warning' : ($percent >= 50 ? 'watch' : 'on_track'));
        return ['id' => $budget->id, 'category_id' => $budget->category_id, 'category_name' => $budget->category?->name, 'period_type' => $budget->period_type, 'amount' => (float) $budget->amount, 'carry_forward' => $budget->carry_forward, 'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(), 'spent' => $spent, 'carry_amount' => $carry, 'effective_budget' => $effective, 'remaining' => $remaining, 'daily_allowance' => max($remaining, 0) / $daysRemaining, 'progress_percent' => round($percent, 1), 'warning_level' => $warning];
    }

    private function previousRemainder(Budget $budget, CarbonImmutable $currentStart): float
    {
        [$start, $end] = $budget->period_type === 'weekly'
            ? [$currentStart->subWeek()->startOfWeek(), $currentStart->subWeek()->endOfWeek()]
            : [$currentStart->subMonth()->startOfMonth(), $currentStart->subMonth()->endOfMonth()];
        return max(0, (float) $budget->amount - $this->spending($budget, $start, $end));
    }

    private function spending(Budget $budget, CarbonImmutable $start, CarbonImmutable $end): float
    {
        return (float) DB::table('transactions')->where('user_id', auth()->id())->where('category_id', $budget->category_id)->whereIn('type', ['EXPENSE', 'RECURRING'])->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])->sum('amount');
    }
}
