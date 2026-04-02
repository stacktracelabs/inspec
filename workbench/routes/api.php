<?php

use Illuminate\Support\Facades\Route;
use Workbench\App\Http\Controllers\Admin\GenerateAdminSpecController;
use Workbench\App\Http\Controllers\Api\GenerateSpecController;

Route::get('api/spec-test', GenerateSpecController::class);
Route::get('api/admin/spec-test', GenerateAdminSpecController::class);

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
