<?php

namespace App\Services;

use App\Models\Publication;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use RuntimeException;

class PublicationService
{
    public function list(int $perPage, ?string $search): LengthAwarePaginator
    {
        return Publication::query()
            ->when($search, fn ($query) => $query->where(fn ($searchQuery) => $searchQuery
                ->where('title', 'like', "%{$search}%")
                ->orWhere('authors', 'like', "%{$search}%")
                ->orWhere('publisher', 'like', "%{$search}%")
                ->orWhere('doi', 'like', "%{$search}%")))
            ->latest('id')
            ->paginate($perPage);
    }

    public function get(int $publicationId): Publication
    {
        return $this->find($publicationId);
    }

    public function create(array $data): Publication
    {
        return Publication::create($data);
    }

    public function update(int $publicationId, array $data): Publication
    {
        $publication = $this->find($publicationId);
        $publication->update($data);

        return $publication->refresh();
    }

    public function delete(int $publicationId): void
    {
        $this->find($publicationId)->delete();
    }

    private function find(int $publicationId): Publication
    {
        return Publication::find($publicationId) ?? throw new RuntimeException('Publication not found.');
    }
}
