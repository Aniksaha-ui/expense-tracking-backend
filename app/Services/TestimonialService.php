<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
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
        $this->findTestimonial($testimonialId);

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
        if (DB::table('testimonials')->where('id', $testimonialId)->delete() === 0) {
            throw new RuntimeException('Testimonial not found.');
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
