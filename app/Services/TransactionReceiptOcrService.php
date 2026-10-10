<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TransactionReceiptOcrService
{
    public function __construct(private readonly ProviderConfigurationService $providerConfigurations) {}
    public function scan(UploadedFile $receipt, array $expenseCategories = []): array
    {
        $provider = $this->providerConfigurations->ocr();
        if (! $provider['enabled']) {
            throw new RuntimeException('Receipt scanning is not configured. Ask an administrator to enable OCR.');
        }

        return match (strtolower((string) $provider['driver'])) {
            'google_vision', 'google' => $this->scanWithGoogleVision($receipt, $provider),
            'openai' => $this->scanWithOpenAi($receipt, $expenseCategories, $provider['openai_api_key']),
            default => throw new RuntimeException('The configured receipt OCR provider is not supported.'),
        };
    }

    private function scanWithGoogleVision(UploadedFile $receipt, array $provider): array
    {
        $config = config('services.transaction_ocr.google_vision');
        $projectId = trim((string) ($provider['google_project'] ?? $config['project_id'] ?? ''));
        $credentialsPath = trim((string) ($provider['google_credentials'] ?? $config['credentials'] ?? ''));

        if ($projectId === '' || $credentialsPath === '' || ! is_readable($credentialsPath)) {
            throw new RuntimeException('Google Vision is not configured. Set GOOGLE_CLOUD_PROJECT and GOOGLE_APPLICATION_CREDENTIALS.');
        }

        $credentials = json_decode((string) file_get_contents($credentialsPath), true);

        if (! is_array($credentials) || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            throw new RuntimeException('Google Vision credentials are invalid. Use the service-account JSON key file.');
        }

        $token = (new ServiceAccountCredentials(
            ['https://www.googleapis.com/auth/cloud-vision'],
            $credentials,
        ))->fetchAuthToken();
        $accessToken = $token['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Google Vision authentication failed. Verify the service-account key.');
        }

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(45)
            ->post('https://vision.googleapis.com/v1/images:annotate', [
                'requests' => [[
                    'image' => [
                        'content' => base64_encode((string) file_get_contents($receipt->getRealPath())),
                    ],
                    'features' => [[
                        'type' => 'DOCUMENT_TEXT_DETECTION',
                        'maxResults' => 1,
                    ]],
                ]],
            ]);

        if (! $response->successful()) {
            report(new RuntimeException('Google Vision OCR returned '.$response->status().': '.$response->body()));
            throw new RuntimeException('Receipt could not be scanned. Please try a clearer image or enter it manually.');
        }

        $result = $response->json('responses.0');

        if (! is_array($result) || ! empty($result['error'])) {
            report(new RuntimeException('Google Vision OCR response error: '.json_encode($result['error'] ?? [])));
            throw new RuntimeException('Receipt could not be scanned. Please try a clearer image or enter it manually.');
        }

        $rawText = trim((string) data_get($result, 'fullTextAnnotation.text', ''));

        if ($rawText === '') {
            throw new RuntimeException('No readable text was found on this receipt. Please try a clearer image.');
        }

        return $this->extractReceiptDetails($rawText);
    }

    private function scanWithOpenAi(UploadedFile $receipt, array $expenseCategories, ?string $configuredApiKey = null): array
    {
        $config = config('services.transaction_ocr.openai');
        $apiKey = trim((string) ($configuredApiKey ?? $config['api_key'] ?? ''));

        if ($apiKey === '') {
            throw new RuntimeException('OpenAI receipt scanning is not configured. Set OCR_OPENAI_API_KEY.');
        }

        $image = sprintf('data:%s;base64,%s', $receipt->getMimeType(), base64_encode((string) file_get_contents($receipt->getRealPath())));
        $categoryOptions = $expenseCategories === []
            ? 'No categories are available; use null for category.'
            : 'Choose category only from this exact list, or use null when none fits: '.json_encode(array_values($expenseCategories));

        $response = Http::withToken($apiKey)->acceptJson()->timeout(45)->post('https://api.openai.com/v1/responses', [
            'model' => $config['model'] ?? 'gpt-4.1-mini',
            'input' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'input_text', 'text' => 'Read this receipt and return ONLY JSON. Include merchant, amount (the receipt total), transaction_date (YYYY-MM-DD|null), note, type (EXPENSE|INCOME|DEPOSIT), confidence (0-1), raw_text, and items. items must be an array with one entry per purchasable line item or service, not quantities alone, taxes, discounts, subtotals, payment rows, or totals. Each item must include description, amount (the line total), category, transaction_date, note, type, and confidence. Apply the receipt transaction date and EXPENSE type to every item unless clearly shown otherwise. '.$categoryOptions.' Use null for unclear values.'],
                    ['type' => 'input_image', 'image_url' => $image],
                ],
            ]],
        ]);

        if (! $response->successful()) {
            report(new RuntimeException('OpenAI OCR returned '.$response->status().': '.$response->body()));

            if ($response->status() === 429 && $response->json('error.code') === 'credit_balance_exhausted') {
                throw new RuntimeException('Receipt scanning is unavailable because the configured OpenAI API project has no remaining credits. Add API credits or use a key from a funded project.');
            }

            throw new RuntimeException('Receipt could not be scanned. Please try a clearer image or enter it manually.');
        }

        return $this->normalize($this->decode($this->extractOpenAiText($response->json())));
    }

    private function extractReceiptDetails(string $rawText): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $rawText))));
        $merchant = $this->findMerchant($lines);
        $amount = $this->findAmount($rawText);

        return [
            'merchant' => $merchant,
            'amount' => $amount === null ? null : number_format($amount, 2, '.', ''),
            'transaction_date' => $this->findDate($rawText),
            'note' => $merchant,
            'type' => 'EXPENSE',
            'confidence' => $amount !== null ? 0.85 : 0.6,
            'raw_text' => mb_substr($rawText, 0, 5000),
            'items' => [],
        ];
    }

    private function findAmount(string $text): ?float
    {
        if (preg_match('/(?:grand\s+total|total\s+due|amount\s+due|total)\D{0,25}([0-9][0-9,]*(?:\.[0-9]{1,2})?)/i', $text, $match)) {
            return (float) str_replace(',', '', $match[1]);
        }

        return null;
    }

    private function findDate(string $text): ?string
    {
        if (preg_match('/\b(20\d{2})[-\/](\d{1,2})[-\/](\d{1,2})\b/', $text, $match)) {
            return sprintf('%04d-%02d-%02d', $match[1], $match[2], $match[3]);
        }

        return null;
    }

    private function findMerchant(array $lines): ?string
    {
        foreach (array_slice($lines, 0, 8) as $line) {
            if (! preg_match('/^(invoice|bill\s+to|date|phone|email|website|page)\b/i', $line) && mb_strlen($line) > 2) {
                return mb_substr($line, 0, 1000);
            }
        }

        return null;
    }

    private function extractOpenAiText(array $response): string
    {
        if (is_string($response['output_text'] ?? null)) {
            return $response['output_text'];
        }

        foreach ($response['output'] ?? [] as $output) {
            foreach ($output['content'] ?? [] as $content) {
                if (is_string($content['text'] ?? null)) {
                    return $content['text'];
                }
            }
        }

        throw new RuntimeException('Receipt scanner did not return readable text.');
    }

    private function decode(string $text): array
    {
        $data = json_decode(trim(preg_replace('/^```(?:json)?|```$/m', '', $text)), true);

        if (! is_array($data)) {
            throw new RuntimeException('Receipt scanner returned an unreadable result.');
        }

        return $data;
    }

    private function normalize(array $data): array
    {
        $amount = is_numeric($data['amount'] ?? null) ? round((float) $data['amount'], 2) : null;
        $date = isset($data['transaction_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $data['transaction_date']) ? $data['transaction_date'] : null;
        $type = strtoupper((string) ($data['type'] ?? 'EXPENSE'));

        $normalized = [
            'merchant' => $this->stringOrNull($data['merchant'] ?? null),
            'amount' => $amount !== null && $amount >= 0 ? number_format($amount, 2, '.', '') : null,
            'transaction_date' => $date,
            'note' => $this->stringOrNull($data['note'] ?? null),
            'type' => in_array($type, ['EXPENSE', 'INCOME', 'DEPOSIT'], true) ? $type : 'EXPENSE',
            'confidence' => min(1, max(0, (float) ($data['confidence'] ?? 0))),
            'raw_text' => mb_substr((string) ($data['raw_text'] ?? ''), 0, 5000),
        ];

        $items = array_values(array_filter(array_map(
            fn (mixed $item) => is_array($item) ? $this->normalizeItem($item, $normalized) : null,
            is_array($data['items'] ?? null) ? $data['items'] : [],
        )));

        // A receipt without identifiable line items remains useful in the existing single-entry flow.
        $normalized['items'] = $items;

        return $normalized;
    }

    private function normalizeItem(array $item, array $receipt): ?array
    {
        $amount = is_numeric($item['amount'] ?? null) ? round((float) $item['amount'], 2) : null;

        if ($amount === null || $amount < 0) {
            return null;
        }

        $type = strtoupper((string) ($item['type'] ?? $receipt['type']));
        $date = (string) ($item['transaction_date'] ?? $receipt['transaction_date'] ?? '');

        return [
            'description' => $this->stringOrNull($item['description'] ?? $item['merchant'] ?? null),
            'amount' => number_format($amount, 2, '.', ''),
            'category' => $this->stringOrNull($item['category'] ?? null),
            'transaction_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : $receipt['transaction_date'],
            'note' => $this->stringOrNull($item['note'] ?? null),
            'type' => in_array($type, ['EXPENSE', 'INCOME', 'DEPOSIT'], true) ? $type : 'EXPENSE',
            'confidence' => min(1, max(0, (float) ($item['confidence'] ?? $receipt['confidence']))),
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 1000);
    }
}
