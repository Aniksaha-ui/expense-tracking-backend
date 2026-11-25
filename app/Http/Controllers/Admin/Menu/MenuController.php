<?php

namespace App\Http\Controllers\Admin\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MenuItemRequest;
use App\Http\Requests\Admin\SyncRoleMenusRequest;
use App\Http\Requests\Admin\SyncUserMenusRequest;
use App\Http\Resources\Admin\MenuItemResource;
use App\Repository\Services\Menu\MenuService;
use App\Support\ApiResponse;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MenuController extends Controller
{
    public function __construct(private readonly MenuService $menuService) {}

    public function index(Request $request): JsonResponse
    {
        return $this->handle(
            fn () => $this->menuService->getAll(
                (int) $request->query('page', 1),
                $request->query('search'),
                (int) $request->query('per_page', 10),
            ),
            'An error occurred while fetching menu items',
            200,
            422,
            function (mixed $data) use ($request) {
                if ($data instanceof LengthAwarePaginator) {
                    $data->setCollection(
                        $data->getCollection()->map(
                            fn (object $item) => (new MenuItemResource($item))->toArray($request)
                        )
                    );
                }

                return $data;
            }
        );
    }

    public function users(): JsonResponse
    {
        return $this->handle(
            fn () => $this->menuService->getUsers(),
            'An error occurred while fetching users'
        );
    }

    public function options(): JsonResponse
    {
        return $this->handle(
            fn () => $this->menuService->getOptions(),
            'An error occurred while fetching menu options'
        );
    }

    public function syncUserMenus(SyncUserMenusRequest $request, int $userId): JsonResponse
    {
        return $this->handle(
            fn () => $this->menuService->syncUserMenus($userId, $request->validated('paths')),
            'An error occurred while updating user menu permissions',
            200,
            422
        );
    }

    public function syncRoleMenus(SyncRoleMenusRequest $request): JsonResponse
    {
        return $this->handle(
            fn () => $this->menuService->syncRoleMenus(
                $request->validated('role'),
                $request->validated('paths')
            ),
            'An error occurred while updating role menu permissions',
            200,
            422
        );
    }

    public function store(MenuItemRequest $request): JsonResponse
    {
        return $this->handle(
            fn () => $this->menuService->create($request->validated()),
            'An error occurred while creating menu item',
            201,
            422,
            fn (mixed $data) => is_object($data) ? (new MenuItemResource($data))->toArray($request) : $data
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->handle(
            fn () => $this->menuService->getById($id),
            'An error occurred while fetching menu item',
            200,
            404,
            fn (mixed $data) => is_object($data) ? (new MenuItemResource($data))->toArray($request) : $data
        );
    }

    public function update(MenuItemRequest $request, int $id): JsonResponse
    {
        return $this->handle(
            fn () => $this->menuService->update($id, $request->validated()),
            'An error occurred while updating menu item',
            200,
            404,
            fn (mixed $data) => is_object($data) ? (new MenuItemResource($data))->toArray($request) : $data
        );
    }

    public function destroy(int $id): JsonResponse
    {
        return $this->handle(
            fn () => $this->menuService->delete($id),
            'An error occurred while deleting menu item',
            200,
            404
        );
    }

    /**
     * Run a service call and convert its result into the standard API response.
     *
     * @param  callable(): array{status: bool, data: mixed, message: string, code?: int}  $action
     * @param  (callable(mixed): mixed)|null  $transform
     */
    private function handle(
        callable $action,
        string $errorMessage,
        int $successCode = 200,
        int $failedCode = 422,
        ?callable $transform = null
    ): JsonResponse {
        try {
            $result = $action();

            if (! $result['status']) {
                return ApiResponse::error(
                    $result['message'],
                    $result['data'] ?? [],
                    (int) ($result['code'] ?? $failedCode)
                );
            }

            $payload = $transform ? $transform($result['data']) : $result['data'];

            return ApiResponse::success($payload, $result['message'], $successCode);
        } catch (Exception $exception) {
            Log::error($errorMessage.': '.$exception->getMessage());

            return ApiResponse::error($errorMessage, [], 500);
        }
    }
}
