<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SavingsOffersAnalysisService
{
    public function generate(array $spending, array $offers, int $months): array
    {
        $fallback = array_map(fn (array $offer) => $this->fallback($offer, $spending, $months), $offers);
        $config = config('services.financial_overview_ai');
        $apiKey = trim((string) ($config['api_key'] ?? ''));

        if ($apiKey === '' || $offers === []) return $this->englishOnly($fallback);

        try {
            $response = Http::withToken($apiKey)->acceptJson()->timeout(45)->post('https://api.openai.com/v1/responses', [
                'model' => $config['model'] ?? 'gpt-4o-mini',
                'instructions' => 'Prepare clear English content for a Bangladesh expense report. Translate each offer title and source details into natural English when needed. Preserve all factual details and do not invent offers, expiry, item price, discount, or savings. Every figure is Bangladeshi Taka (৳). If no numeric discount is in the source, say terms must be checked. Keep the recommendation under 55 words and include the relevant category and average monthly spend when supplied.',
                'input' => json_encode(['months_in_period' => $months, 'spending' => $spending, 'offers' => $offers], JSON_THROW_ON_ERROR),
                'text' => ['format' => ['type' => 'json_schema', 'name' => 'savings_offer_recommendations', 'strict' => true, 'schema' => [
                    'type' => 'object', 'properties' => ['recommendations' => ['type' => 'array', 'items' => [
                        'type' => 'object', 'properties' => ['index' => ['type' => 'integer'], 'title' => ['type' => 'string'], 'details' => ['type' => 'string'], 'recommendation' => ['type' => 'string']],
                        'required' => ['index', 'title', 'details', 'recommendation'], 'additionalProperties' => false,
                    ]]], 'required' => ['recommendations'], 'additionalProperties' => false,
                ]]],
                'max_output_tokens' => 1200,
            ]);
            if (! $response->successful()) throw new \RuntimeException('OpenAI returned HTTP '.$response->status().'.');
            $decoded = json_decode($this->extractText($response->json()), true, flags: JSON_THROW_ON_ERROR);
            foreach ($decoded['recommendations'] ?? [] as $recommendation) {
                $index = $recommendation['index'] ?? null;
                if (is_int($index) && isset($fallback[$index]) && is_string($recommendation['title'] ?? null) && is_string($recommendation['details'] ?? null) && is_string($recommendation['recommendation'] ?? null)) {
                    $fallback[$index]['focus'] = trim($recommendation['title']);
                    $fallback[$index]['source'] = trim($recommendation['details']);
                    $fallback[$index]['recommendation'] = trim($recommendation['recommendation']);
                    $fallback[$index]['analysis_source'] = 'openai';
                }
            }
        } catch (Throwable $exception) {
            Log::warning('Savings-offers OpenAI analysis was unavailable.', ['exception' => $exception->getMessage()]);
        }
        return $this->englishOnly($fallback);
    }

    private function fallback(array $offer, array $spending, int $months): array
    {
        $match = $this->matchSpending($offer, $spending);
        $category = $match['category'] ?? 'this category';
        $average = isset($match['amount']) ? (float) $match['amount'] / max(1, $months) : null;
        $saving = $this->explicitSaving(trim(($offer['focus'] ?? '').' '.($offer['source'] ?? '')), $average);
        $lead = $average === null
            ? "This offer may be relevant to your {$category} spending."
            : "You spend about ৳".number_format($average, 0)." per month on {$category}.";
        $savingText = $saving === null
            ? ' Check the offer terms, minimum order, and expiry before using it.'
            : ' If eligible, this offer could reduce your cost by up to ৳'.number_format($saving, 0).'.';
        return $offer + ['matched_category' => $match['category'] ?? null, 'monthly_average' => $average, 'estimated_saving' => $saving, 'recommendation' => $lead.$savingText, 'analysis_source' => 'calculated'];
    }

    private function englishOnly(array $offers): array
    {
        return array_map(function (array $offer): array {
            if ($this->containsBangla((string) ($offer['focus'] ?? ''))) {
                $offer['focus'] = 'Offer from '.($offer['provider'] ?? 'the source');
            }

            if ($this->containsBangla((string) ($offer['source'] ?? ''))) {
                $offer['source'] = 'The original offer details are available at the source link. An English translation was not available for this report.';
            }

            if ($this->containsBangla((string) ($offer['recommendation'] ?? ''))) {
                $offer['recommendation'] = 'Review the offer terms, minimum order, and expiry before using it.';
            }

            return $offer;
        }, $offers);
    }

    private function containsBangla(string $text): bool
    {
        return preg_match('/[\x{0980}-\x{09FF}]/u', $text) === 1;
    }

    private function matchSpending(array $offer, array $spending): ?array
    {
        $haystack = strtolower(($offer['focus'] ?? '').' '.($offer['source'] ?? ''));
        foreach ($spending as $row) if (($category = strtolower((string) $row['category'])) !== '' && str_contains($haystack, $category)) return $row;
        return $spending[0] ?? null;
    }

    private function explicitSaving(string $text, ?float $average): ?float
    {
        if (preg_match('/(?:৳|bdt|taka|tk\\.?)[\\s]*([0-9,]+)|([0-9,]+)[\\s]*(?:৳|bdt|taka|tk\\.?)/iu', $text, $match)) return min($average ?? INF, (float) str_replace(',', '', $match[1] ?: $match[2]));
        if ($average !== null && preg_match('/([0-9]{1,2})\\s*%\\s*(?:off|discount|ছাড়)/iu', $text, $match)) return $average * ((float) $match[1] / 100);
        return null;
    }

    private function extractText(array $response): string
    {
        if (is_string($response['output_text'] ?? null)) return trim($response['output_text']);
        foreach ($response['output'] ?? [] as $output) foreach ($output['content'] ?? [] as $content) if (is_string($content['text'] ?? null)) return trim($content['text']);
        throw new \RuntimeException('OpenAI returned no analysis text.');
    }
}
