<?php

use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Support\Facades\Route;
use Workbench\App\Http\Controllers\Admin\GenerateAdminSpecController;
use Workbench\App\Http\Controllers\Api\GenerateSpecController;
use Workbench\App\Http\Controllers\Pagination\Invalid\InvalidCursorPaginatorController;
use Workbench\App\Http\Controllers\Pagination\Invalid\InvalidPaginatorController;
use Workbench\App\Http\Controllers\Pagination\Overrides\OverrideCursorUsersController;
use Workbench\App\Http\Controllers\Pagination\Overrides\OverridePaginatedUsersController;

Route::get('api/spec-test', GenerateSpecController::class);
Route::get('api/admin/spec-test', GenerateAdminSpecController::class);
Route::get('api/paginated-users', fn () => []);
Route::get('api/cursor-users', fn () => []);
Route::get('api/conflicting-paginator-one', fn () => []);
Route::get('api/conflicting-paginator-two', fn () => []);
Route::get('api/override-page-users', OverridePaginatedUsersController::class);
Route::get('api/override-cursor-users', OverrideCursorUsersController::class);
Route::get('api/invalid-page-users', InvalidPaginatorController::class);
Route::get('api/invalid-cursor-users', InvalidCursorPaginatorController::class);
Route::prefix('api')->middleware('auth:sanctum')->group(function () {
    Route::match(['GET', 'POST'], 'broadcasting/auth', [BroadcastController::class, 'authenticate']);
    Route::match(['GET', 'POST'], 'broadcasting/user-auth', [BroadcastController::class, 'authenticateUser']);
});

Route::post('api/webhooks/prefixed', fn () => [
    'status' => 'accepted',
])->middleware('auth:sanctum');

Route::post('webhooks', fn () => [
    'status' => 'accepted',
])->middleware('auth:sanctum');

Route::match(['GET', 'POST'], 'webhooks/named', fn () => [
    'status' => 'ok',
])->name('webhooks.named');

Route::domain('one.example.test')->post('webhooks/ambiguous', fn () => [
    'status' => 'ok',
]);

Route::domain('two.example.test')->post('webhooks/ambiguous', fn () => [
    'status' => 'ok',
]);

Route::get('apiary/example', fn () => [
    'status' => 'ok',
]);
