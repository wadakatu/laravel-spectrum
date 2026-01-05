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

// ============================================
// Comprehensive OpenAPI Generation Tests
// ============================================

use App\Http\Controllers\Api\V2\UserController as V2UserController;
use App\Http\Controllers\ComprehensiveTestController;
use App\Http\Controllers\InvokableController;

// 1. Nested Resources
Route::prefix('nested')->group(function () {
    Route::get('/users/{user}/posts', [ComprehensiveTestController::class, 'userPosts']);
    Route::get('/users/{user}/posts/{post}', [ComprehensiveTestController::class, 'showUserPost']);
    Route::get('/users/{userId}/posts/{postId}/comments', [ComprehensiveTestController::class, 'userPostComments']);
});

// 2. Custom Route Model Binding Keys
Route::get('/users/uuid/{uuid}', [ComprehensiveTestController::class, 'findByUuid'])
    ->where('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
Route::get('/users/slug/{slug}', [ComprehensiveTestController::class, 'findBySlug'])
    ->where('slug', '[a-z0-9-]+');

// 3. Multiple Response Status Codes
Route::post('/resources', [ComprehensiveTestController::class, 'createWithStatus']);
Route::delete('/resources/{id}', [ComprehensiveTestController::class, 'deleteResource']);
Route::post('/async/process', [ComprehensiveTestController::class, 'asyncProcess']);

// 4. Complex Array/Nested Validation
Route::post('/bulk/users', [ComprehensiveTestController::class, 'nestedArrayValidation']);
Route::post('/matrix', [ComprehensiveTestController::class, 'matrixValidation']);

// 5. UUID and Special Parameters
Route::get('/items/{uuid}', [ComprehensiveTestController::class, 'getByUuid'])
    ->where('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
Route::get('/reports/date-range', [ComprehensiveTestController::class, 'dateRangeQuery']);
Route::get('/events/datetime', [ComprehensiveTestController::class, 'datetimeQuery']);

// 6. Optional Parameters
Route::get('/search/advanced', [ComprehensiveTestController::class, 'optionalParams']);

// 7. Binary/File Responses
Route::get('/files/{id}/download', [ComprehensiveTestController::class, 'downloadFile']);
Route::get('/files/{id}/stream', [ComprehensiveTestController::class, 'streamFile']);
Route::get('/images/{filename}', [ComprehensiveTestController::class, 'getImage']);

// 8. Different Content Types
Route::get('/export/xml', [ComprehensiveTestController::class, 'xmlResponse']);
Route::get('/export/text', [ComprehensiveTestController::class, 'textResponse']);

// 9. Conditional/Polymorphic Returns
Route::get('/mixed/resource', [ComprehensiveTestController::class, 'conditionalReturn']);
Route::get('/mixed/items', [ComprehensiveTestController::class, 'flexibleReturn']);

// 10. Custom Headers
Route::get('/with-headers', [ComprehensiveTestController::class, 'withCustomHeaders']);
Route::get('/cacheable/{id}', [ComprehensiveTestController::class, 'cacheableResponse']);

// 11. Deprecated Endpoints
Route::get('/v1/legacy', [ComprehensiveTestController::class, 'deprecatedEndpoint']);

// 12. Array Query Parameters
Route::get('/filter/array', [ComprehensiveTestController::class, 'arrayQueryParams']);
Route::get('/filter/boolean', [ComprehensiveTestController::class, 'booleanParams']);

// 13. Numeric Constraints
Route::post('/orders', [ComprehensiveTestController::class, 'numericConstraints']);

// 14. String Formats
Route::post('/validate/formats', [ComprehensiveTestController::class, 'stringFormats']);

// 15. Conditional Required Fields
Route::post('/payments', [ComprehensiveTestController::class, 'conditionalRequired']);

// 16. Mutually Exclusive Fields
Route::post('/lookup', [ComprehensiveTestController::class, 'mutuallyExclusive']);

// 17. Invokable Controller
Route::post('/invoke', InvokableController::class);

// 18. API Versioning (v2)
Route::prefix('v2')->group(function () {
    Route::get('/users', [V2UserController::class, 'index']);
    Route::get('/users/{user}', [V2UserController::class, 'show']);
    Route::post('/users/bulk', [V2UserController::class, 'bulkCreate']);
    Route::patch('/users/bulk', [V2UserController::class, 'bulkUpdate']);
    Route::delete('/users/bulk', [V2UserController::class, 'bulkDelete']);
});

// ============================================
// OSS Pattern Tests (from real Laravel projects)
// ============================================

use App\Http\Controllers\ConditionalResourceController;
use App\Http\Controllers\OssPatternController;

Route::prefix('oss')->group(function () {
    // JSON API style filtering (Spatie Query Builder pattern)
    Route::get('/filter', [OssPatternController::class, 'jsonApiFilter']);
    Route::get('/sparse-fields', [OssPatternController::class, 'sparseFieldsets']);

    // Service class patterns
    Route::post('/service/users', [OssPatternController::class, 'createViaService']);
    Route::put('/service/users/{id}', [OssPatternController::class, 'updateViaService']);

    // Cursor pagination
    Route::get('/cursor-paginate', [OssPatternController::class, 'cursorPaginated']);

    // Polymorphic patterns
    Route::post('/comments', [OssPatternController::class, 'polymorphicComments']);

    // Batch operations
    Route::delete('/batch', [OssPatternController::class, 'batchDelete']);
    Route::patch('/batch', [OssPatternController::class, 'batchUpdate']);

    // Multi-tenancy
    Route::get('/tenants/{tenant}/resources', [OssPatternController::class, 'tenantResource']);

    // Advanced search
    Route::get('/search', [OssPatternController::class, 'advancedSearch']);

    // Webhook receiver
    Route::post('/webhooks', [OssPatternController::class, 'receiveWebhook']);

    // Rate limit info
    Route::get('/rate-limited', [OssPatternController::class, 'withRateLimitInfo']);

    // GraphQL-like selection
    Route::get('/select', [OssPatternController::class, 'selectFields']);

    // Idempotency pattern (Stripe-style)
    Route::post('/idempotent', [OssPatternController::class, 'idempotentCreate']);

    // Soft delete patterns
    Route::get('/trashed', [OssPatternController::class, 'listWithTrashed']);
    Route::post('/restore/{id}', [OssPatternController::class, 'restore']);

    // Timezone-aware queries
    Route::get('/timezone-query', [OssPatternController::class, 'timezoneQuery']);
});

// Conditional Resource patterns (whenLoaded, when, etc.)
Route::prefix('conditional-resource')->group(function () {
    Route::get('/users', [ConditionalResourceController::class, 'index']);
    Route::get('/users/{id}', [ConditionalResourceController::class, 'show']);
});

// ============================================
// Advanced Validation Patterns
// ============================================

use App\Http\Controllers\AdvancedUserController;

Route::prefix('advanced')->group(function () {
    // Complex FormRequest with Rule objects, Password rules, Enum rules
    Route::post('/users', [AdvancedUserController::class, 'store']);
    Route::put('/users/{id}', [AdvancedUserController::class, 'update']);
});

// ============================================
// Custom Validation Rules
// ============================================

use App\Http\Controllers\CustomRuleController;
use App\Http\Controllers\ModernValidationController;

Route::prefix('custom-rules')->group(function () {
    // Custom Rule class implementing ValidationRule
    Route::post('/register', [CustomRuleController::class, 'register']);
});

// Modern Laravel validation patterns (Laravel 9+)
Route::prefix('modern')->group(function () {
    Route::post('/content', [ModernValidationController::class, 'store']);
});

// Sometimes and conditional validation patterns
use App\Http\Controllers\SometimesController;

Route::prefix('conditional-validation')->group(function () {
    Route::post('/order', [SometimesController::class, 'process']);
});

// ============================================
// Fractal Transformer Tests
// ============================================

use App\Http\Controllers\FractalController;

Route::prefix('fractal')->name('api.fractal.')->group(function () {
    // User endpoints with Fractal transformation
    Route::get('/users', [FractalController::class, 'index'])->name('users.index');
    Route::get('/users/{id}', [FractalController::class, 'show'])->name('users.show');
    Route::get('/users/{userId}/posts', [FractalController::class, 'userPosts'])->name('users.posts');

    // Post endpoints with Fractal transformation
    Route::get('/posts', [FractalController::class, 'posts'])->name('posts.index');
    Route::get('/posts/{id}', [FractalController::class, 'showPost'])->name('posts.show');
});
