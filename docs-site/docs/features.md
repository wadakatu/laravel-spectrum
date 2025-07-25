# Features Overview

Laravel Spectrum provides a detailed explanation of all features.

## 📝 Request Analysis

### FormRequest Validation

Automatically detects validation rules from FormRequest classes and converts them to OpenAPI schemas.

```php
class UpdateUserRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $this->user->id,
            'age' => 'nullable|integer|min:0|max:150',
            'role' => 'required|in:admin,editor,viewer',
            'tags' => 'array',
            'tags.*' => 'string|distinct',
            'profile' => 'required|array',
            'profile.bio' => 'nullable|string|max:1000',
            'profile.avatar' => 'nullable|file|image|max:2048',
        ];
    }
}
```

Generated schema:
- `name`: optional, string, maximum 255 characters
- `email`: optional, email format
- `age`: nullable, integer, range 0-150
- `role`: required, enum ['admin', 'editor', 'viewer']
- `tags`: array, each element is a string
- `profile.avatar`: file upload, image format, maximum 2MB

### Inline Validation

Also detects `validate()` methods within controllers:

```php
public function update(Request $request, $id)
{
    $validated = $request->validate([
        'status' => 'required|in:draft,published,archived',
        'published_at' => 'required_if:status,published|date',
    ]);
}
```

### Conditional Validation

Dynamic validation based on HTTP methods or other conditions:

```php
public function rules()
{
    $rules = [
        'title' => 'required|string|max:255',
    ];

    if ($this->isMethod('POST')) {
        $rules['slug'] = 'required|unique:posts';
    } elseif ($this->isMethod('PUT')) {
        $rules['slug'] = 'required|unique:posts,slug,' . $this->route('post');
    }

    return $rules;
}
```

## 📦 Response Analysis

### API Resources

Automatically analyzes Laravel API Resource structures:

```php
class PostResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->when($request->user(), $this->content),
            'author' => new UserResource($this->whenLoaded('author')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'meta' => [
                'views' => $this->views_count,
                'likes' => $this->likes_count,
            ],
            $this->mergeWhen($request->user()?->isAdmin(), [
                'admin_notes' => $this->admin_notes,
            ]),
        ];
    }
}
```

Detected elements:
- Basic fields (id, title, slug)
- Conditional fields (`when()`, `mergeWhen()`)
- Nested resources
- Relations (`whenLoaded()`)
- Metadata structures

### Fractal Transformers

Also supports League/Fractal usage:

```php
class BookTransformer extends TransformerAbstract
{
    protected array $availableIncludes = ['author', 'publisher'];
    protected array $defaultIncludes = ['genre'];

    public function transform(Book $book)
    {
        return [
            'id' => (int) $book->id,
            'title' => $book->title,
            'isbn' => $book->isbn,
            'published_year' => (int) $book->published_year,
        ];
    }

    public function includeAuthor(Book $book)
    {
        return $this->item($book->author, new AuthorTransformer);
    }
}
```

### Pagination

Automatically detects pagination responses:

```php
// Controller
public function index()
{
    return PostResource::collection(
        Post::with('author')->paginate(15)
    );
}
```

Generated schema includes:
- `data`: array of items
- `links`: pagination links
- `meta`: page information (current_page, total, per_page, etc.)

## 🔍 Query Parameter Detection

Automatically detects from `$request->input()` or `$request->query()` within controller methods:

```php
public function index(Request $request)
{
    $query = Post::query();
    
    // Search
    if ($search = $request->input('search')) {
        $query->where('title', 'like', "%{$search}%");
    }
    
    // Filtering
    if ($status = $request->query('status')) {
        $query->where('status', $status);
    }
    
    // Sorting
    $sortBy = $request->input('sort_by', 'created_at');
    $sortOrder = $request->input('sort_order', 'desc');
    $query->orderBy($sortBy, $sortOrder);
    
    // Pagination
    $perPage = $request->input('per_page', 15);
    
    return PostResource::collection($query->paginate($perPage));
}
```

