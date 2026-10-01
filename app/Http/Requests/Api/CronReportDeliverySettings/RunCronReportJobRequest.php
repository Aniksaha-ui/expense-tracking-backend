<?php
namespace App\Http\Requests\Api\CronReportDeliverySettings;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
class RunCronReportJobRequest extends ApiFormRequest { public function rules(): array { return ['from_date' => ['required', 'date_format:Y-m-d'], 'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'], 'frequency' => ['nullable', Rule::in(['daily', 'weekly', 'bi-weekly', 'monthly'])]]; } }
