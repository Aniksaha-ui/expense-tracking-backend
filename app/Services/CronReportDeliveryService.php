<?php

namespace App\Services;

use App\Models\CronReportDeliverySetting;
use App\Models\User;
use Illuminate\Mail\Mailable;

class CronReportDeliveryService
{
    public const JOBS = ['expense_reports' => ['label' => 'Category expense report', 'command' => 'expense-reports:email'], 'cost_reduction_reports' => ['label' => 'Cost reduction report', 'command' => 'cost-reduction-reports:email'], 'expense_intelligence_reports' => ['label' => 'Expense intelligence report', 'command' => 'expense-intelligence-reports:email']];
    public function settingsFor(User $user): array
    {
        $stored = CronReportDeliverySetting::query()->where('user_id', $user->id)->get()->keyBy('job_key');
        return collect(self::JOBS)->map(function (array $job, string $jobKey) use ($stored, $user): array {
            $setting = $stored->get($jobKey);
            return ['job_key' => $jobKey, 'label' => $job['label'], 'command' => $job['command'], 'is_enabled' => $setting?->is_enabled ?? true, 'email_enabled' => $setting?->email_enabled ?? true, 'email_recipients' => $setting?->email_recipients ?? [$user->email], 'telegram_enabled' => $setting?->telegram_enabled ?? false, 'telegram_chat_ids' => $setting?->telegram_chat_ids ?? []];
        })->values()->all();
    }
    public function updateFor(User $user, array $settings): array
    {
        foreach ($settings as $setting) {
            CronReportDeliverySetting::query()->updateOrCreate(['user_id' => $user->id, 'job_key' => $setting['job_key']], ['is_enabled' => $setting['is_enabled'], 'email_enabled' => $setting['email_enabled'], 'email_recipients' => $this->cleanList($setting['email_recipients'] ?? []), 'telegram_enabled' => $setting['telegram_enabled'], 'telegram_chat_ids' => $this->cleanList($setting['telegram_chat_ids'] ?? [])]);
        }
        return $this->settingsFor($user);
    }
    public function deliver(User $user, string $jobKey, Mailable $mail, string $telegramMessage): int
    {
        $setting = CronReportDeliverySetting::query()->where('user_id', $user->id)->where('job_key', $jobKey)->first();
        if ($setting && ! $setting->is_enabled) return 0;
        $deliveries = 0;
        $recipients = $setting?->email_recipients ?: [config("{$jobKey}.recipient") ?: $user->email];
        if (($setting?->email_enabled ?? true) && $recipients) {
            app(NotificationService::class)->sendEmail($recipients, $mail, config("{$jobKey}.cc"), config("{$jobKey}.bcc"));
            $deliveries++;
        }
        if ($setting?->telegram_enabled) {
            foreach ($setting->telegram_chat_ids ?: [] as $chatId) {
                app(TelegramService::class)->sendMessageTo((string) $chatId, $telegramMessage);
                $deliveries++;
            }
        }
        return $deliveries;
    }
    private function cleanList(array $items): array
    {
        return array_values(array_unique(array_filter(array_map(fn($item) => trim((string) $item), $items))));
    }
}
