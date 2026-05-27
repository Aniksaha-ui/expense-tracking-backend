<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\CategoryService;
use Illuminate\Database\Seeder;

class DefaultCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categoryService = app(CategoryService::class);

        User::query()->each(function (User $user) use ($categoryService) {
            $categoryService->seedDefaultCategoriesForUser($user->id);
        });
    }
}
