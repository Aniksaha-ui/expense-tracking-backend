<?php

namespace App\Http\Requests\Api\CronReportDeliverySettings;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateCronReportDeliverySettingsRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['settings' => ['required', 'array'], 'settings.*.job_key' => ['required', 'string', Rule::in(['expense_reports', 'cost_reduction_reports', 'expense_intelligence_reports'])], 'settings.*.is_enabled' => ['required', 'boolean'], 'settings.*.email_enabled' => ['required', 'boolean'], 'settings.*.email_recipients' => ['nullable', 'array'], 'settings.*.email_recipients.*' => ['email:rfc,dns', 'distinct'], 'settings.*.telegram_enabled' => ['required', 'boolean'], 'settings.*.telegram_chat_ids' => ['nullable', 'array'], 'settings.*.telegram_chat_ids.*' => ['string', 'max:100', 'distinct']];
    }
}
