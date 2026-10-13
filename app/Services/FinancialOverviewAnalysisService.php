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
                    'instructions' => 'You are a careful financial-report analyst. All monetary figures are Bangladeshi Taka (BDT); always write BDT or Taka, never dollars or USD. Use only supplied figures; do not invent causes, transactions, guarantees, or regulated financial advice. Produce one concise, non-repetitive report analysis. In spending_analysis, explain the largest category shares and only material category movement supported by the supplied category data. Clearly label partial periods and never interpret missing partial-month spending as a permanent reduction. Recommendations must say review, assess, or set a limit unless the data directly supports a stronger claim; never suggest eliminating a category solely because it is large.',
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
                                    'key_findings' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'spending_analysis' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'watchouts' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'recommendations' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'limitations' => ['type' => 'array', 'items' => ['type' => 'string']],
                                ],
                                'required' => ['executive_summary', 'key_findings', 'spending_analysis', 'watchouts', 'recommendations', 'limitations'],
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
        $keys = ['key_findings', 'spending_analysis', 'watchouts', 'recommendations', 'limitations'];
        $normalized = collect($keys)->mapWithKeys(fn (string $key): array => [$key => array_slice(array_values(array_filter(array_map('strval', $sections[$key] ?? []))), 0, 3)])->all();
        $normalized['executive_summary'] = trim((string) ($sections['executive_summary'] ?? ''));
        return $normalized;
    }

    private function toText(array $sections): string
    {
        $labels = ['key_findings' => 'Key findings', 'spending_analysis' => 'Spending analysis', 'watchouts' => 'Watchouts', 'recommendations' => 'Recommendations', 'limitations' => 'Limitations'];
        $blocks = [];
        if (!empty($sections['executive_summary'])) $blocks[] = 'Executive summary: '.$sections['executive_summary'];
        foreach ($labels as $key => $label) if (!empty($sections[$key])) $blocks[] = $label.":\n".implode("\n", array_map(fn (string $item): string => '• '.$item, $sections[$key]));
        return implode("\n\n", $blocks);
    }
}
