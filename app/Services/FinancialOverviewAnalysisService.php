<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FinancialOverviewAnalysisService
{
    /**
     * Generate a short narrative from report facts only. Failure is intentionally
     * non-blocking: scheduled financial reports must still be delivered.
     */
    public function generate(array $summary, iterable $rows, array $transferReceiptsByAccount, array $categoryCostingComparison, array $periodCategoryCosting, array $singleMonthCategories = [], array $reportPeriod = [], array $accountActivity = []): array
    {
        $config = config('services.financial_overview_ai');
        $apiKey = trim((string) ($config['api_key'] ?? ''));

        if ($apiKey === '') {
            return $this->unavailable('OpenAI analysis is not configured.');
        }

        $facts = [
            'reporting_period' => $reportPeriod,
            'summary' => $summary,
            'monthly_ledger' => array_slice(is_array($rows) ? array_values($rows) : iterator_to_array($rows, false), -24),
            'transfer_receipts_by_account' => $transferReceiptsByAccount,
            'category_costing_comparison' => $categoryCostingComparison,
            'selected_period_category_costing' => $periodCategoryCosting,
            'single_month_categories_in_last_six_months' => $singleMonthCategories,
            'account_activity' => $accountActivity,
        ];

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(45)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => $config['model'] ?? 'gpt-4o-mini',
                'instructions' => 'You are a careful financial-report analyst. All monetary figures are Bangladeshi Taka (BDT); always write BDT or Taka, never dollars or USD. Use only supplied figures; do not invent causes, transactions, guarantees, or regulated financial advice. Produce one concise, non-repetitive report analysis. Each list must contain at most three concise items. In spending_analysis, explain the largest category shares, material category movement, and categories with positive spending in only one reporting month. In spending_pressure_analysis, identify evidence-based pressure on available money: for example, expenses exceeding recorded income, a negative net cash flow, a material decline from opening to closing balance, or a concentration in a high-cost category. State the specific recorded figures that support any warning. Do not say the user cannot replenish money, cannot afford something, or will run out of money unless the supplied data proves it; when future income, obligations, or history are insufficient, say that replenishment capacity cannot be determined from the recorded data. In account_analysis, describe material account balance movement using only opening balance, money added, money out, transfers, closing balance, and net change. Transfers between the user’s accounts redistribute money; do not call them portfolio income, loss, or spending. Describe a single-month category as a review signal only: it may be one-off, newly started, or missing history; do not claim which without evidence. Clearly label partial periods and never interpret missing partial-month spending as a permanent reduction. Recommendations must say review, assess, or set a limit unless the data directly supports a stronger claim; never suggest eliminating a category solely because it is large.',
                    'input' => 'Analyze this financial overview data for the report recipient. Every monetary figure is in Bangladeshi Taka (BDT):\n'.json_encode($facts, JSON_THROW_ON_ERROR),
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'financial_overview_analysis',
                            'strict' => true,
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'executive_summary' => ['type' => 'string'],
                                    'key_findings' => ['type' => 'array', 'maxItems' => 3, 'items' => ['type' => 'string']],
                                    'spending_analysis' => ['type' => 'array', 'maxItems' => 3, 'items' => ['type' => 'string']],
                                    'spending_pressure_analysis' => ['type' => 'array', 'maxItems' => 3, 'items' => ['type' => 'string']],
                                    'account_analysis' => ['type' => 'array', 'maxItems' => 3, 'items' => ['type' => 'string']],
                                    'watchouts' => ['type' => 'array', 'maxItems' => 3, 'items' => ['type' => 'string']],
                                    'recommendations' => ['type' => 'array', 'maxItems' => 3, 'items' => ['type' => 'string']],
                                    'limitations' => ['type' => 'array', 'maxItems' => 3, 'items' => ['type' => 'string']],
                                ],
                                'required' => ['executive_summary', 'key_findings', 'spending_analysis', 'spending_pressure_analysis', 'account_analysis', 'watchouts', 'recommendations', 'limitations'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                    'max_output_tokens' => 700,
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException('OpenAI returned HTTP '.$response->status().'.');
            }

            $text = $this->extractText($response->json());

            if ($text === '') {
                throw new \RuntimeException('OpenAI returned no analysis text.');
            }

            $sections = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
            $sections = $this->normalizeSections($sections);

            return ['available' => true, 'sections' => $sections, 'text' => $this->toText($sections)];
        } catch (Throwable $exception) {
            Log::warning('Financial overview OpenAI analysis was unavailable.', [
                'exception' => $exception->getMessage(),
            ]);

            return $this->unavailable('OpenAI analysis was unavailable when this report was generated.');
        }
    }

    private function extractText(array $response): string
    {
        if (is_string($response['output_text'] ?? null)) {
            return trim($response['output_text']);
        }

        foreach ($response['output'] ?? [] as $output) {
            foreach ($output['content'] ?? [] as $content) {
                if (is_string($content['text'] ?? null)) {
                    return trim($content['text']);
                }
            }
        }

        return '';
    }

    private function unavailable(string $message): array
    {
        $sections = ['executive_summary' => $message, 'limitations' => [$message]];

        return ['available' => false, 'sections' => $sections, 'text' => $message];
    }

    private function normalizeSections(array $sections): array
    {
        $keys = ['key_findings', 'spending_analysis', 'spending_pressure_analysis', 'account_analysis', 'watchouts', 'recommendations', 'limitations'];
        $normalized = collect($keys)->mapWithKeys(fn (string $key): array => [$key => array_values(array_filter(array_map('strval', $sections[$key] ?? [])))])->all();
        $normalized['executive_summary'] = trim((string) ($sections['executive_summary'] ?? ''));
        return $normalized;
    }

    private function toText(array $sections): string
    {
        $labels = ['key_findings' => 'Key findings', 'spending_analysis' => 'Spending analysis', 'spending_pressure_analysis' => 'Spending pressure and replenishment risk', 'account_analysis' => 'Account analysis', 'watchouts' => 'Watchouts', 'recommendations' => 'Recommendations', 'limitations' => 'Limitations'];
        $blocks = [];
        if (!empty($sections['executive_summary'])) $blocks[] = 'Executive summary: '.$sections['executive_summary'];
        foreach ($labels as $key => $label) if (!empty($sections[$key])) $blocks[] = $label.":\n".implode("\n", array_map(fn (string $item): string => '• '.$item, $sections[$key]));
        return implode("\n\n", $blocks);
    }
}
