<?php

namespace App\Services;

use App\Models\ProviderConfiguration;
use Illuminate\Support\Facades\DB;

class ProviderConfigurationService
{
    private const SECRET_KEYS = [
        'OCR_OPENAI_API_KEY' => 'ocr_openai_api_key',
        'GOOGLE_APPLICATION_CREDENTIALS' => 'google_application_credentials',
        'SERPER_API_KEY' => 'serper_api_key',
    ];

    public function active(): ?ProviderConfiguration
    {
        return ProviderConfiguration::query()->where('is_active', true)->first();
    }

    public function ocr(): array
    {
        $profile = $this->active();

        return [
            'enabled' => $profile?->ocr_enabled ?? config('services.transaction_ocr.enabled'),
            'driver' => $profile?->ocr_driver ?? config('services.transaction_ocr.driver'),
            'openai_api_key' => $profile?->ocr_openai_api_key ?? config('services.transaction_ocr.openai.api_key'),
            'google_project' => $profile?->google_cloud_project ?? config('services.transaction_ocr.google_vision.project_id'),
            'google_credentials' => $profile?->google_application_credentials ?? config('services.transaction_ocr.google_vision.credentials'),
        ];
    }

    public function offers(): array
    {
        $profile = $this->active();
        $key = $profile?->serper_api_key;

        return [
            'enabled' => $profile?->savings_offers_enabled ?? config('services.offers.enabled'),
            'serper_api_key' => $this->isMaskedSecret($key) ? null : ($key ?? config('services.serper.api_key')),
        ];
    }

    public function replace(array $profiles): array
    {
        return DB::transaction(function () use ($profiles): array {
            ProviderConfiguration::query()->update(['is_active' => false]);

            foreach ($profiles as $profile) {
                $values = $profile['values'] ?? [];
                $id = filter_var($profile['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $record = $id ? ProviderConfiguration::find($id) : null;
                $record ??= new ProviderConfiguration();

                $attributes = [
                    'name' => $profile['name'],
                    'is_active' => (bool) ($profile['isActive'] ?? false),
                    'ocr_driver' => $values['OCR_DRIVER'] ?? 'google_vision',
                    'ocr_enabled' => filter_var($values['OCR_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'google_cloud_project' => $values['GOOGLE_CLOUD_PROJECT'] ?? null,
                    'savings_offers_enabled' => filter_var($values['SAVINGS_OFFERS_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ];

                foreach (self::SECRET_KEYS as $requestKey => $column) {
                    $value = trim((string) ($values[$requestKey] ?? ''));
                    if ($value !== '' && ! $this->isMaskedSecret($value)) {
                        $attributes[$column] = $value;
                    } elseif (! $record->exists) {
                        $attributes[$column] = null;
                    }
                }

                $record->fill($attributes);
                $record->save();
            }

            return $this->publicProfiles();
        });
    }

    public function publicProfiles(): array
    {
        return ProviderConfiguration::query()->orderByDesc('is_active')->get()->map(function (ProviderConfiguration $profile): array {
            return [
                'id' => (string) $profile->id,
                'name' => $profile->name,
                'isActive' => $profile->is_active,
                'values' => [
                    'OCR_DRIVER' => $profile->ocr_driver,
                    'OCR_ENABLED' => $profile->ocr_enabled ? 'true' : 'false',
                    'OCR_OPENAI_API_KEY' => '',
                    'GOOGLE_CLOUD_PROJECT' => $profile->google_cloud_project,
                    'GOOGLE_APPLICATION_CREDENTIALS' => '',
                    'SAVINGS_OFFERS_ENABLED' => $profile->savings_offers_enabled ? 'true' : 'false',
                    'SERPER_API_KEY' => '',
                ],
                'secretConfigured' => [
                    'OCR_OPENAI_API_KEY' => $this->configuredSecret($profile->ocr_openai_api_key),
                    'GOOGLE_APPLICATION_CREDENTIALS' => $this->configuredSecret($profile->google_application_credentials),
                    'SERPER_API_KEY' => $this->configuredSecret($profile->serper_api_key),
                ],
            ];
        })->all();
    }

    private function configuredSecret(?string $value): bool
    {
        return $value !== null && trim($value) !== '' && ! $this->isMaskedSecret($value);
    }

    private function isMaskedSecret(?string $value): bool
    {
        return $value !== null && str_contains($value, '••••');
    }
}