Detected parameters:
- `search`: string, optional
- `status`: string, optional
- `sort_by`: string, default 'created_at'
- `sort_order`: string, default 'desc'
- `per_page`: integer, default 15

## 📤 File Upload

Automatic detection of file uploads:

```php
public function rules()
{
    return [
        'document' => 'required|file|mimes:pdf,doc,docx|max:10240',
        'images' => 'required|array|max:5',
        'images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
    ];
}
```

Documented with appropriate `multipart/form-data` encoding.

## 🔐 Authentication and Security

### Middleware Detection

```php
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/api/profile', [ProfileController::class, 'show']);
});

Route::middleware('auth:api')->group(function () {
    Route::apiResource('posts', PostController::class);
});
```

### Custom Middleware

```php
// config/spectrum.php
'authentication' => [
    'middleware_map' => [
        'auth:sanctum' => 'bearer',
        'auth:api' => 'bearer',
        'api-key' => 'apiKey',
    ],
],
```

## 🎨 Example Data Generation

### Dynamic Examples with Faker

```php
// config/spectrum.php
'example_generation' => [
    'use_faker' => true,
    'custom_generators' => [
        'email' => fn($faker) => $faker->safeEmail(),
        'avatar_url' => fn($faker) => $faker->imageUrl(200, 200, 'people'),
        'price' => fn($faker) => $faker->randomFloat(2, 10, 1000),
        'status' => fn($faker) => $faker->randomElement(['active', 'pending', 'inactive']),
    ],
],
```

### Integration with Model Factories

Automatically uses model factories when defined:

```php
class UserFactory extends Factory
{
    public function definition()
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'role' => fake()->randomElement(['admin', 'user', 'guest']),
        ];
    }
}
```

## 🏷️ Tags and Grouping

### Automatic Tag Generation

Automatically generated from controller names or route prefixes:

```
/api/v1/users/* → "Users"
/api/v1/posts/* → "Posts"
/api/admin/* → "Admin"
```

### Custom Tag Mapping

```php
// config/spectrum.php
'tags' => [
    'api/auth/login' => 'Authentication',
    'api/auth/register' => 'Authentication',
    'api/auth/logout' => 'Authentication',
    'api/users/*' => 'User Management',
    'api/admin/*' => 'Administration',
],
```

## ⚡ Performance Features

### Incremental Generation

Re-analyzes only changed files:

```bash
php artisan spectrum:generate --incremental
```

### Parallel Processing

Speed up with multi-core processing:

```bash
php artisan spectrum:generate:optimized --workers=8
```

### Smart Caching

- Route definition caching
- FormRequest analysis result caching
- API Resource structure caching
- Dependency tracking

## 🔄 Real-time Features

### Hot Reload

```bash
php artisan spectrum:watch
```

- Automatic file change detection
- Browser refresh via WebSocket
- Fast differential updates
- Multiple browser synchronization

### Watch Targets

- `app/Http/Controllers/**`
- `app/Http/Requests/**`
- `app/Http/Resources/**`
- `routes/**`
- `config/spectrum.php`

## 🎭 Mock Server Features

### Automatic Mock API Generation

Launch a fully functional mock API server from OpenAPI documentation:

```bash
php artisan spectrum:mock
```

### Key Features

- **Dynamic Response Generation**: Automatically generated based on OpenAPI schema
- **Authentication Simulation**: Supports Bearer, API Key, Basic auth
- **Validation**: Automatic request validation
- **Scenario-based**: Multiple scenarios like success/error
- **Delay Simulation**: Network delay reproduction

### Usage Examples

```bash
# Basic launch
php artisan spectrum:mock

# Custom port and response delay
php artisan spectrum:mock --port=3000 --delay=200

# Test with error scenario
curl http://localhost:8081/api/users?_scenario=error
```

For details, see the [Mock Server Guide](./mock-server.md).

## 📚 Next Steps

- [Performance Optimization](./performance.md) - Configuration for large projects
- [Export Features](./export.md) - Export to Postman or Insomnia
- [Customization](./customization.md) - Advanced customization methods