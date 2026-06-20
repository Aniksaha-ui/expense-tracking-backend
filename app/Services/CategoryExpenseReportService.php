<?php

namespace App\Services;

use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

class CategoryExpenseReportService
{
    public function __construct(
        private readonly ReportService $reportService,
        private readonly PdfService $pdfService,
    ) {
    }

    public function generate(
        User $user,
        CarbonImmutable $fromDate,
        CarbonImmutable $toDate,
        ?array $summary = null,
    ): array
    {
        $summary ??= $this->summary($user, $fromDate, $toDate);
        $viewData = [
            'user' => $user,
            'rows' => $summary['rows'],
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'total' => $summary['total'],
            'transactionCount' => $summary['transaction_count'],
            'categoryCount' => $summary['category_count'],
            'averageExpense' => $summary['average_expense'],
        ];

        return [
            ...$viewData,
            'pdf' => $this->pdfService->render(
                'reports.category-expenses-pdf',
                $viewData,
                orientation: 'landscape',
            ),
            'filename' => sprintf(
                'category-expenses-%s-to-%s.pdf',
                $fromDate->toDateString(),
                $toDate->toDateString()
            ),
        ];
    }

    public function summary(User $user, CarbonImmutable $fromDate, CarbonImmutable $toDate): array
    {
        $rows = $this->reportService->categoryBreakdown($user->id, [
            'from_date' => $fromDate->toDateString(),
            'to_date' => $toDate->toDateString(),
        ])->map(fn (object $row): array => [
            'category_name' => $row->category_name,
            'transaction_count' => (int) $row->transaction_count,
            'total_amount' => (string) BigDecimal::of((string) $row->total_amount)->toScale(2),
        ]);

        $total = $rows->reduce(
            fn (BigDecimal $sum, array $row): BigDecimal => $sum->plus($row['total_amount']),
            BigDecimal::zero()
        )->toScale(2);
        $transactionCount = $rows->sum('transaction_count');
        $rows = $rows->map(fn (array $row): array => [
            ...$row,
            'percentage' => $total->isZero()
                ? '0.0'
                : (string) BigDecimal::of($row['total_amount'])
                    ->dividedBy($total, 4, RoundingMode::HALF_UP)
                    ->multipliedBy(100)
                    ->toScale(1, RoundingMode::HALF_UP),
        ]);

        return [
            'rows' => $rows,
            'total' => (string) $total,
            'transaction_count' => $transactionCount,
            'category_count' => $rows->count(),
            'average_expense' => $transactionCount === 0
                ? '0.00'
                : (string) $total->dividedBy($transactionCount, 2, RoundingMode::HALF_UP),
        ];
    }
}
