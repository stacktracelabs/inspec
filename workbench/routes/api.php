<?php

use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Support\Facades\Route;
use Workbench\App\Http\Controllers\Admin\GenerateAdminSpecController;
use Workbench\App\Http\Controllers\Api\GenerateSpecController;

Route::get('api/spec-test', GenerateSpecController::class);
Route::get('api/admin/spec-test', GenerateAdminSpecController::class);
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
