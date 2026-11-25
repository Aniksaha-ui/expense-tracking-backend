<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\EnsureMenuManager;
use App\Models\User;
use App\Repository\Services\Menu\MenuPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class MenuPermissionsTest extends TestCase
{
    public function test_menu_access_matches_user_or_role_and_denies_other_entries(): void
    {
        $user = new User;
        $user->id = 7;
        $user->role = 'editor';
        $items = collect([
            (object) ['id' => 1, 'user_id' => 7, 'roles' => null],
            (object) ['id' => 2, 'user_id' => null, 'roles' => '["editor"]'],
            (object) ['id' => 3, 'user_id' => 8, 'roles' => '["editor"]'],
            (object) ['id' => 4, 'user_id' => 8, 'roles' => '["admin"]'],
            (object) ['id' => 5, 'user_id' => null, 'roles' => null],
            (object) ['id' => 6, 'user_id' => null, 'roles' => 'invalid'],
        ]);
        $query = Mockery::mock();
        DB::shouldReceive('table')->once()->with('menu_items')->andReturn($query);
        $query->shouldReceive('orderBy')->once()->with('order')->andReturnSelf();
        $query->shouldReceive('orderBy')->once()->with('id')->andReturnSelf();
        $query->shouldReceive('get')->once()->andReturn($items);

        $this->assertSame([1, 2, 3], (new MenuPermissionService)->forUser($user)->pluck('id')->all());
    }

    public function test_menu_management_requires_both_designated_id_and_email(): void
    {
        config(['menu.manager_email' => 'sahaanik1045@gmail.com']);
        $middleware = new EnsureMenuManager;
        foreach ([[1, 'sahaanik1045@gmail.com', 200], [2, 'sahaanik1045@gmail.com', 403], [1, 'other@example.com', 403], [null, null, 403]] as [$id, $email, $status]) {
            $user = $id === null ? null : new User;
            if ($user) {
                $user->id = $id;
                $user->email = $email;
            }
            $request = Request::create('/api/admin/menu_items');
            $request->setUserResolver(fn () => $user);
            $response = $middleware->handle($request, fn () => response('ok'));
            $this->assertSame($status, $response->getStatusCode());
        }
    }

    public function test_menu_manager_email_is_configurable(): void
    {
        config(['menu.manager_email' => 'manager@example.com']);
        $user = new User;
        $user->id = 1;
        $user->email = 'manager@example.com';
        $request = Request::create('/api/admin/menu_items');
        $request->setUserResolver(fn () => $user);
        $this->assertSame(200, (new EnsureMenuManager)->handle($request, fn () => response('ok'))->getStatusCode());

        config(['menu.manager_email' => '']);
        $this->assertSame(403, (new EnsureMenuManager)->handle($request, fn () => response('ok'))->getStatusCode());
    }
}
