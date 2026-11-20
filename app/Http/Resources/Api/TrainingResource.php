<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TrainingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'provider' => $this->provider,
            'description' => $this->description,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'certificate_url' => $this->certificate_url,
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
