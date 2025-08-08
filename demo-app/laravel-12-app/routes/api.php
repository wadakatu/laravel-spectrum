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
    Route::get('users/{id}/detailed', [UserController::class, 'detailed']);
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

// Product resource routes for testing Faker integration
Route::get('/products', [\App\Http\Controllers\Api\ProductController::class, 'index']);
Route::get('/products/{id}', [\App\Http\Controllers\Api\ProductController::class, 'show']);
Route::get('/match-test', [\App\Http\Controllers\TestMatchController::class, 'matchTest']);

// File upload routes - File upload detection test
use App\Http\Controllers\FileUploadController;

Route::prefix('uploads')->group(function () {
    Route::post('/profile', [FileUploadController::class, 'upload']);
    Route::post('/images', [FileUploadController::class, 'uploadImages']);
    Route::post('/gallery', [FileUploadController::class, 'uploadGallery']);
    Route::post('/documents', [FileUploadController::class, 'uploadDocuments']);
});

// Request validate routes - request()->validate() pattern test
use App\Http\Controllers\RequestValidateController;

Route::prefix('request-validate')->group(function () {
    Route::post('/blog/posts', [RequestValidateController::class, 'store']);
    Route::post('/blog/articles', [RequestValidateController::class, 'storeWithRequestVariable']);
    Route::post('/user/upload', [RequestValidateController::class, 'upload']);
    Route::put('/user/profile/{id}', [RequestValidateController::class, 'update']);
    Route::post('/settings', [RequestValidateController::class, 'testDifferentVariableNames']);
});

// Task routes with Enum parameters
Route::get('/tasks/{status}', [\App\Http\Controllers\TaskController::class, 'index']);
Route::post('/tasks/{status}/{priority}', [\App\Http\Controllers\TaskController::class, 'store']);
Route::patch('/tasks/{id}', [\App\Http\Controllers\TaskController::class, 'update']);

// Conditional validation test routes
use App\Http\Controllers\ConditionalUserController;

Route::prefix('conditional')->group(function () {
    Route::post('/users', [ConditionalUserController::class, 'store']);
    Route::put('/users/{user}', [ConditionalUserController::class, 'update']);
    Route::patch('/users/{user}', [ConditionalUserController::class, 'update']);
});

// Test error handling
Route::post('/broken-endpoint', [\App\Http\Controllers\BrokenController::class, 'brokenEndpoint']);
Route::get('/broken-resource', [\App\Http\Controllers\BrokenController::class, 'brokenResource']);

// Response detection test routes
Route::prefix('test-response')->group(function () {
    Route::get('/json', [App\Http\Controllers\TestResponseController::class, 'responseJson']);
    Route::get('/array', [App\Http\Controllers\TestResponseController::class, 'arrayReturn']);
    Route::get('/model/{id}', [App\Http\Controllers\TestResponseController::class, 'modelReturn']);
    Route::get('/collection-map', [App\Http\Controllers\TestResponseController::class, 'collectionMap']);
});

// Anonymous FormRequest test routes
use App\Http\Controllers\AnonymousFormRequestController;

Route::prefix('anonymous-form-request')->group(function () {
    Route::post('/blog', [AnonymousFormRequestController::class, 'store']);
    Route::put('/profile/{id}', [AnonymousFormRequestController::class, 'updateProfile']);
    Route::post('/product', [AnonymousFormRequestController::class, 'createProduct']);
    Route::post('/register', [AnonymousFormRequestController::class, 'register']);
});
