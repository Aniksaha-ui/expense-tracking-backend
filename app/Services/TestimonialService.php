<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class TestimonialService
{
    public function listTestimonials(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return DB::table('testimonials')
            ->when($search, function ($query, string $value) {
                $query->where(function ($searchQuery) use ($value) {
                    $searchQuery->where('name', 'like', "%{$value}%")
                        ->orWhere('designation', 'like', "%{$value}%")
                        ->orWhere('feedback', 'like', "%{$value}%")
                        ->orWhere('rating', 'like', "%{$value}%");
                });
            })
            ->orderByDesc('status')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function getTestimonial(int $testimonialId): object
    {
        return $this->findTestimonial($testimonialId);
    }

    public function createTestimonial(array $data): object
    {
        $data = $this->storeUploadedImage($data);

        $testimonialId = DB::table('testimonials')->insertGetId([
            ...$data,
            'image_url' => $data['image_url'] ?? null,
            'status' => $data['status'] ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->findTestimonial($testimonialId);
    }

    public function updateTestimonial(int $testimonialId, array $data): object
    {
        $testimonial = $this->findTestimonial($testimonialId);
        $data = $this->storeUploadedImage($data, $testimonial->image_url);

        if ($data !== []) {
            DB::table('testimonials')->where('id', $testimonialId)->update([
                ...$data,
                'updated_at' => now(),
            ]);
        }

        return $this->findTestimonial($testimonialId);
    }

    public function deleteTestimonial(int $testimonialId): void
    {
        $testimonial = $this->findTestimonial($testimonialId);

        if (DB::table('testimonials')->where('id', $testimonialId)->delete() === 0) {
            throw new RuntimeException('Testimonial not found.');
        }

        $this->deleteStoredImage($testimonial->image_url);
    }

    private function storeUploadedImage(array $data, ?string $previousImage = null): array
    {
        $image = $data['image'] ?? null;
        unset($data['image']);

        if (! $image instanceof UploadedFile) {
            return $data;
        }

        $data['image_url'] = $image->store('testimonials/images', 'public');
        $this->deleteStoredImage($previousImage);

        return $data;
    }

    private function deleteStoredImage(?string $image): void
    {
        if ($image && str_starts_with($image, 'testimonials/images/')) {
            Storage::disk('public')->delete($image);
        }
    }

    private function findTestimonial(int $testimonialId): object
    {
        $testimonial = DB::table('testimonials')->where('id', $testimonialId)->first();

        if (! $testimonial) {
            throw new RuntimeException('Testimonial not found.');
        }

        return $testimonial;
    }
}
