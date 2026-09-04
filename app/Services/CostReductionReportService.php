<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;

class CostReductionReportService
{
    public function __construct(
        private readonly CategoryExpenseAnalyzer $categoryExpenseAnalyzer,
        private readonly PdfService $pdfService,
    ) {
    }

    public function generate(User $user, CarbonImmutable $fromDate, CarbonImmutable $toDate, ?array $analysis = null): array
    {
        $analysis ??= $this->summary($user, $fromDate, $toDate);
        $viewData = [
            'user' => $user,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'rows' => $analysis['rows'],
            'alerts' => $analysis['alerts'],
            'chartRows' => $analysis['chart_rows'],
            'summary' => $analysis['summary'],
        ];

        return [
            ...$viewData,
            'pdf' => $this->pdfService->render(
                'reports.cost-reduction-pdf',
                $viewData,
                orientation: 'landscape',
            ),
            'filename' => sprintf(
                'cost-reduction-%s-to-%s.pdf',
                $fromDate->toDateString(),
                $toDate->toDateString()
            ),
        ];
    }

    public function summary(User $user, CarbonImmutable $fromDate, CarbonImmutable $toDate): array
    {
        return $this->categoryExpenseAnalyzer->analyze($user->id, $fromDate, $toDate);
    }
}
