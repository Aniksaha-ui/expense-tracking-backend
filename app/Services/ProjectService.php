<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProjectService
{
    public function list(int $perPage, ?string $search): LengthAwarePaginator
    {
        return DB::table('projects')->when($search, fn ($q) => $q->where(fn ($s) => $s->where('project_name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")->orWhere('frontend_tech', 'like', "%{$search}%")))->orderByDesc('isPublished')->orderByDesc('id')->paginate($perPage);
    }

    public function get(int $id): object
    {
        return $this->find($id);
    }

    public function create(array $data): object
    {
        $id = DB::table('projects')->insertGetId([...$data, 'created_at' => now(), 'updated_at' => now()]);

        return $this->find($id);
    }

    public function update(int $id, array $data): object
    {
        $this->find($id);
        DB::table('projects')->where('id', $id)->update([...$data, 'updated_at' => now()]);

        return $this->find($id);
    }

    public function delete(int $id): void
    {
        if (! DB::table('projects')->where('id', $id)->delete()) {
            throw new RuntimeException('Project not found.');
        }
    }

    private function find(int $id): object
    {
        $item = DB::table('projects')->where('id', $id)->first();
        if (! $item) {
            throw new RuntimeException('Project not found.');
        }

return $item;
    }
}
