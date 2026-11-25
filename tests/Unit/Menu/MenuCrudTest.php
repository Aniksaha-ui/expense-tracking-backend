<?php

namespace Tests\Unit\Menu;

use App\Http\Controllers\Admin\Menu\MenuController;
use App\Http\Requests\Admin\MenuItemRequest;
use App\Http\Requests\Admin\SyncRoleMenusRequest;
use App\Http\Requests\Admin\SyncUserMenusRequest;
use App\Http\Resources\Admin\MenuItemResource;
use App\Repository\Services\Menu\MenuService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Mockery;
use Tests\TestCase;

class MenuCrudTest extends TestCase
{
    public function test_menu_item_resource_normalizes_json_roles_and_types(): void
    {
        $raw = (object) [
            'id' => '5',
            'user_id' => '1',
            'user_name' => 'Alice',
            'user_email' => 'alice@example.com',
            'title' => 'Menu Items',
            'path' => '/menu-items',
            'icon' => 'ListTree',
            'location' => 'Tools',
            'parent_id' => null,
            'order' => '4',
            'roles' => '["admin","users"]',
            'created_at' => '2026-11-25 12:00:00',
            'updated_at' => '2026-11-25 12:00:00',
        ];

        $formatted = (new MenuItemResource($raw))->toArray(Request::create('/api/admin/menu_items'));

        $this->assertSame(5, $formatted['id']);
        $this->assertSame(1, $formatted['user_id']);
        $this->assertSame('Alice', $formatted['user_name']);
        $this->assertSame('alice@example.com', $formatted['user_email']);
        $this->assertSame(4, $formatted['order']);
        $this->assertSame(['admin', 'users'], $formatted['roles']);
    }

    public function test_menu_controller_returns_standard_api_envelope_for_crud(): void
    {
        $item = (object) [
            'id' => 10,
            'user_id' => null,
            'user_name' => null,
            'user_email' => null,
            'title' => 'Reports',
            'path' => '/reports',
            'icon' => 'BarChart3',
            'location' => 'Tools',
            'parent_id' => null,
            'order' => 17,
            'roles' => ['admin'],
            'created_at' => '2026-11-25 12:00:00',
            'updated_at' => '2026-11-25 12:00:00',
        ];

        $paginator = new LengthAwarePaginator([$item], 1, 10, 1);

        $service = Mockery::mock(MenuService::class);
        $service->shouldReceive('getAll')->once()->with(1, 'Rep', 50)->andReturn([
            'status' => true,
            'data' => $paginator,
            'message' => 'Menu items retrieved successfully',
        ]);
        $service->shouldReceive('create')->once()->andReturn([
            'status' => true,
            'data' => $item,
            'message' => 'Menu item created successfully',
        ]);
        $service->shouldReceive('getById')->once()->with(10)->andReturn([
            'status' => true,
            'data' => $item,
            'message' => 'Menu item retrieved successfully',
        ]);
        $service->shouldReceive('update')->once()->with(10, ['path' => '/reports'])->andReturn([
            'status' => true,
            'data' => $item,
            'message' => 'Menu item updated successfully',
        ]);
        $service->shouldReceive('update')->once()->with(999, ['path' => '/reports'])->andReturn([
            'status' => false,
            'data' => [],
            'message' => 'Menu item not found',
            'code' => 404,
        ]);
        $service->shouldReceive('delete')->once()->with(10)->andReturn([
            'status' => true,
            'data' => [],
            'message' => 'Menu item deleted successfully',
        ]);

        $controller = new MenuController($service);

        $indexResponse = $controller->index(Request::create('/api/admin/menu_items?page=1&search=Rep&per_page=50'));
        $this->assertSame(200, $indexResponse->getStatusCode());
        $this->assertSame('success', $indexResponse->getData(true)['isExecute']);
        $this->assertSame('Reports', $indexResponse->getData(true)['data']['data'][0]['title']);

        $storeRequest = Mockery::mock(MenuItemRequest::class);
        $storeRequest->shouldReceive('validated')->once()->andReturn(['path' => '/reports']);
        $storeResponse = $controller->store($storeRequest);
        $this->assertSame(201, $storeResponse->getStatusCode());
        $this->assertSame('success', $storeResponse->getData(true)['isExecute']);

        $showResponse = $controller->show(Request::create('/api/admin/menu_items/10'), 10);
        $this->assertSame(200, $showResponse->getStatusCode());
        $this->assertSame(10, $showResponse->getData(true)['data']['id']);

        $updateRequest = Mockery::mock(MenuItemRequest::class);
        $updateRequest->shouldReceive('validated')->once()->andReturn(['path' => '/reports']);
        $updateResponse = $controller->update($updateRequest, 10);
        $this->assertSame(200, $updateResponse->getStatusCode());
        $this->assertSame('Reports', $updateResponse->getData(true)['data']['title']);

        $missingUpdateRequest = Mockery::mock(MenuItemRequest::class);
        $missingUpdateRequest->shouldReceive('validated')->once()->andReturn(['path' => '/reports']);
        $missingUpdateResponse = $controller->update($missingUpdateRequest, 999);
        $this->assertSame(404, $missingUpdateResponse->getStatusCode());

        $deleteResponse = $controller->destroy(10);
        $this->assertSame(200, $deleteResponse->getStatusCode());
    }

