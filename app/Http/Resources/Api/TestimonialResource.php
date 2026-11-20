<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TestimonialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'designation' => $this->designation,
            'feedback' => $this->feedback,
            'rating' => $this->rating,
            'image_url' => $this->publicImageUrl($this->image_url),
            'status' => (int) $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function publicImageUrl(?string $image): ?string
    {
        if (! $image || preg_match('/^(https?:)?\/\//', $image)) {
            return $image;
        }

        return Storage::disk('public')->url($image);
    }
}
