<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\FinancialGoal;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;

class FinancialOverviewReportService
{
    public function __construct(
        private readonly ReportService $reportService,
        private readonly PdfService $pdfService,
        private readonly FinancialOverviewAnalysisService $analysisService,
        private readonly FinancialIntelligenceService $financialIntelligenceService,
    ) {
    }

    public function generate(User $user, CarbonImmutable $fromDate, CarbonImmutable $toDate): array
    {
        $report = $this->reportService->financialOverview($user->id, [
            'from_date' => $fromDate->toDateString(),
            'to_date' => $toDate->toDateString(),
            'granularity' => 'monthly',
        ]);
        $singleMonthCategories = $this->singleMonthCategories($user, $toDate);
        $analysis = $this->analysisService->generate(
            $report['summary'],
            $report['daily'],
            $report['transfer_receipts_by_account'],
            $report['category_costing_comparison'],
            $report['period_category_costing'],
            $singleMonthCategories,
        );
        // The scheduled report uses the same deterministic calculations as the
        // in-app intelligence dashboard. This keeps projections out of the LLM
        // and makes the PDF usable even if OpenAI is unavailable.
        $intelligence = $this->financialIntelligenceService->dashboard($user->id, [
            'from_date' => $fromDate->toDateString(),
            'to_date' => $toDate->toDateString(),
            'closing_balance' => $report['summary']['closing_balance'],
        ]);
        $supplementary = $this->supplementaryData($user, $fromDate, $toDate);
        $supplementary['single_month_categories'] = $singleMonthCategories;
        $viewData = [
            'user' => $user,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'summary' => $report['summary'],
            'transferReceiptsByAccount' => $report['transfer_receipts_by_account'],
            'rows' => $report['daily'],
            'analysis' => $analysis,
            'categoryCostingComparison' => $report['category_costing_comparison'],
            'periodCategoryCosting' => $report['period_category_costing'],
            'financialIntelligence' => $intelligence,
            'supplementary' => $supplementary,
        ];

        return [
            ...$viewData,
            'pdf' => $this->pdfService->render('reports.financial-overview-pdf', $viewData, orientation: 'landscape'),
            'filename' => sprintf('financial-overview-%s-to-%s.pdf', $fromDate->toDateString(), $toDate->toDateString()),
        ];
    }

    private function supplementaryData(User $user, CarbonImmutable $fromDate, CarbonImmutable $toDate): array
    {
        $accounts = Account::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->orderByDesc('current_balance')
            ->limit(6)
            ->get(['name', 'type', 'institution_name', 'current_balance'])
            ->map(fn (Account $account): array => [
                'name' => $account->name,
                'type' => $account->type?->value ?? (string) $account->type,
                'institution_name' => $account->institution_name,
                'balance' => (string) $account->current_balance,
            ])
            ->all();

        $goals = FinancialGoal::query()
            ->where('user_id', $user->id)
            ->orderBy('target_date')
            ->limit(4)
            ->get(['name', 'target_amount', 'current_saved_amount', 'target_date'])
            ->map(function (FinancialGoal $goal): array {
                $target = (float) $goal->target_amount;
                $saved = (float) $goal->current_saved_amount;

                return [
                    'name' => $goal->name,
                    'target_amount' => (string) $goal->target_amount,
                    'current_saved_amount' => (string) $goal->current_saved_amount,
                    'progress_percent' => $target > 0 ? min(100, round($saved / $target * 100, 1)) : 0,
                    'target_date' => $goal->target_date?->toDateString(),
                ];
            })
            ->all();

        $expenseTypes = [TransactionType::EXPENSE->value, TransactionType::RECURRING->value];
        $expenseQuery = Transaction::query()
            ->where('transactions.user_id', $user->id)
            ->whereBetween('transactions.transaction_date', [$fromDate, $toDate])
            ->whereIn('transactions.type', $expenseTypes);
        $topExpenses = (clone $expenseQuery)
            ->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
            ->leftJoin('accounts', 'transactions.account_id', '=', 'accounts.id')
            ->orderByDesc('transactions.amount')
            ->limit(5)
            ->get([
                'transactions.transaction_date', 'transactions.amount', 'transactions.note',
                'categories.name as category_name', 'accounts.name as account_name',
            ])
            ->map(fn (Transaction $transaction): array => [
                'date' => $transaction->transaction_date?->toDateString(),
                'amount' => (string) $transaction->amount,
                'note' => $transaction->note,
                'category_name' => $transaction->getAttribute('category_name') ?: 'Uncategorized',
                'account_name' => $transaction->getAttribute('account_name'),
            ])
            ->all();
        $expenseCount = (clone $expenseQuery)->count();
        $uncategorizedCount = (clone $expenseQuery)->whereNull('category_id')->count();

        return [
            'accounts' => $accounts,
            'goals' => $goals,
            'top_expenses' => $topExpenses,
            'data_quality' => [
                'expense_count' => $expenseCount,
                'uncategorized_expense_count' => $uncategorizedCount,
                'uncategorized_percent' => $expenseCount > 0 ? round($uncategorizedCount / $expenseCount * 100, 1) : 0,
            ],
        ];
    }

    private function singleMonthCategories(User $user, CarbonImmutable $toDate): array
    {
        $historyStart = $toDate->startOfMonth()->subMonths(5);
        $rows = Transaction::query()
            ->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('transactions.user_id', $user->id)
            ->whereBetween('transactions.transaction_date', [$historyStart, $toDate])
            ->whereIn('transactions.type', [TransactionType::EXPENSE->value, TransactionType::RECURRING->value])
            ->selectRaw("COALESCE(categories.name, 'Uncategorized') as category_name")
            ->selectRaw("DATE_FORMAT(transactions.transaction_date, '%Y-%m-01') as report_month")
            ->selectRaw('SUM(transactions.amount) as total_amount')
            ->groupByRaw("COALESCE(categories.name, 'Uncategorized'), DATE_FORMAT(transactions.transaction_date, '%Y-%m-01')")
            ->get();

        return $rows
            ->groupBy('category_name')
            ->filter(fn ($categoryRows): bool => $categoryRows->count() === 1)
            ->map(function ($categoryRows, string $categoryName): array {
                $row = $categoryRows->first();

                return [
                    'category_name' => $categoryName,
                    'amount' => (string) ($row->total_amount ?? 0),
                    'month' => $row->report_month,
                    'observation' => 'Recorded in one reporting month only; review whether this was a one-off purchase, a newly started cost, or a missing category history.',
                ];
            })
            ->sortByDesc(fn (array $category): float => (float) $category['amount'])
            ->take(6)
            ->values()
            ->all();
    }
}
