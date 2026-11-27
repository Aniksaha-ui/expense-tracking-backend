<?php

namespace App\Repository\Services\Menu;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class MenuPermissionService
{
    public function forUser(User $user)
    {
        $role = $user->role;

        return DB::table('menu_items')->orderBy('order')->orderBy('id')->get()
            ->filter(function ($item) use ($user, $role) {
                $roles = json_decode($item->roles ?? '[]', true);

                return (int) $item->user_id === (int) $user->id
                    || (is_string($role) && $role !== '' && is_array($roles) && in_array($role, $roles, true));
            })->values();
    }
}
