<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CronReportDeliverySettings\UpdateCronReportDeliverySettingsRequest;
use App\Services\CronReportDeliveryService;
use Illuminate\Http\JsonResponse;

class CronReportDeliverySettingController extends Controller
{
    public function __construct(private readonly CronReportDeliveryService $deliveryService) {}
    public function index(): JsonResponse
    {
        return $this->successResponse($this->deliveryService->settingsFor(auth()->user()));
    }
    public function update(UpdateCronReportDeliverySettingsRequest $request): JsonResponse
    {
        return $this->successResponse($this->deliveryService->updateFor(auth()->user(), $request->validated('settings')), 'Cron report delivery settings updated successfully.');
    }
}
