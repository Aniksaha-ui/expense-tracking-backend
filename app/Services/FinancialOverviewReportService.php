<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;

class FinancialOverviewReportService
{
    public function __construct(
        private readonly ReportService $reportService,
        private readonly PdfService $pdfService,
    ) {
    }

    public function generate(User $user, CarbonImmutable $fromDate, CarbonImmutable $toDate): array
    {
        $report = $this->reportService->financialOverview($user->id, [
            'from_date' => $fromDate->toDateString(),
            'to_date' => $toDate->toDateString(),
            'granularity' => 'monthly',
        ]);
        $viewData = [
            'user' => $user,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'summary' => $report['summary'],
            'rows' => $report['daily'],
        ];

        return [
            ...$viewData,
            'pdf' => $this->pdfService->render('reports.financial-overview-pdf', $viewData, orientation: 'landscape'),
            'filename' => sprintf('financial-overview-%s-to-%s.pdf', $fromDate->toDateString(), $toDate->toDateString()),
        ];
    }
}
