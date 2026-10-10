<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfferLocationController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return $this->successResponse($this->location($request));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $request->user()->update([
            'offers_latitude' => $data['latitude'],
            'offers_longitude' => $data['longitude'],
        ]);

        return $this->successResponse($this->location($request), 'Offer search location saved.');
    }

    private function location(Request $request): array
    {
        $user = $request->user();

        return [
            'latitude' => $user->offers_latitude === null ? null : (float) $user->offers_latitude,
            'longitude' => $user->offers_longitude === null ? null : (float) $user->offers_longitude,
            'radius_km' => (float) config('services.offers.radius_km', 2),
        ];
    }
}
