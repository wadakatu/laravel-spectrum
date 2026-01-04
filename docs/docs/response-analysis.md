# Response Analysis Guide

Laravel Spectrum automatically analyzes and documents API resources, Fractal transformers, pagination, and various response patterns.

## 📦 API Resources

### Basic API Resource

```php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'avatar_url' => $this->avatar_url,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
```

Generated OpenAPI schema:

```json
{
  "type": "object",
  "properties": {
    "id": { "type": "integer" },
    "name": { "type": "string" },
    "email": { "type": "string", "format": "email" },
    "role": { "type": "string" },
    "avatar_url": { "type": "string", "format": "uri" },
    "created_at": { "type": "string", "format": "date-time" },
    "updated_at": { "type": "string", "format": "date-time" }
  }
}
```

### Nested Resources

```php
class PostResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'author' => new UserResource($this->author),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
            'tags' => TagResource::collection($this->tags),
            'meta' => [
                'views_count' => $this->views_count,
                'likes_count' => $this->likes_count,
                'is_featured' => $this->is_featured,
            ],
        ];
    }
}
```

### Conditional Attributes

```php
class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            
            // Show only to authenticated users
            'phone' => $this->when($request->user(), $this->phone),
            
            // Show only to admins
            'internal_notes' => $this->when(
                $request->user()?->isAdmin(),
                $this->internal_notes
            ),
            
            // Conditionally merge
            $this->mergeWhen($request->user()?->id === $this->id, [
                'private_settings' => $this->private_settings,
                'notification_preferences' => $this->notification_preferences,
            ]),
            
            // Only when relation is loaded
            'posts' => PostResource::collection($this->whenLoaded('posts')),
            'posts_count' => $this->whenCounted('posts'),
        ];
    }
}
```

### Metadata and Wrapping

```php
class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }

    public function with($request)
    {
        return [
            'meta' => [
                'version' => '1.0',
                'api_version' => config('app.api_version'),
                'generated_at' => now()->toISOString(),
            ],
        ];
    }

    // Custom wrapping
    public static $wrap = 'user';
}
```

## 🔄 Collection Resources

### Basic Collection

```php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class UserCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total_active' => $this->collection->where('is_active', true)->count(),
                'total_inactive' => $this->collection->where('is_active', false)->count(),
            ],
        ];
    }

    public function with($request)
    {
        return [
            'links' => [
                'self' => route('users.index'),
            ],
            'meta' => [
                'generated_at' => now()->toISOString(),
            ],
        ];
    }
}
```

### Paginated Collection

```php
// Controller
public function index(Request $request)
{
    $users = User::query()
        ->when($request->search, function ($query, $search) {
            $query->where('name', 'like', "%{$search}%");
        })
        ->paginate($request->input('per_page', 15));

    return UserResource::collection($users);
}
```

Automatically detected pagination structure:

```json
{
  "data": [...],
  "links": {
    "first": "http://api.example.com/users?page=1",
    "last": "http://api.example.com/users?page=10",
    "prev": null,
    "next": "http://api.example.com/users?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "path": "http://api.example.com/users",
    "per_page": 15,
    "to": 15,
    "total": 150
  }
}
```

## 🦴 Fractal Transformers

### Basic Transformer

```php
namespace App\Transformers;

use App\Models\Product;
use League\Fractal\TransformerAbstract;

class ProductTransformer extends TransformerAbstract
{
    protected array $availableIncludes = ['category', 'reviews'];
    protected array $defaultIncludes = ['brand'];

    public function transform(Product $product)
    {
        return [
            'id' => (int) $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => [
                'amount' => (float) $product->price,
                'currency' => $product->currency,
                'formatted' => $product->formatted_price,
            ],
            'in_stock' => (bool) $product->in_stock,
            'created_at' => $product->created_at->toIso8601String(),
        ];
    }

    public function includeCategory(Product $product)
    {
        return $this->item($product->category, new CategoryTransformer);
    }

    public function includeReviews(Product $product)
    {
        return $this->collection($product->reviews, new ReviewTransformer);
    }

    public function includeBrand(Product $product)
    {
        return $this->item($product->brand, new BrandTransformer);
    }
}
```

### Using Fractal Manager

```php
use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;

class ProductController extends Controller
{
    private Manager $fractal;

    public function __construct(Manager $fractal)
    {
        $this->fractal = $fractal;
        $this->fractal->setSerializer(new \League\Fractal\Serializer\ArraySerializer());
    }

    public function index(Request $request)
    {
        $paginator = Product::paginate(20);
        $products = new Collection($paginator->items(), new ProductTransformer);
        $products->setPaginator(new IlluminatePaginatorAdapter($paginator));

        if ($request->has('include')) {
            $this->fractal->parseIncludes($request->include);
        }

        return $this->fractal->createData($products)->toArray();
    }

    public function show($id)
    {
        $product = Product::findOrFail($id);
        $resource = new Item($product, new ProductTransformer);

        return $this->fractal->createData($resource)->toArray();
    }
}
```

