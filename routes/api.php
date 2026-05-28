<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\RecurringExpenseController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Admin\Menu\MenuController as AdminMenuController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('profile', [AuthController::class, 'profile']);
    });
});

Route::middleware('auth:api')->group(function () {
    Route::apiResource('accounts', AccountController::class)->only(['index', 'store', 'show', 'update']);
    Route::get('categories', [CategoryController::class, 'index']);
    Route::post('categories', [CategoryController::class, 'store']);
    Route::put('categories/{categoryId}', [CategoryController::class, 'update']);
    Route::get('admin/menu_items', [AdminMenuController::class, 'index']);
    Route::post('admin/menu_items', [AdminMenuController::class, 'store']);
    Route::get('admin/menu_items/{id}', [AdminMenuController::class, 'show']);
    Route::post('admin/menu_items/update/{id}', [AdminMenuController::class, 'update']);
    Route::delete('admin/menu_items/{id}', [AdminMenuController::class, 'destroy']);

    Route::prefix('transactions')->group(function () {
        Route::get('/', [TransactionController::class, 'index']);
        Route::post('income', [TransactionController::class, 'storeIncome']);
        Route::post('expense', [TransactionController::class, 'storeExpense']);
        Route::post('deposit', [TransactionController::class, 'storeDeposit']);
        Route::put('{transactionId}', [TransactionController::class, 'update']);
        Route::patch('{transactionId}', [TransactionController::class, 'update']);
    });

    Route::prefix('transfers')->group(function () {
        Route::get('/', [TransferController::class, 'index']);
        Route::post('/', [TransferController::class, 'store']);
        Route::post('withdraw-to-cash', [TransferController::class, 'withdrawToCash']);
        Route::put('{transferId}', [TransferController::class, 'update']);
        Route::patch('{transferId}', [TransferController::class, 'update']);
    });

    Route::prefix('recurring-expenses')->group(function () {
        Route::get('/', [RecurringExpenseController::class, 'index']);
        Route::post('/', [RecurringExpenseController::class, 'store']);
        Route::put('{recurringExpenseId}', [RecurringExpenseController::class, 'update']);
        Route::post('{recurringExpenseId}/run', [RecurringExpenseController::class, 'run']);
        Route::post('run-due', [RecurringExpenseController::class, 'runDue']);
    });

    Route::prefix('reports')->group(function () {
        Route::get('summary', [ReportController::class, 'summary']);
        Route::get('account-balances', [ReportController::class, 'accountBalances']);
        Route::get('category-breakdown', [ReportController::class, 'categoryBreakdown']);
        Route::get('cash-flow', [ReportController::class, 'cashFlow']);
        Route::get('due-recurring', [ReportController::class, 'dueRecurring']);
    });
});
