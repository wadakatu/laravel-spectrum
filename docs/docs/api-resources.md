# API Resources Guide

Laravel Spectrum fully analyzes Laravel API Resources and automatically generates response schemas.

## 🎯 Basic Usage

### Simple Resource

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
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
```

Generated OpenAPI Schema:

```json
{
  "UserResource": {
    "type": "object",
    "properties": {
      "id": {
        "type": "integer"
      },
      "name": {
        "type": "string"
      },
      "email": {
        "type": "string",
        "format": "email"
      },
      "created_at": {
        "type": "string",
        "format": "date-time"
      },
      "updated_at": {
        "type": "string",
        "format": "date-time"
      }
    },
    "required": ["id", "name", "email", "created_at", "updated_at"]
  }
}
```

## 📦 Collections

### Resource Collections

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
                'total_users' => $this->collection->count(),
                'active_users' => $this->collection->where('is_active', true)->count(),
            ],
        ];
    }
}
```

### Paginated Collections

```php
// Controller
public function index()
{
    $users = User::paginate(20);
    return UserResource::collection($users);
}
```

The following schema is automatically generated:

```json
{
  "UserResourceCollection": {
    "type": "object",
    "properties": {
      "data": {
        "type": "array",
        "items": {
          "$ref": "#/components/schemas/UserResource"
        }
      },
      "links": {
        "type": "object",
        "properties": {
          "first": { "type": "string", "nullable": true },
          "last": { "type": "string", "nullable": true },
          "prev": { "type": "string", "nullable": true },
          "next": { "type": "string", "nullable": true }
        }
      },
      "meta": {
        "type": "object",
        "properties": {
          "current_page": { "type": "integer" },
          "from": { "type": "integer", "nullable": true },
          "last_page": { "type": "integer" },
          "path": { "type": "string" },
          "per_page": { "type": "integer" },
          "to": { "type": "integer", "nullable": true },
          "total": { "type": "integer" }
        }
      }
    }
  }
}
```

## 🔄 Conditional Fields

### Using whenLoaded()

```php
class PostResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'author' => new UserResource($this->whenLoaded('author')),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->pluck('name');
            }),
        ];
    }
}
```

### Using when()

```php
class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            // Show only to administrators
            'phone' => $this->when($request->user()->isAdmin(), $this->phone),
            'address' => $this->when($request->user()->isAdmin(), $this->address),
            // Only for own profile
            'private_notes' => $this->when(
                $request->user()->id === $this->id, 
                $this->private_notes
            ),
        ];
    }
}
```

## 🎨 Nested Resources

### Complex Nested Structures

```php
class OrderResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'customer' => new CustomerResource($this->customer),
            'items' => OrderItemResource::collection($this->items),
            'shipping' => [
                'method' => $this->shipping_method,
                'address' => new AddressResource($this->shippingAddress),
                'tracking_number' => $this->tracking_number,
            ],
            'payment' => [
                'method' => $this->payment_method,
                'status' => $this->payment_status,
                'transaction_id' => $this->when(
                    $request->user()->can('view-payment-details'),
                    $this->transaction_id
                ),
            ],
            'totals' => [
                'subtotal' => $this->subtotal,
                'tax' => $this->tax,
                'shipping' => $this->shipping_cost,
                'total' => $this->total,
            ],
        ];
    }
}
```

### Using Resources Within Resources

```php
class BlogPostResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->when($request->route()->getName() === 'posts.show', $this->content),
            'author' => new AuthorResource($this->author),
            'category' => new CategoryResource($this->category),
            'tags' => TagResource::collection($this->tags),
            'featured_image' => $this->when($this->featured_image, function () {
                return new ImageResource($this->featured_image);
            }),
            'related_posts' => $this->when(
                $request->route()->getName() === 'posts.show',
                PostSummaryResource::collection($this->relatedPosts())
            ),
        ];
    }
}
```

## 🔧 Customization

### Adding Metadata

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
                'timestamp' => now()->toISOString(),
            ],
        ];
    }
}
```

### Customizing Wrap Key

```php
class UserResource extends JsonResource
{
    public static $wrap = 'user';
    
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}

// Response example:
{
    "user": {
        "id": 1,
        "name": "John Doe"
    }
}
```

### Disabling Wrapping

```php
class UserResource extends JsonResource
{
    public static $wrap = null;
    
    // Or disable globally in AppServiceProvider
    public function boot()
    {
        JsonResource::withoutWrapping();
    }
}
```

## 🚀 Advanced Examples

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
            'subject' => $this->when($this->subject, function () {
                return $this->morphToResource($this->subject);
            }),
            'causer' => new UserResource($this->causer),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
    
    private function morphToResource($model)
    {
        $resourceMap = [
            'App\Models\Post' => PostResource::class,
            'App\Models\Comment' => CommentResource::class,
            'App\Models\User' => UserResource::class,
        ];
        
        $resourceClass = $resourceMap[get_class($model)] ?? null;
        
        return $resourceClass ? new $resourceClass($model) : null;
    }
}
```

### Dynamic Fields

```php
class DynamicResource extends JsonResource
{
    public function toArray($request)
    {
        $fields = $request->input('fields', []);
        $data = [
            'id' => $this->id,
            'type' => $this->getMorphClass(),
        ];
        
        // Include only requested fields
        if (empty($fields)) {
            return array_merge($data, $this->resource->toArray());
        }
        
        foreach ($fields as $field) {
            if ($this->resource->hasAttribute($field)) {
                $data[$field] = $this->resource->$field;
            }
        }
        
        return $data;
    }
}
```

## 💡 Best Practices

### 1. Consistent Structure

```php
// Create a base resource class
abstract class BaseResource extends JsonResource
{
    protected function baseFields()
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}

// Use inheritance
class UserResource extends BaseResource
{
    public function toArray($request)
    {
        return array_merge($this->baseFields(), [
            'name' => $this->name,
            'email' => $this->email,
        ]);
    }
}
```

### 2. Type Consistency

```php
class ProductResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'price' => (float) $this->price,
            'in_stock' => (bool) $this->in_stock,
            'quantity' => (int) $this->quantity,
            'tags' => $this->tags->pluck('name')->toArray(),
        ];
    }
}
```

### 3. Handling Null Values

```php
class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'bio' => $this->bio ?? '',
            'avatar_url' => $this->avatar_url ?? null,
            'last_login_at' => $this->last_login_at?->toISOString(),
        ];
    }
}
```

## 🔍 Detection in Spectrum

Laravel Spectrum automatically detects:

1. **Field Types** - Infers property types
2. **Required Fields** - Analyzes nullability
3. **Nested Structures** - Detects references to other resources
4. **Collections** - Identifies arrays and collections
5. **Conditional Fields** - Understands `when()` and `whenLoaded()`

## 📚 Related Documentation

- [Response Analysis](./response-analysis.md) - Detailed response structures
- [Pagination](./pagination.md) - Pagination support
- [Advanced Features](./advanced-features.md) - Dynamic response structures