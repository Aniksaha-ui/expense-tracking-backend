<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CategoryService extends BaseFinanceService
{
    private const DEFAULT_CATEGORIES = [
        ['name' => 'Food', 'type' => 'EXPENSE'],
        ['name' => 'Transport', 'type' => 'EXPENSE'],
        ['name' => 'Utilities', 'type' => 'EXPENSE'],
        ['name' => 'Shopping', 'type' => 'EXPENSE'],
        ['name' => 'Health', 'type' => 'EXPENSE'],
        ['name' => 'Entertainment', 'type' => 'EXPENSE'],
        ['name' => 'Rent', 'type' => 'EXPENSE'],
        ['name' => 'Salary', 'type' => 'INCOME'],
        ['name' => 'Freelance', 'type' => 'INCOME'],
        ['name' => 'Other Income', 'type' => 'INCOME'],
    ];

    public function listCategories(int $userId): \Illuminate\Support\Collection
    {
        return DB::table('categories')
            ->where('user_id', $userId)
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }

    public function storeCategory(array $data, int $userId): object
    {
        $slug = Str::slug($data['name']);
        $type = strtoupper($data['type']);

        $exists = DB::table('categories')
            ->where('user_id', $userId)
            ->where('slug', $slug)
            ->where('type', $type)
            ->exists();

        if ($exists) {
            throw new RuntimeException('Category already exists.');
        }

        $categoryId = DB::table('categories')->insertGetId([
            'user_id' => $userId,
            'name' => $data['name'],
            'slug' => $slug,
            'type' => $type,
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->fetchCategoryRecord($userId, $categoryId);
    }

    public function updateCategory(array $data, int $userId, int $categoryId): object
    {
        $existing = $this->getOwnedCategory($userId, $categoryId);
        $name = $data['name'] ?? $existing->name;
        $type = strtoupper($data['type'] ?? $existing->type);
        $slug = Str::slug($name);

        $duplicate = DB::table('categories')
            ->where('user_id', $userId)
            ->where('slug', $slug)
            ->where('type', $type)
            ->where('id', '!=', $categoryId)
            ->exists();

        if ($duplicate) {
            throw new RuntimeException('Category already exists.');
        }

        DB::table('categories')
            ->where('user_id', $userId)
            ->where('id', $categoryId)
            ->update([
                'name' => $name,
                'slug' => $slug,
                'type' => $type,
                'updated_at' => now(),
            ]);

        return $this->fetchCategoryRecord($userId, $categoryId);
    }

    public function seedDefaultCategoriesForUser(int $userId): void
    {
        foreach (self::DEFAULT_CATEGORIES as $category) {
            DB::table('categories')->updateOrInsert(
                [
                    'user_id' => $userId,
                    'slug' => Str::slug($category['name']),
                    'type' => $category['type'],
                ],
                [
                    'name' => $category['name'],
                    'is_default' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
