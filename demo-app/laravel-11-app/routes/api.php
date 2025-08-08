<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\BrokenController;
use App\Http\Controllers\ConditionalUserController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\PaginationTestController;
use App\Http\Controllers\RequestValidateController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TestMatchController;
use App\Http\Controllers\TestResponseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Original test routes
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public authentication routes
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

// Product resource routes for testing
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/match-test', [TestMatchController::class, 'matchTest']);

// File upload routes - File upload detection test
Route::prefix('uploads')->group(function () {
    Route::post('/profile', [FileUploadController::class, 'upload']);
    Route::post('/images', [FileUploadController::class, 'uploadImages']);
    Route::post('/gallery', [FileUploadController::class, 'uploadGallery']);
    Route::post('/documents', [FileUploadController::class, 'uploadDocuments']);
});

// Request validate routes - request()->validate() pattern test
Route::prefix('request-validate')->group(function () {
    Route::post('/blog/posts', [RequestValidateController::class, 'store']);
    Route::post('/blog/articles', [RequestValidateController::class, 'storeWithRequestVariable']);
    Route::post('/user/upload', [RequestValidateController::class, 'upload']);
    Route::put('/user/profile/{id}', [RequestValidateController::class, 'update']);
    Route::post('/settings', [RequestValidateController::class, 'testDifferentVariableNames']);
});

// Task routes
Route::get('/tasks/{status}', [TaskController::class, 'index']);
Route::post('/tasks/{status}/{priority}', [TaskController::class, 'store']);
Route::patch('/tasks/{id}', [TaskController::class, 'update']);

// Conditional validation test routes
Route::prefix('conditional')->group(function () {
    Route::post('/users', [ConditionalUserController::class, 'store']);
    Route::put('/users/{user}', [ConditionalUserController::class, 'update']);
    Route::patch('/users/{user}', [ConditionalUserController::class, 'update']);
});

// Test error handling
Route::post('/broken-endpoint', [BrokenController::class, 'brokenEndpoint']);
Route::get('/broken-resource', [BrokenController::class, 'brokenResource']);

// Response detection test routes
Route::prefix('test-response')->group(function () {
    Route::get('/json', [TestResponseController::class, 'responseJson']);
    Route::get('/array', [TestResponseController::class, 'arrayReturn']);
    Route::get('/model/{id}', [TestResponseController::class, 'modelReturn']);
    Route::get('/collection-map', [TestResponseController::class, 'collectionMap']);
});