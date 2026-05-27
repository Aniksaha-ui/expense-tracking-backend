<?php

namespace App\Http\Controllers\Admin\Menu;

use App\Http\Controllers\Controller;
use App\Repository\Services\Menu\MenuService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MenuController extends Controller
{
    private MenuService $menuService;

    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
    }

    public function index(Request $request)
    {
        try {
            $page = $request->query('page', 1);
            $search = $request->query('search');

            $response = $this->menuService->getAll($page, $search);

            return $this->serviceResponse($response);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());

            return $this->failedResponse('An error occurred while fetching menu items');
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:191',
                'path' => 'nullable|string|max:191',
                'icon' => 'nullable|string|max:191',
                'location' => 'nullable|string|max:191',
                'parent_id' => 'nullable|exists:menu_items,id',
                'order' => 'integer',
                'roles' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return $this->failedResponse('Validation Error', 422, [
                    'errors' => $validator->errors(),
                ]);
            }

            $response = $this->menuService->create($request->all());

            return $this->serviceResponse($response, $this->serviceStatusCode($response, 201));
        } catch (Exception $ex) {
            Log::error($ex->getMessage());

            return $this->failedResponse('An error occurred while creating menu item');
        }
    }

    public function show($id)
    {
        try {
            $response = $this->menuService->getById($id);

            return $this->serviceResponse($response, $this->serviceStatusCode($response, 200, 404));
        } catch (Exception $ex) {
            Log::error($ex->getMessage());

            return $this->failedResponse('An error occurred while fetching menu item');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:191',
                'path' => 'nullable|string|max:191',
                'icon' => 'nullable|string|max:191',
                'location' => 'nullable|string|max:191',
                'parent_id' => 'nullable|exists:menu_items,id',
                'order' => 'integer',
                'roles' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return $this->failedResponse('Validation Error', 422, [
                    'errors' => $validator->errors(),
                ]);
            }

            $response = $this->menuService->update($id, $request->all());

            return $this->serviceResponse($response, $this->serviceStatusCode($response, 200, 404));
        } catch (Exception $ex) {
            Log::error($ex->getMessage());

            return $this->failedResponse('An error occurred while updating menu item');
        }
    }

    public function destroy($id)
    {
        try {
            $response = $this->menuService->delete($id);

            return $this->serviceResponse($response, $this->serviceStatusCode($response, 200, 404));
        } catch (Exception $ex) {
            Log::error($ex->getMessage());

            return $this->failedResponse('An error occurred while deleting menu item');
        }
    }

    protected function apiResponse(string $executionStatus, $data = [], string $message = 'success', int $statusCode = 200)
    {
        return response()->json([
            'isExecture' => $executionStatus,
            'data' => $data ?? [],
            'message' => $message,
        ], $statusCode);
    }

    protected function normalizeExecutionStatus($status): string
    {
        return $status ? 'success' : 'failed';
    }

    protected function serviceResponse(array $response, int $statusCode = 200)
    {
        return $this->apiResponse(
            $this->normalizeExecutionStatus($response['status'] ?? $response['isExecute'] ?? false),
            $response['data'] ?? [],
            $response['message'] ?? 'success',
            $statusCode
        );
    }

    protected function serviceStatusCode(array $response, int $successCode = 200, int $failedCode = 422): int
    {
        return $this->normalizeExecutionStatus($response['status'] ?? $response['isExecute'] ?? false) === 'success'
            ? $successCode
            : $failedCode;
    }

    protected function failedResponse(string $message = 'Internal Server Error', int $statusCode = 500, $data = [])
    {
        return $this->apiResponse('failed', $data, $message, $statusCode);
    }
}
