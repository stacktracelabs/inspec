<?php

use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Support\Facades\Route;
use Workbench\App\Http\Controllers\Admin\GenerateAdminSpecController;
use Workbench\App\Http\Controllers\Api\GenerateSpecController;

Route::get('api/spec-test', GenerateSpecController::class);
Route::get('api/admin/spec-test', GenerateAdminSpecController::class);
Route::get('api/paginated-users', fn () => []);
Route::get('api/cursor-users', fn () => []);
Route::get('api/conflicting-paginator-one', fn () => []);
Route::get('api/conflicting-paginator-two', fn () => []);
Route::get('api/error-responses/plain', fn () => [
    'status' => 'ok',
]);
Route::post('api/error-responses/request', fn () => [
    'status' => 'accepted',
]);
Route::get('api/error-responses/throttled', fn () => [
    'status' => 'ok',
])->middleware('throttle:api');
Route::post('api/error-responses/throttled-request', fn () => [
    'status' => 'accepted',
])->middleware('throttle:api');
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

Route::get('posts', fn () => []);
Route::post('posts', fn () => []);
