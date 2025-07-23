# Real-World Examples

This guide shows practical examples of how Laravel Spectrum automatically generates documentation for common API patterns.

## FormRequest with Advanced Validation

```php
// app/Http/Requests/CreateProductRequest.php
class CreateProductRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0|max:999999.99',
            'category' => 'required|exists:categories,id',
            'status' => 'required|in:active,inactive,draft',
            'tags' => 'array|max:10',
            'tags.*' => 'string|distinct|max:50',
            'images' => 'required|array|min:1|max:5',
            'images.*' => 'image|mimes:jpeg,png,webp|max:5120', // 5MB
            'variations' => 'sometimes|array',
            'variations.*.sku' => 'required_with:variations|string|unique:product_variations,sku',
            'variations.*.price' => 'required_with:variations|numeric|min:0',
        ];
    }
}
```

**Spectrum automatically generates:**
- ✅ Multipart form-data schema for file uploads
- ✅ Enum constraints for status field
- ✅ Nested object schemas for variations
- ✅ Array validation with size constraints
- ✅ Custom validation messages

## API Resource with Conditional Fields

```php
// app/Http/Resources/ProductResource.php
class ProductResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'price' => [
                'amount' => $this->price,
                'currency' => 'USD',
                'formatted' => '$' . number_format($this->price, 2),
            ],
            'category' => new CategoryResource($this->category),
            'images' => ImageResource::collection($this->images),
            'in_stock' => $this->quantity > 0,
            'quantity' => $this->when($request->user()?->isAdmin(), $this->quantity),
            'cost' => $this->when($request->user()?->isAdmin(), $this->cost),
            'analytics' => $this->when($request->user()?->can('view-analytics'), [
                'views' => $this->views_count,
                'sales' => $this->sales_count,
                'revenue' => $this->revenue_total,
            ]),
            'variations' => ProductVariationResource::collection($this->whenLoaded('variations')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'meta' => [
                'average_rating' => $this->reviews_avg_rating ?? 0,
                'review_count' => $this->reviews_count ?? 0,
                'favorited' => $request->user()?->hasFavorited($this->resource) ?? false,
            ],
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
```

**Spectrum automatically detects:**
- ✅ Nested object structures
- ✅ Conditional fields based on permissions
- ✅ Related resource collections
- ✅ Computed fields and aggregates
- ✅ Complex nested schemas

## Query Parameters & Pagination

```php
// app/Http/Controllers/ProductController.php
class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query();
        
        // Spectrum detects these query parameters automatically!
        if ($request->has('category')) {
            $query->where('category_id', $request->category);
        }
        
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }
        
        if ($request->has('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }
        
        if ($request->has('sort')) {
            $direction = $request->get('direction', 'asc');
            $query->orderBy($request->sort, $direction);
        }
        
        // Spectrum detects pagination automatically!
        $products = $query->paginate($request->get('per_page', 15));
        
        return ProductResource::collection($products);
    }
}
```

**Generated OpenAPI includes:**
- ✅ Query parameters with types
- ✅ Pagination wrapper schema
- ✅ Sort and filter options
- ✅ Default values

## Enum Support

```php
// app/Enums/OrderStatus.php
enum OrderStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
}

// app/Http/Requests/UpdateOrderRequest.php
class UpdateOrderRequest extends FormRequest
{
    public function rules()
    {
        return [
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
```

**Spectrum automatically:**
- ✅ Detects enum values from PHP enum
- ✅ Generates enum constraints in OpenAPI
- ✅ Includes all possible values
- ✅ Maintains type safety

## File Upload Handling

```php
// app/Http/Controllers/MediaController.php
class MediaController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,png,pdf|max:10240', // 10MB
            'type' => 'required|in:avatar,document,attachment',
            'description' => 'nullable|string|max:500',
        ]);
        
        $path = $request->file('file')->store('uploads');
        
        return response()->json([
            'url' => Storage::url($path),
            'type' => $request->type,
            'size' => $request->file('file')->getSize(),
            'mime_type' => $request->file('file')->getMimeType(),
        ]);
    }
}
```

**Spectrum generates:**
- ✅ multipart/form-data content type
- ✅ File upload schema with constraints
- ✅ MIME type restrictions
- ✅ File size limits

## Fractal Transformer with Includes

```php
class BookTransformer extends TransformerAbstract
{
    protected $availableIncludes = ['author', 'publisher', 'reviews'];
    protected $defaultIncludes = ['author'];
    
    public function transform(Book $book)
    {
        return [
            'id' => $book->id,
            'isbn' => $book->isbn,
            'title' => $book->title,
            'published_at' => $book->published_at->toDateString(),
        ];
    }
    
    public function includeAuthor(Book $book)
    {
        return $this->item($book->author, new AuthorTransformer);
    }
    
    public function includeReviews(Book $book)
    {
        return $this->collection($book->reviews, new ReviewTransformer);
    }
}
```

**Spectrum documents:**
- Default includes (always present)
- Optional includes (query parameter: `?include=reviews,publisher`)
- Nested include structures

## Custom Validation Rules

```php
// Custom rule class
class StrongPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (strlen($value) < 12 || !preg_match('/[A-Z]/', $value)) {
            $fail('The :attribute must be at least 12 characters and contain uppercase.');
        }
    }
}

// Usage in FormRequest
public function rules()
{
    return [
        'password' => ['required', new StrongPassword, 'confirmed'],
        'email' => 'required|email|unique:users,email',
    ];
}
```

## Complex Nested Validation

```php
public function rules()
{
    return [
        'order' => 'required|array',
        'order.items' => 'required|array|min:1',
        'order.items.*.product_id' => 'required|exists:products,id',
        'order.items.*.quantity' => 'required|integer|min:1',
        'order.items.*.options' => 'nullable|array',
        'order.items.*.options.*.name' => 'required|string',
        'order.items.*.options.*.value' => 'required|string',
        'order.shipping' => 'required|array',
        'order.shipping.address' => 'required|string',
        'order.shipping.method' => 'required|in:standard,express,overnight',
        'order.payment' => 'required|array',
        'order.payment.method' => 'required|in:card,paypal,bank_transfer',
        'order.payment.card_number' => 'required_if:order.payment.method,card',
    ];
}
```

**Spectrum generates complete nested schema with all constraints and conditional requirements.**

## Authentication Examples

### Sanctum Authentication

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('users', UserController::class);
    Route::post('/users/{user}/avatar', [UserController::class, 'uploadAvatar']);
});
```

### Custom API Key Authentication

```php
Route::middleware('auth.apikey')->group(function () {
    Route::get('/stats', StatsController::class);
    Route::get('/reports', ReportsController::class);
});
```

### Mixed Authentication

```php
Route::prefix('api/v1')->group(function () {
    // Public endpoints
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    
    // Authenticated endpoints
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/profile', [ProfileController::class, 'show']);
    });
    
    // Admin only endpoints
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('/products', [ProductController::class, 'store']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);
    });
});
```

Spectrum automatically detects and documents the authentication requirements for each endpoint.