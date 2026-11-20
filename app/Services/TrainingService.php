<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class TrainingService
{
    public function listTrainings(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return DB::table('trainings')
            ->when($search, function ($query, string $value) {
                $query->where(function ($searchQuery) use ($value) {
                    $searchQuery->where('title', 'like', "%{$value}%")
                        ->orWhere('provider', 'like', "%{$value}%")
                        ->orWhere('description', 'like', "%{$value}%");
                });
            })
            ->orderByDesc('status')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function getTraining(int $trainingId): object
    {
        return $this->findTraining($trainingId);
    }

    public function createTraining(array $data): object
    {
        $data = $this->storeUploadedImage($data);

        $trainingId = DB::table('trainings')->insertGetId([
            ...$data,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->findTraining($trainingId);
    }

    public function updateTraining(int $trainingId, array $data): object
    {
        $training = $this->findTraining($trainingId);
        $data = $this->storeUploadedImage($data, $training->image_url);

        if ($data !== []) {
            DB::table('trainings')->where('id', $trainingId)->update([
                ...$data,
                'updated_at' => now(),
            ]);
        }

        return $this->findTraining($trainingId);
    }

    public function deleteTraining(int $trainingId): void
    {
        $training = $this->findTraining($trainingId);

        if (DB::table('trainings')->where('id', $trainingId)->delete() === 0) {
            throw new RuntimeException('Training not found.');
        }

        $this->deleteStoredImage($training->image_url);
    }

    private function storeUploadedImage(array $data, ?string $previousImage = null): array
    {
        $image = $data['image'] ?? null;
        unset($data['image']);

        if (! $image instanceof UploadedFile) {
            return $data;
        }

        $data['image_url'] = $image->store('trainings/images', 'public');
        $this->deleteStoredImage($previousImage);

        return $data;
    }

    private function deleteStoredImage(?string $image): void
    {
        if ($image && str_starts_with($image, 'trainings/images/')) {
            Storage::disk('public')->delete($image);
        }
    }

    private function findTraining(int $trainingId): object
    {
        $training = DB::table('trainings')->where('id', $trainingId)->first();

        if (! $training) {
            throw new RuntimeException('Training not found.');
        }

        return $training;
    }
}