## 📊 Complex Response Patterns

### Polymorphic Relations

```php
class ActivityResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'description' => $this->description,
            'subject' => $this->whenMorphLoaded('subject', function () {
                return match ($this->subject_type) {
                    Post::class => new PostResource($this->subject),
                    Comment::class => new CommentResource($this->subject),
                    User::class => new UserResource($this->subject),
                    default => null,
                };
            }),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
```

### Recursive Structures

```php
class CategoryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'parent_id' => $this->parent_id,
            'children' => CategoryResource::collection($this->whenLoaded('children')),
            'products_count' => $this->whenCounted('products'),
        ];
    }
}
```

### Custom Response Formats

```php
class ApiResponse
{
    public static function success($data, string $message = '', int $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ], $code);
    }

    public static function error(string $message, array $errors = [], int $code = 400)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => now()->toISOString(),
        ], $code);
    }

    public static function paginated($paginator, $resource)
    {
        return response()->json([
            'success' => true,
            'data' => $resource::collection($paginator),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'timestamp' => now()->toISOString(),
        ]);
    }
}
```

## 🎯 Type Inference and Schema Generation

### Type Inference from Model Attributes

Laravel Spectrum infers types from:

1. **Database Schema**
    - Migration files
    - Model's `$casts` property

2. **Model Casts**
   ```php
   protected $casts = [
       'is_active' => 'boolean',
       'metadata' => 'array',
       'published_at' => 'datetime',
       'price' => 'decimal:2',
       'settings' => 'json',
   ];
   ```

3. **Accessors and Mutators**
   ```php
   // Attribute casting
   protected function price(): Attribute
   {
       return Attribute::make(
           get: fn ($value) => $value / 100,
           set: fn ($value) => $value * 100,
       );
   }
   ```

## 💡 Best Practices

### 1. Consistent Response Structure

```php
// Common base class for all resources
abstract class BaseResource extends JsonResource
{
    protected function formatTimestamp($timestamp): ?string
    {
        return $timestamp?->toISOString();
    }

    protected function formatMoney($amount, string $currency = 'JPY'): array
    {
        return [
            'amount' => $amount,
            'currency' => $currency,
            'formatted' => number_format($amount) . ' ' . $currency,
        ];
    }
}
```

### 2. Explicit Type Casting

```php
public function toArray($request)
{
    return [
        'id' => (int) $this->id,  // Explicitly cast to int
        'name' => (string) $this->name,
        'price' => (float) $this->price,
        'is_active' => (bool) $this->is_active,
        'tags' => $this->tags->pluck('name')->toArray(), // Return as array
    ];
}
```

### 3. Proper Relation Handling

```php
public function toArray($request)
{
    return [
        // Avoid N+1 problem
        'comments_count' => $this->whenCounted('comments'),
        
        // Include only when loaded
        'comments' => CommentResource::collection(
            $this->whenLoaded('comments')
        ),
        
        // Provide default value
        'author' => $this->whenLoaded(
            'author',
            fn() => new UserResource($this->author),
            fn() => null
        ),
    ];
}
```

### 4. Unified Error Responses

```php
trait ApiResponses
{
    protected function successResponse($data, string $message = '', int $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function errorResponse(string $message, int $code = 400, array $errors = [])
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    protected function validationErrorResponse($validator)
    {
        return $this->errorResponse(
            'Validation failed',
            422,
            $validator->errors()->toArray()
        );
    }
}
```

## 🔍 Troubleshooting

### Response Structure Not Detected

1. **Check Return Statement**
   ```php
   // ✅ Detected
   return new UserResource($user);
   return UserResource::collection($users);
   
   // ❌ May not be detected
   return response()->json(new UserResource($user));
   ```

2. **Resource Class Namespace**
   ```php
   use App\Http\Resources\UserResource; // Correct namespace
   ```

3. **Clear Cache**
   ```bash
   php artisan spectrum:cache clear
   ```

### Nesting Too Deep Warning

```php
// config/spectrum.php
'analysis' => [
    'max_depth' => 5, // Adjust maximum nesting depth
],
```

## 📚 Related Documentation

- [API Resources](./api-resources.md) - Laravel resources in detail
- [Pagination](./pagination.md) - Pagination support
- [Error Handling](./error-handling.md) - Error responses