    public function test_menu_controller_options_and_sync_endpoints(): void
    {
        $service = Mockery::mock(MenuService::class);
        $service->shouldReceive('getOptions')->once()->andReturn([
            'status' => true,
            'data' => [
                'routes' => [['path' => '/account', 'title' => 'Accounts']],
                'users' => [(object) ['id' => 1, 'name' => 'Alice']],
                'roles' => ['admin', 'users'],
            ],
            'message' => 'Menu options retrieved successfully',
        ]);
        $service->shouldReceive('syncUserMenus')->once()->with(1, ['/account', '/reports'])->andReturn([
            'status' => true,
            'data' => ['user' => (object) ['id' => 1], 'paths' => ['/account', '/reports']],
            'message' => 'User menu permissions updated successfully',
        ]);
        $service->shouldReceive('syncRoleMenus')->once()->with('users', ['/account'])->andReturn([
            'status' => true,
            'data' => ['role' => 'users', 'paths' => ['/account']],
            'message' => 'Role menu permissions updated successfully',
        ]);

        $controller = new MenuController($service);

        $optionsResponse = $controller->options();
        $this->assertSame(200, $optionsResponse->getStatusCode());
        $this->assertSame('/account', $optionsResponse->getData(true)['data']['routes'][0]['path']);

        $userSyncRequest = Mockery::mock(SyncUserMenusRequest::class);
        $userSyncRequest->shouldReceive('validated')->once()->with('paths')->andReturn(['/account', '/reports']);
        $userSyncResponse = $controller->syncUserMenus($userSyncRequest, 1);
        $this->assertSame(200, $userSyncResponse->getStatusCode());
        $this->assertSame(['/account', '/reports'], $userSyncResponse->getData(true)['data']['paths']);

        $roleSyncRequest = Mockery::mock(SyncRoleMenusRequest::class);
        $roleSyncRequest->shouldReceive('validated')->once()->with('role')->andReturn('users');
        $roleSyncRequest->shouldReceive('validated')->once()->with('paths')->andReturn(['/account']);
        $roleSyncResponse = $controller->syncRoleMenus($roleSyncRequest);
        $this->assertSame(200, $roleSyncResponse->getStatusCode());
        $this->assertSame('users', $roleSyncResponse->getData(true)['data']['role']);
    }

    public function test_menu_item_request_enforces_fixed_routes_and_rejects_custom_paths(): void
    {
        $request = new MenuItemRequest;
        $rules = $request->rules();
        unset($rules['parent_id'], $rules['user_id']);

        $invalid = Validator::make(['path' => '/not-a-real-route', 'order' => -2], $rules);
        $this->assertTrue($invalid->fails());
        $this->assertArrayHasKey('path', $invalid->errors()->toArray());
        $this->assertArrayHasKey('order', $invalid->errors()->toArray());

        $valid = Validator::make(['path' => '/account', 'order' => 0], $rules);
        $this->assertFalse($valid->fails());
        $this->assertArrayNotHasKey('title', $rules);
    }

    public function test_sync_requests_validate_fixed_route_paths(): void
    {
        $userRules = (new SyncUserMenusRequest)->rules();
        $roleRules = (new SyncRoleMenusRequest)->rules();

        $this->assertTrue(Validator::make(['paths' => ['/fake-route']], $userRules)->fails());
        $this->assertFalse(Validator::make(['paths' => ['/account', '/reports']], $userRules)->passes() === false);

        $this->assertTrue(Validator::make(['role' => '', 'paths' => ['/account']], $roleRules)->fails());
        $this->assertTrue(Validator::make(['role' => 'admin', 'paths' => ['/fake']], $roleRules)->fails());
        $this->assertTrue(Validator::make(['role' => 'admin', 'paths' => ['/account']], $roleRules)->passes());
    }
}
