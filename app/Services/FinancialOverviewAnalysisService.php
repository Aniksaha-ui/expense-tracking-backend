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
    public function generate(array $summary, iterable $rows, array $transferReceiptsByAccount, array $categoryCostingComparison, array $periodCategoryCosting): array
    {
        $config = config('services.financial_overview_ai');
        $apiKey = trim((string) ($config['api_key'] ?? ''));

        if ($apiKey === '') {
            return $this->unavailable('OpenAI analysis is not configured.');
        }

        $facts = [
            'summary' => $summary,
            'monthly_ledger' => array_slice(is_array($rows) ? array_values($rows) : iterator_to_array($rows, false), -24),
            'transfer_receipts_by_account' => $transferReceiptsByAccount,
            'category_costing_comparison' => $categoryCostingComparison,
            'selected_period_category_costing' => $periodCategoryCosting,
        ];

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(45)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => $config['model'] ?? 'gpt-4o-mini',
                    'instructions' => 'You are a careful financial-report analyst. All monetary figures are Bangladeshi Taka (BDT); always write BDT or Taka, never dollars or USD. Use only the supplied figures; do not invent data, make guarantees, or give regulated financial advice. Each insight must be concise, factual, and understandable to a non-finance reader. For category comparison, identify the largest categories and meaningful latest-month changes. Give 2–3 specific, non-prescriptive cost-reduction suggestions using only the selected report-period category data; target high-cost or frequent categories and state the relevant figure. Mention insufficient data when applicable.',
                    'input' => 'Analyze this financial overview data for the report recipient. Every monetary figure is in Bangladeshi Taka (BDT):\n'.json_encode($facts, JSON_THROW_ON_ERROR),
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'financial_overview_analysis',
                            'strict' => true,
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'snapshot' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'trends' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'category_comparison' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'watchouts' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'next_steps' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'cost_reduction_suggestions' => ['type' => 'array', 'items' => ['type' => 'string']],
                                ],
                                'required' => ['snapshot', 'trends', 'category_comparison', 'watchouts', 'next_steps', 'cost_reduction_suggestions'],
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
        $sections = ['category_comparison' => [$message]];

        return ['available' => false, 'sections' => $sections, 'text' => $message];
    }

    private function normalizeSections(array $sections): array
    {
        $keys = ['snapshot', 'trends', 'category_comparison', 'watchouts', 'next_steps', 'cost_reduction_suggestions'];

        return collect($keys)->mapWithKeys(fn (string $key): array => [
            $key => array_slice(array_values(array_filter(array_map('strval', $sections[$key] ?? []))), 0, 3),
        ])->all();
    }

    private function toText(array $sections): string
    {
        $labels = ['snapshot' => 'Snapshot', 'trends' => 'Trends', 'category_comparison' => 'Category costing comparison', 'watchouts' => 'Watchouts', 'next_steps' => 'Next steps', 'cost_reduction_suggestions' => 'Cost reduction suggestions'];

        return collect($sections)->filter()->map(fn (array $items, string $key): string => ($labels[$key] ?? $key).":\n".implode("\n", array_map(fn (string $item): string => '• '.$item, $items)))->implode("\n\n");
    }
}
