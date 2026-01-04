# Quick Start Guide

Get started with Laravel Spectrum in 5 minutes.

## 🚀 30-Second Start

```bash
# 1. Install
composer require wadakatu/laravel-spectrum --dev

# 2. Generate documentation
php artisan spectrum:generate

# 3. Start preview
php artisan spectrum:watch
```

Your browser will automatically open, showing API documentation at `http://localhost:8080`!

## 📋 Step-by-Step Guide

### Step 1: Installation

```bash
composer require wadakatu/laravel-spectrum --dev
```

### Step 2: Generate Your First Documentation

```bash
php artisan spectrum:generate
```

This command performs:
- ✅ Detects all API routes
- ✅ Automatically extracts parameters from FormRequests
- ✅ Generates response schemas from API Resources
- ✅ Saves documentation to `storage/app/spectrum/openapi.json`

### Step 3: View Documentation

#### Option 1: Real-time Preview (Recommended)

```bash
php artisan spectrum:watch
```

- Browser opens automatically
- Detects file changes and auto-refreshes
- Perfect for development

#### Option 2: Embed in Blade View

```html
@extends('layouts.app')

@section('content')
<div id="swagger-ui"></div>

<script src="https://unpkg.com/swagger-ui-dist/swagger-ui-bundle.js"></script>
<link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist/swagger-ui.css">

<script>
window.onload = function() {
    SwaggerUIBundle({
        url: "{{ asset('storage/app/spectrum/openapi.json') }}",
        dom_id: '#swagger-ui',
        deepLinking: true,
        presets: [
            SwaggerUIBundle.presets.apis,
        ],
    });
};
</script>
@endsection
```

### Step 4: Launch Mock Server (Optional)

```bash
php artisan spectrum:mock
```

- Mock API starts at `http://localhost:8081`
- Perfect for frontend development
- Test without actual backend

## 🎯 Basic Example

### Simple Controller

```php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;

class PostController extends Controller
{
    public function index()
    {
        return PostResource::collection(Post::paginate());
    }

    public function store(StorePostRequest $request)
    {
        $post = Post::create($request->validated());
        return new PostResource($post);
    }
}
```

### FormRequest

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'status' => 'required|in:draft,published',
            'tags' => 'array',
            'tags.*' => 'string',
        ];
    }
}
```

### API Resource

```php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'status' => $this->status,
            'tags' => $this->tags,
            'author' => new UserResource($this->whenLoaded('author')),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
```

### Route Definition

```php
// routes/api.php
Route::apiResource('posts', PostController::class);
```

**That's all it takes to generate complete API documentation!**

## ⚡ Common Commands

```bash
# Basic generation
php artisan spectrum:generate

# Regenerate without cache
php artisan spectrum:generate --no-cache

# Specific route patterns only
php artisan spectrum:generate --pattern="api/v2/*"

# Real-time preview
php artisan spectrum:watch

# Launch mock server
php artisan spectrum:mock

# Export to Postman
php artisan spectrum:export:postman

# Clear cache
php artisan spectrum:cache clear
```

## 💡 Pro Tips

### 1. Publish Configuration File

```bash
php artisan vendor:publish --provider="LaravelSpectrum\SpectrumServiceProvider"
```

### 2. Configure Custom Tags

```php
// config/spectrum.php
'tags' => [
    'api/auth/*' => 'Authentication',
    'api/users/*' => 'User Management',
    'api/posts/*' => 'Blog Posts',
],
```

### 3. Integrate with CI/CD

```yaml
# .github/workflows/docs.yml
- name: Generate API Docs
  run: |
    composer install
    php artisan spectrum:generate
    
- name: Upload Docs
  uses: actions/upload-artifact@v3
  with:
    name: api-docs
    path: storage/app/spectrum/
```

## 🔍 Troubleshooting

### Routes Not Showing

```php
// config/spectrum.php
'route_patterns' => [
    'api/*',     // Make sure this matches your routes
    'api/v1/*',
],
```

### Validation Not Detected

Ensure FormRequest is properly type-hinted:

```php
// ✅ Correct
public function store(StorePostRequest $request)

// ❌ Wrong
public function store(Request $request)
```

## 📚 Next Steps

1. [Configuration Options](./config-reference.md) - Detailed customization
2. [Features](./features.md) - Explore all features
3. [Export Features](./export.md) - Export to Postman or Insomnia
4. [Mock Server](./mock-server.md) - Using the mock API

---

**🎉 Congratulations!** You're ready to start using Laravel Spectrum.