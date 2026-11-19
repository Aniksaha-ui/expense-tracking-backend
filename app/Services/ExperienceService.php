<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExperienceService
{
    public function listExperiences(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return DB::table('expriences')
            ->when($search, function ($query, string $value) {
                $query->where(function ($searchQuery) use ($value) {
                    $searchQuery->where('job_title', 'like', "%{$value}%")
                        ->orWhere('company', 'like', "%{$value}%")
                        ->orWhere('location', 'like', "%{$value}%")
                        ->orWhere('description', 'like', "%{$value}%");
                });
            })
            ->orderByDesc('is_current')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function getExperience(int $experienceId): object
    {
        return $this->findExperience($experienceId);
    }

    public function createExperience(array $data): object
    {
        $experienceId = DB::table('expriences')->insertGetId([
            ...$data,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->findExperience($experienceId);
    }

    public function updateExperience(int $experienceId, array $data): object
    {
        $this->findExperience($experienceId);

        if ($data !== []) {
            DB::table('expriences')->where('id', $experienceId)->update([
                ...$data,
                'updated_at' => now(),
            ]);
        }

        return $this->findExperience($experienceId);
    }

    public function deleteExperience(int $experienceId): void
    {
        if (DB::table('expriences')->where('id', $experienceId)->delete() === 0) {
            throw new RuntimeException('Experience not found.');
        }
    }

    private function findExperience(int $experienceId): object
    {
        $experience = DB::table('expriences')->where('id', $experienceId)->first();

        if (! $experience) {
            throw new RuntimeException('Experience not found.');
        }

        return $experience;
    }
}
