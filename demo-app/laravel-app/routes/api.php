<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\PaginationTestController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/register', [AuthController::class, 'register']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('users', UserController::class);
    Route::apiResource('posts', PostController::class);
    Route::post('users/search/test', [UserController::class, 'search']);
    Route::get('profile', [UserController::class, 'profile']);
});

// Pagination test routes
Route::prefix('pagination-test')->group(function () {
    Route::get('/', [PaginationTestController::class, 'index']);
    Route::get('/with-resource', [PaginationTestController::class, 'withResource']);
    Route::get('/simple', [PaginationTestController::class, 'simplePagination']);
    Route::get('/cursor', [PaginationTestController::class, 'cursorPagination']);
    Route::get('/query-builder', [PaginationTestController::class, 'withQueryBuilder']);
});

// Product routes - Query parameter detection test
Route::prefix('products')->group(function () {
    Route::get('/search', [ProductController::class, 'search']);
    Route::get('/filter', [ProductController::class, 'filter']);
});
