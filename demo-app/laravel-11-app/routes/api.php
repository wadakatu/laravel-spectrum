<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Test routes for Laravel Spectrum
use App\Http\Controllers\Api\TestController;

Route::prefix('v1')->group(function () {
    Route::get('/test', [TestController::class, 'index']);
    Route::post('/users', [TestController::class, 'store']);
});
