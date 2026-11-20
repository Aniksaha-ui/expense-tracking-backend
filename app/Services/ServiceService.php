<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ServiceService
{
    public function listServices(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return DB::table('services')
            ->when($search, function ($query, string $value) {
                $query->where(function ($searchQuery) use ($value) {
                    $searchQuery->where('title', 'like', "%{$value}%")
                        ->orWhere('description', 'like', "%{$value}%")
                        ->orWhere('icon', 'like', "%{$value}%")
                        ->orWhere('price', 'like', "%{$value}%");
                });
            })
            ->orderByDesc('status')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function getService(int $serviceId): object
    {
        return $this->findService($serviceId);
    }

    public function createService(array $data): object
    {
        $serviceId = DB::table('services')->insertGetId([
            ...$data,
            'image_url' => $data['image_url'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->findService($serviceId);
    }

    public function updateService(int $serviceId, array $data): object
    {
        $this->findService($serviceId);

        if ($data !== []) {
            DB::table('services')->where('id', $serviceId)->update([
                ...$data,
                'updated_at' => now(),
            ]);
        }

        return $this->findService($serviceId);
    }

    public function deleteService(int $serviceId): void
    {
        if (DB::table('services')->where('id', $serviceId)->delete() === 0) {
            throw new RuntimeException('Service not found.');
        }
    }

    private function findService(int $serviceId): object
    {
        $service = DB::table('services')->where('id', $serviceId)->first();

        if (! $service) {
            throw new RuntimeException('Service not found.');
        }

        return $service;
    }
}
