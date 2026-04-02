<?php

use Illuminate\Support\Facades\Route;
use Workbench\App\Http\Controllers\Admin\GenerateAdminSpecController;
use Workbench\App\Http\Controllers\Api\GenerateSpecController;

Route::get('api/spec-test', GenerateSpecController::class);
Route::get('api/admin/spec-test', GenerateAdminSpecController::class);
