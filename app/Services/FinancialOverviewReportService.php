<?php

namespace App\Services;

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
        $analysis = $this->analysisService->generate(
            $report['summary'],
            $report['daily'],
            $report['transfer_receipts_by_account'],
            $report['category_costing_comparison'],
            $report['period_category_costing'],
        );
        // The scheduled report uses the same deterministic calculations as the
        // in-app intelligence dashboard. This keeps projections out of the LLM
        // and makes the PDF usable even if OpenAI is unavailable.
        $intelligence = $this->financialIntelligenceService->dashboard($user->id, [
            'from_date' => $fromDate->toDateString(),
            'to_date' => $toDate->toDateString(),
            'closing_balance' => $report['summary']['closing_balance'],
        ]);
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
        ];

        return [
            ...$viewData,
            'pdf' => $this->pdfService->render('reports.financial-overview-pdf', $viewData, orientation: 'landscape'),
            'filename' => sprintf('financial-overview-%s-to-%s.pdf', $fromDate->toDateString(), $toDate->toDateString()),
        ];
    }
}
