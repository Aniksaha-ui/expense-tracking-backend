<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'abstract' => $this->abstract,
            'authors' => $this->authors,
            'publication_date' => $this->publication_date,
            'publisher' => $this->publisher,
            'doi' => $this->doi,
            'url' => $this->url,
            'pdf_url' => $this->pdf_url,
            'status' => (int) $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
