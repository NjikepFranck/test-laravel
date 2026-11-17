<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Http\Middleware\AuthenticateApi;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:60,1', AuthenticateApi::class])->group(function () {
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/{project}/tasks', [TaskController::class, 'index']);
    Route::patch('/tasks/{task}', [TaskController::class, 'update']);
});
