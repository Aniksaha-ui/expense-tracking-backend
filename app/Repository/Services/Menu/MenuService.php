<?php

namespace App\Repository\Services\Menu;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MenuService
{
    public function getAll($page, $search)
    {
        try {
            $perPage = 10;
            $query = DB::table('menu_items');

            if ($search) {
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhere('path', 'like', '%'.$search.'%')
                    ->orWhere('location', 'like', '%'.$search.'%');
            }

            $menuItems = $query->orderBy('order', 'asc')
                ->orderBy('id', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            $menuItems->getCollection()->transform(function ($item) {
                if ($item->roles) {
                    $item->roles = json_decode($item->roles);
                }

                return $item;
            });

            if ($menuItems->count() > 0) {
                return ['status' => true, 'data' => $menuItems, 'message' => 'Menu items retrieved successfully'];
            }

            return ['status' => true, 'data' => [], 'message' => 'No menu items found'];
        } catch (Exception $ex) {
            Log::error('MenuService getAll function error: '.$ex->getMessage());

            return ['status' => false, 'data' => [], 'message' => 'Error retrieving menu items'];
        }
    }

    public function create($data)
    {
        try {
            $id = DB::table('menu_items')->insertGetId([
                'title' => $data['title'],
                'path' => $data['path'] ?? null,
                'icon' => $data['icon'] ?? null,
                'location' => $data['location'] ?? 'main',
                'parent_id' => $data['parent_id'] ?? null,
                'order' => $data['order'] ?? 0,
                'roles' => isset($data['roles']) ? json_encode($data['roles']) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $menuItem = DB::table('menu_items')->where('id', $id)->first();

            return ['status' => true, 'data' => $menuItem, 'message' => 'Menu item created successfully'];
        } catch (Exception $ex) {
            Log::error('MenuService create function error: '.$ex->getMessage());

            return ['status' => false, 'data' => [], 'message' => 'Error creating menu item'];
        }
    }

    public function getById($id)
    {
        try {
            $menuItem = DB::table('menu_items')->where('id', $id)->first();

            if ($menuItem) {
                if ($menuItem->roles) {
                    $menuItem->roles = json_decode($menuItem->roles);
                }

                return ['status' => true, 'data' => $menuItem, 'message' => 'Menu item retrieved successfully'];
            }

            return ['status' => false, 'data' => [], 'message' => 'Menu item not found'];
        } catch (Exception $ex) {
            Log::error('MenuService getById function error: '.$ex->getMessage());

            return ['status' => false, 'data' => [], 'message' => 'Error retrieving menu item'];
        }
    }

    public function update($id, $data)
    {
        try {
            $updateData = [
                'title' => $data['title'],
                'path' => $data['path'] ?? null,
                'icon' => $data['icon'] ?? null,
                'location' => $data['location'] ?? 'main',
                'parent_id' => $data['parent_id'] ?? null,
                'order' => $data['order'] ?? 0,
                'updated_at' => now(),
            ];

            if (isset($data['roles'])) {
                $updateData['roles'] = json_encode($data['roles']);
            }

            DB::table('menu_items')->where('id', $id)->update($updateData);

            $menuItem = DB::table('menu_items')->where('id', $id)->first();

            return ['status' => true, 'data' => $menuItem, 'message' => 'Menu item updated successfully'];
        } catch (Exception $ex) {
            Log::error('MenuService update function error: '.$ex->getMessage());

            return ['status' => false, 'data' => [], 'message' => 'Error updating menu item'];
        }
    }

    public function delete($id)
    {
        try {
            $deleted = DB::table('menu_items')->where('id', $id)->delete();

            if ($deleted) {
                return ['status' => true, 'data' => [], 'message' => 'Menu item deleted successfully'];
            }

            return ['status' => false, 'data' => [], 'message' => 'Menu item not found or could not be deleted'];
        } catch (Exception $ex) {
            Log::error('MenuService delete function error: '.$ex->getMessage());

            return ['status' => false, 'data' => [], 'message' => 'Error deleting menu item'];
        }
    }
}
