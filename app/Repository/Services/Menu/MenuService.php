<?php

namespace App\Repository\Services\Menu;

use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MenuService
{
    public function getAll(int $page = 1, ?string $search = null, int $perPage = 10): array
    {
        try {
            $perPage = max(1, min($perPage, 200));
            $query = $this->baseMenuQuery();

            if ($search !== null && trim($search) !== '') {
                $term = '%'.trim($search).'%';
                $query->where(function ($builder) use ($term) {
                    $builder->where('menu_items.title', 'like', $term)
                        ->orWhere('menu_items.path', 'like', $term)
                        ->orWhere('menu_items.location', 'like', $term)
                        ->orWhere('menu_items.icon', 'like', $term)
                        ->orWhere('users.name', 'like', $term)
                        ->orWhere('users.email', 'like', $term);
                });
            }

            $menuItems = $query
                ->orderBy('menu_items.order', 'asc')
                ->orderBy('menu_items.id', 'desc')
                ->paginate($perPage, ['*'], 'page', max(1, $page));

            $menuItems->getCollection()->transform(fn (object $item) => $this->formatItem($item));

            if ($menuItems->count() > 0) {
                return [
                    'status' => true,
                    'data' => $menuItems,
                    'message' => 'Menu items retrieved successfully',
                ];
            }

            return [
                'status' => true,
                'data' => [],
                'message' => 'No menu items found',
            ];
        } catch (Exception $ex) {
            Log::error('MenuService getAll function error: '.$ex->getMessage());

            return [
                'status' => false,
                'data' => [],
                'message' => 'Error retrieving menu items',
            ];
        }
    }

    public function getUsers(): array
    {
        try {
            $users = DB::table('users')
                ->select('id', 'name', 'email', 'role')
                ->orderBy('name', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            return [
                'status' => true,
                'data' => $users,
                'message' => 'Users retrieved successfully',
            ];
        } catch (Exception $ex) {
            Log::error('MenuService getUsers function error: '.$ex->getMessage());

            return [
                'status' => false,
                'data' => [],
                'message' => 'Error retrieving users',
            ];
        }
    }

    /**
     * Return fixed routes, available users, and known roles for menu management.
     */
    public function getOptions(): array
    {
        try {
            $users = DB::table('users')
                ->select('id', 'name', 'email', 'role')
                ->orderBy('name', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $roleSet = [];
            foreach (config('menu.default_roles', ['admin', 'users']) as $role) {
                if (is_string($role) && trim($role) !== '') {
                    $roleSet[trim($role)] = true;
                }
            }

            foreach ($users as $user) {
                if (is_string($user->role ?? null) && trim($user->role) !== '') {
                    $roleSet[trim($user->role)] = true;
                }
            }

            $existingRoleBlobs = DB::table('menu_items')
                ->whereNotNull('roles')
                ->pluck('roles');

            foreach ($existingRoleBlobs as $blob) {
                foreach ($this->decodeRoles($blob) as $role) {
                    if ($role !== '') {
                        $roleSet[$role] = true;
                    }
                }
            }

            $roles = array_keys($roleSet);
            sort($roles);

            return [
                'status' => true,
                'data' => [
                    'routes' => array_values($this->fixedRoutes()),
                    'users' => $users,
                    'roles' => $roles,
                ],
                'message' => 'Menu options retrieved successfully',
            ];
        } catch (Exception $ex) {
            Log::error('MenuService getOptions function error: '.$ex->getMessage());

            return [
                'status' => false,
                'data' => [],
                'message' => 'Error retrieving menu options',
            ];
        }
    }

    public function create(array $data): array
    {
        try {
            $route = $this->findFixedRoute($data['path'] ?? null);
            if (! $route) {
                return [
                    'status' => false,
                    'data' => [],
                    'message' => 'The selected route is not a valid application menu route',
                    'code' => 422,
                ];
            }

            $menuItem = DB::transaction(function () use ($data, $route) {
                $roles = array_key_exists('roles', $data) && is_array($data['roles'])
                    ? $this->cleanRoles($data['roles'])
                    : [];

                $id = DB::table('menu_items')->insertGetId([
                    'user_id' => $data['user_id'] ?? null,
                    // Menu name is always locked to the fixed route definition.
                    'title' => $route['title'],
                    'path' => $route['path'],
                    'icon' => $data['icon'] ?? $route['icon'],
                    'location' => $data['location'] ?? $route['location'],
                    'parent_id' => $data['parent_id'] ?? null,
                    'order' => (int) ($data['order'] ?? $route['order']),
                    'roles' => $roles !== [] ? json_encode($roles) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $this->findMenuWithUser($id);
            });

            return [
                'status' => true,
                'data' => $menuItem ? $this->formatItem($menuItem) : [],
                'message' => 'Menu item created successfully',
            ];
        } catch (Exception $ex) {
            Log::error('MenuService create function error: '.$ex->getMessage());

            return [
                'status' => false,
                'data' => [],
                'message' => 'Error creating menu item',
            ];
        }
    }

    public function getById(int $id): array
    {
        try {
            $menuItem = $this->findMenuWithUser($id);

            if (! $menuItem) {
                return [
                    'status' => false,
                    'data' => [],
                    'message' => 'Menu item not found',
                ];
            }

            return [
                'status' => true,
                'data' => $this->formatItem($menuItem),
                'message' => 'Menu item retrieved successfully',
            ];
        } catch (Exception $ex) {
            Log::error('MenuService getById function error: '.$ex->getMessage());

            return [
                'status' => false,
                'data' => [],
                'message' => 'Error retrieving menu item',
            ];
        }
    }

    public function update(int $id, array $data): array
    {
        try {
            $existing = DB::table('menu_items')->where('id', $id)->first();

            if (! $existing) {
                return [
                    'status' => false,
                    'data' => [],
                    'message' => 'Menu item not found',
                    'code' => 404,
                ];
            }

            $route = $this->findFixedRoute($data['path'] ?? null);
            if (! $route) {
                return [
                    'status' => false,
                    'data' => [],
                    'message' => 'The selected route is not a valid application menu route',
                    'code' => 422,
                ];
            }

            $parentId = $data['parent_id'] ?? null;
            if ($parentId !== null && $this->createsCycle($id, (int) $parentId)) {
                return [
                    'status' => false,
                    'data' => [],
                    'message' => 'Selected parent menu item creates a circular hierarchy',
                    'code' => 422,
                ];
            }

            $menuItem = DB::transaction(function () use ($id, $data, $route) {
                $updateData = [
                    // Menu name is always locked to the fixed route definition.
                    'title' => $route['title'],
                    'path' => $route['path'],
                    'icon' => $data['icon'] ?? $route['icon'],
                    'location' => $data['location'] ?? $route['location'],
                    'parent_id' => $data['parent_id'] ?? null,
                    'order' => (int) ($data['order'] ?? $route['order']),
                    'updated_at' => now(),
                ];

                if (array_key_exists('user_id', $data)) {
                    $updateData['user_id'] = $data['user_id'];
                }

                if (array_key_exists('roles', $data)) {
                    $roles = is_array($data['roles']) ? $this->cleanRoles($data['roles']) : [];
                    $updateData['roles'] = $roles !== [] ? json_encode($roles) : null;
                }

                DB::table('menu_items')->where('id', $id)->update($updateData);

                return $this->findMenuWithUser($id);
            });

            return [
                'status' => true,
                'data' => $menuItem ? $this->formatItem($menuItem) : [],
                'message' => 'Menu item updated successfully',
            ];
        } catch (Exception $ex) {
            Log::error('MenuService update function error: '.$ex->getMessage());

            return [
                'status' => false,
                'data' => [],
                'message' => 'Error updating menu item',
            ];
        }
    }

    /**
     * Sync direct menu access for a specific user across fixed routes.
     *
     * @param  array<int, string>  $paths
     */
    public function syncUserMenus(int $userId, array $paths): array
    {
        try {
            $user = DB::table('users')
                ->select('id', 'name', 'email', 'role')
                ->where('id', $userId)
                ->first();

            if (! $user) {
                return [
                    'status' => false,
                    'data' => [],
                    'message' => 'User not found',
                    'code' => 404,
                ];
            }

            $selectedPaths = array_values(array_unique($paths));
            $selectedLookup = array_flip($selectedPaths);

            DB::transaction(function () use ($userId, $selectedPaths, $selectedLookup) {
                $userRows = DB::table('menu_items')
                    ->where('user_id', $userId)
                    ->get();

                $alreadyAssigned = [];

                foreach ($userRows as $row) {
                    $path = (string) ($row->path ?? '');
                    if (isset($selectedLookup[$path])) {
                        $alreadyAssigned[$path] = true;
                        $fixed = $this->findFixedRoute($path);
                        if ($fixed && $row->title !== $fixed['title']) {
                            DB::table('menu_items')
                                ->where('id', $row->id)
                                ->update([
                                    'title' => $fixed['title'],
                                    'updated_at' => now(),
                                ]);
                        }

                        continue;
                    }

                    $roles = $this->decodeRoles($row->roles ?? null);
                    if ($roles !== []) {
                        DB::table('menu_items')
                            ->where('id', $row->id)
                            ->update([
                                'user_id' => null,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('menu_items')->where('id', $row->id)->delete();
                    }
                }

                foreach ($selectedPaths as $path) {
                    if (isset($alreadyAssigned[$path])) {
                        continue;
                    }

                    $route = $this->findFixedRoute($path);
                    if (! $route) {
                        continue;
                    }

                    // Reuse an unassigned placeholder row for this route if one exists.
                    $placeholder = DB::table('menu_items')
                        ->where('path', $path)
                        ->whereNull('user_id')
                        ->where(function ($builder) {
                            $builder->whereNull('roles')->orWhere('roles', '[]');
                        })
                        ->first();

                    if ($placeholder) {
                        DB::table('menu_items')
                            ->where('id', $placeholder->id)
                            ->update([
                                'user_id' => $userId,
                                'title' => $route['title'],
                                'icon' => $placeholder->icon ?: $route['icon'],
                                'location' => $placeholder->location ?: $route['location'],
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('menu_items')->insert([
                            'user_id' => $userId,
                            'title' => $route['title'],
                            'path' => $route['path'],
                            'icon' => $route['icon'],
                            'location' => $route['location'],
                            'parent_id' => null,
                            'order' => $route['order'],
                            'roles' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });

            return [
                'status' => true,
                'data' => [
                    'user' => $user,
                    'paths' => $selectedPaths,
                ],
                'message' => 'User menu permissions updated successfully',
            ];
        } catch (Exception $ex) {
            Log::error('MenuService syncUserMenus function error: '.$ex->getMessage());

            return [
                'status' => false,
                'data' => [],
                'message' => 'Error updating user menu permissions',
            ];
        }
    }

    /**
     * Sync role-wide menu access across fixed routes.
     *
     * @param  array<int, string>  $paths
     */
    public function syncRoleMenus(string $role, array $paths): array
    {
        try {
            $normalizedRole = trim($role);
            $selectedPaths = array_values(array_unique($paths));
            $selectedLookup = array_flip($selectedPaths);

            DB::transaction(function () use ($normalizedRole, $selectedPaths, $selectedLookup) {
                $allRows = DB::table('menu_items')->get();
                $pathsWithRole = [];

                foreach ($allRows as $row) {
                    $path = (string) ($row->path ?? '');
                    $roles = $this->decodeRoles($row->roles ?? null);
                    $hasRole = in_array($normalizedRole, $roles, true);

                    if (! $hasRole) {
                        continue;
                    }

                    if (isset($selectedLookup[$path])) {
                        $pathsWithRole[$path] = true;

                        continue;
                    }

                    $remainingRoles = array_values(
                        array_filter($roles, fn (string $item) => $item !== $normalizedRole)
                    );

                    if ($remainingRoles === [] && $row->user_id === null) {
                        DB::table('menu_items')->where('id', $row->id)->delete();
                    } else {
                        DB::table('menu_items')
                            ->where('id', $row->id)
                            ->update([
                                'roles' => $remainingRoles !== [] ? json_encode($remainingRoles) : null,
                                'updated_at' => now(),
                            ]);
                    }
                }

                foreach ($selectedPaths as $path) {
                    if (isset($pathsWithRole[$path])) {
                        continue;
                    }

                    $route = $this->findFixedRoute($path);
                    if (! $route) {
                        continue;
                    }

                    $sharedRow = DB::table('menu_items')
                        ->where('path', $path)
                        ->whereNull('user_id')
                        ->first();

                    if ($sharedRow) {
                        $roles = $this->cleanRoles([
                            ...$this->decodeRoles($sharedRow->roles ?? null),
                            $normalizedRole,
                        ]);

                        DB::table('menu_items')
                            ->where('id', $sharedRow->id)
                            ->update([
                                'title' => $route['title'],
                                'icon' => $sharedRow->icon ?: $route['icon'],
                                'location' => $sharedRow->location ?: $route['location'],
                                'roles' => json_encode($roles),
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('menu_items')->insert([
                            'user_id' => null,
                            'title' => $route['title'],
                            'path' => $route['path'],
                            'icon' => $route['icon'],
                            'location' => $route['location'],
                            'parent_id' => null,
                            'order' => $route['order'],
                            'roles' => json_encode([$normalizedRole]),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });

            return [
                'status' => true,
                'data' => [
                    'role' => $normalizedRole,
                    'paths' => $selectedPaths,
                ],
                'message' => 'Role menu permissions updated successfully',
            ];
        } catch (Exception $ex) {
            Log::error('MenuService syncRoleMenus function error: '.$ex->getMessage());

            return [
                'status' => false,
                'data' => [],
                'message' => 'Error updating role menu permissions',
            ];
        }
    }

    public function delete(int $id): array
    {
        try {
            $deleted = DB::transaction(
                fn () => DB::table('menu_items')->where('id', $id)->delete()
            );

            if ($deleted) {
                return [
                    'status' => true,
                    'data' => [],
                    'message' => 'Menu item deleted successfully',
                ];
            }

            return [
                'status' => false,
                'data' => [],
                'message' => 'Menu item not found or could not be deleted',
            ];
        } catch (Exception $ex) {
            Log::error('MenuService delete function error: '.$ex->getMessage());

            return [
                'status' => false,
                'data' => [],
                'message' => 'Error deleting menu item',
            ];
        }
    }

    /**
     * @return array<int, array{path: string, title: string, location: string, icon: string, order: int}>
     */
    private function fixedRoutes(): array
    {
        return config('menu.fixed_routes', []);
    }

    /**
     * @return array{path: string, title: string, location: string, icon: string, order: int}|null
     */
    private function findFixedRoute(?string $path): ?array
    {
        if ($path === null) {
            return null;
        }

        foreach ($this->fixedRoutes() as $route) {
            if ($route['path'] === $path) {
                return $route;
            }
        }

        return null;
    }

    private function baseMenuQuery(): Builder
    {
        return DB::table('menu_items')
            ->leftJoin('users', 'users.id', '=', 'menu_items.user_id')
            ->select(
                'menu_items.*',
                'users.name as user_name',
                'users.email as user_email',
            );
    }

    private function findMenuWithUser(int $id): ?object
    {
        return $this->baseMenuQuery()
            ->where('menu_items.id', $id)
            ->first();
    }

    private function formatItem(object $item): object
    {
        if (is_string($item->roles ?? null)) {
            $item->roles = $this->decodeRoles($item->roles);
        }

        return $item;
    }

    /**
     * @return array<int, string>
     */
    private function decodeRoles(mixed $roles): array
    {
        if (is_array($roles)) {
            return $this->cleanRoles($roles);
        }

        if (! is_string($roles) || trim($roles) === '') {
            return [];
        }

        $decoded = json_decode($roles, true);

        return is_array($decoded) ? $this->cleanRoles($decoded) : [];
    }

    /**
     * @param  array<int, mixed>  $roles
     * @return array<int, string>
     */
    private function cleanRoles(array $roles): array
    {
        $cleaned = [];
        foreach ($roles as $role) {
            if (is_string($role) && trim($role) !== '') {
                $cleaned[trim($role)] = true;
            }
        }

        return array_keys($cleaned);
    }

    private function createsCycle(int $itemId, int $candidateParentId): bool
    {
        $visited = [$itemId => true];
        $currentParentId = $candidateParentId;

        while ($currentParentId !== null) {
            if (isset($visited[$currentParentId])) {
                return true;
            }

            $visited[$currentParentId] = true;
            $parent = DB::table('menu_items')
                ->where('id', $currentParentId)
                ->value('parent_id');

            $currentParentId = $parent !== null ? (int) $parent : null;
        }

        return false;
    }
}
