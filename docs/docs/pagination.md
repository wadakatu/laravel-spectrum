# Pagination Guide

Laravel Spectrum automatically reflects Laravel's pagination in OpenAPI documentation and generates appropriate schemas.

## 🎯 Basic Pagination

### Eloquent Pagination

```php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::paginate(20);
        return UserResource::collection($users);
    }
}
```

Generated OpenAPI schema:

```json
{
  "UserCollection": {
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
          "first": {
            "type": "string",
            "nullable": true,
            "example": "https://api.example.com/users?page=1"
          },
          "last": {
            "type": "string",
            "nullable": true,
            "example": "https://api.example.com/users?page=10"
          },
          "prev": {
            "type": "string",
            "nullable": true
          },
          "next": {
            "type": "string",
            "nullable": true
          }
        }
      },
      "meta": {
        "type": "object",
        "properties": {
          "current_page": {
            "type": "integer",
            "example": 1
          },
          "from": {
            "type": "integer",
            "nullable": true,
            "example": 1
          },
          "last_page": {
            "type": "integer",
            "example": 10
          },
          "links": {
            "type": "array",
            "items": {
              "type": "object",
              "properties": {
                "url": {
                  "type": "string",
                  "nullable": true
                },
                "label": {
                  "type": "string"
                },
                "active": {
                  "type": "boolean"
                }
              }
            }
          },
          "path": {
            "type": "string",
            "example": "https://api.example.com/users"
          },
          "per_page": {
            "type": "integer",
            "example": 20
          },
          "to": {
            "type": "integer",
            "nullable": true,
            "example": 20
          },
          "total": {
            "type": "integer",
            "example": 200
          }
        }
      }
    }
  }
}
```

## 📦 Custom Pagination

### Dynamic Page Size

```php
public function index(Request $request)
{
    $request->validate([
        'per_page' => 'integer|min:1|max:100',
        'page' => 'integer|min:1',
        'sort' => 'string|in:name,email,created_at',
        'order' => 'string|in:asc,desc',
    ]);
    
    $perPage = $request->input('per_page', 20);
    $sortField = $request->input('sort', 'created_at');
    $sortOrder = $request->input('order', 'desc');
    
    $users = User::orderBy($sortField, $sortOrder)
                 ->paginate($perPage);
    
    return UserResource::collection($users);
}
```

### Simple Pagination

```php
public function index(Request $request)
{
    // simplePaginate provides only previous/next page links
    $users = User::simplePaginate(20);
    
    return UserResource::collection($users);
}
```

Generated schema (simple pagination):

```json
{
  "SimplePaginatedCollection": {
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
          "prev": {
            "type": "string",
            "nullable": true
          },
          "next": {
            "type": "string",
            "nullable": true
          }
        }
      },
      "meta": {
        "type": "object",
        "properties": {
          "per_page": {
            "type": "integer"
          },
          "current_page": {
            "type": "integer"
          },
          "from": {
            "type": "integer",
            "nullable": true
          },
          "to": {
            "type": "integer",
            "nullable": true
          }
        }
      }
    }
  }
}
```

## 🔄 Cursor Pagination

### For Large Datasets

```php
public function index(Request $request)
{
    $request->validate([
        'cursor' => 'string|nullable',
        'per_page' => 'integer|min:1|max:50',
    ]);
    
    $perPage = $request->input('per_page', 20);
    
    $users = User::orderBy('id')
                 ->cursorPaginate($perPage);
    
    return UserResource::collection($users);
}
```

Cursor pagination schema:

```json
{
  "CursorPaginatedCollection": {
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
          "prev": {
            "type": "string",
            "nullable": true
          },
          "next": {
            "type": "string",
            "nullable": true
          }
        }
      },
      "meta": {
        "type": "object",
        "properties": {
          "path": {
            "type": "string"
          },
          "per_page": {
            "type": "integer"
          },
          "next_cursor": {
            "type": "string",
            "nullable": true
          },
          "prev_cursor": {
            "type": "string",
            "nullable": true
          }
        }
      }
    }
  }
}
```

## 🎨 Custom Pagination Response

### Adding Metadata

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
                'total' => $this->total(),
                'count' => $this->count(),
                'per_page' => $this->perPage(),
                'current_page' => $this->currentPage(),
                'total_pages' => $this->lastPage(),
                'stats' => [
                    'active_users' => $this->collection->where('is_active', true)->count(),
                    'new_users_today' => $this->collection->where('created_at', '>=', today())->count(),
                ],
            ],
        ];
    }
    
    public function with($request)
    {
        return [
            'links' => [
                'self' => $request->fullUrl(),
            ],
            'timestamp' => now()->toISOString(),
        ];
    }
}
```

### Custom Paginator

```php
namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CustomPaginator
{
    public static function paginate(Collection $items, int $perPage = 20, int $page = null)
    {
        $page = $page ?: request()->input('page', 1);
        $total = $items->count();
        
        $items = $items->slice(($page - 1) * $perPage, $perPage)->values();
        
        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url()]
        );
    }
}

// Usage example
public function index(Request $request)
{
    $users = User::all();
    
    // Custom filtering
    if ($search = $request->input('search')) {
        $users = $users->filter(function ($user) use ($search) {
            return stripos($user->name, $search) !== false ||
                   stripos($user->email, $search) !== false;
        });
    }
    
    $paginated = CustomPaginator::paginate($users, 20);
    
    return UserResource::collection($paginated);
}
```

## 🚀 Advanced Pagination

### Multi-Resource Unified Pagination

```php
public function search(Request $request)
{
    $query = $request->input('q');
    $type = $request->input('type', 'all');
    $perPage = $request->input('per_page', 20);
    
    $results = collect();
    
    if (in_array($type, ['all', 'users'])) {
        $users = User::where('name', 'LIKE', "%{$query}%")
                     ->limit($perPage)
                     ->get()
                     ->map(fn($user) => [
                         'type' => 'user',
                         'data' => new UserResource($user)
                     ]);
        $results = $results->concat($users);
    }
    
    if (in_array($type, ['all', 'posts'])) {
        $posts = Post::where('title', 'LIKE', "%{$query}%")
                     ->limit($perPage)
                     ->get()
                     ->map(fn($post) => [
                         'type' => 'post',
                         'data' => new PostResource($post)
                     ]);
        $results = $results->concat($posts);
    }
    
    $paginated = CustomPaginator::paginate($results, $perPage);
    
    return response()->json($paginated);
}
```

### Infinite Scroll Support

```php
public function infiniteScroll(Request $request)
{
    $lastId = $request->input('last_id', PHP_INT_MAX);
    $limit = $request->input('limit', 20);
    
    $posts = Post::where('id', '<', $lastId)
                 ->orderBy('id', 'desc')
                 ->limit($limit + 1)
                 ->get();
    
    $hasMore = $posts->count() > $limit;
    
    if ($hasMore) {
        $posts = $posts->take($limit);
    }
    
    return response()->json([
        'data' => PostResource::collection($posts),
        'meta' => [
            'has_more' => $hasMore,
            'last_id' => $posts->last()?->id,
        ],
    ]);
}
```

## 📊 Pagination Parameters

### Query Parameter Definition

Laravel Spectrum automatically detects the following query parameters:

```yaml
parameters:
  - name: page
    in: query
    description: Page number
    schema:
      type: integer
      default: 1
      minimum: 1
  - name: per_page
    in: query
    description: Number of items per page
    schema:
      type: integer
      default: 20
      minimum: 1
      maximum: 100
  - name: sort
    in: query
    description: Sort field
    schema:
      type: string
      enum: [name, email, created_at]
      default: created_at
  - name: order
    in: query
    description: Sort order
    schema:
      type: string
      enum: [asc, desc]
      default: desc
```

## 🔧 Performance Optimization

### Efficient Queries

```php
public function index(Request $request)
{
    $users = User::with(['profile', 'posts' => function ($query) {
                    $query->latest()->limit(5);
                 }])
                 ->withCount('posts')
                 ->when($request->input('status'), function ($query, $status) {
                     $query->where('status', $status);
                 })
                 ->when($request->input('role'), function ($query, $role) {
                     $query->whereHas('roles', function ($q) use ($role) {
                         $q->where('name', $role);
                     });
                 })
                 ->paginate(20);
    
    return UserResource::collection($users);
}
```

### Cache Utilization

```php
public function index(Request $request)
{
    $page = $request->input('page', 1);
    $perPage = $request->input('per_page', 20);
    
    $cacheKey = "users.page.{$page}.per_page.{$perPage}";
    
    $users = Cache::remember($cacheKey, 3600, function () use ($perPage) {
        return User::with('profile')->paginate($perPage);
    });
    
    return UserResource::collection($users);
}
```

## 💡 Best Practices

### 1. Appropriate Page Size
- Default: 20-50 items
- Maximum: 100 items
- Mobile: 10-20 items

### 2. Consistent Response Structure
```php
trait PaginationResponse
{
    protected function paginatedResponse($query, $resourceClass, $perPage = 20)
    {
        $paginated = $query->paginate($perPage);
        
        return $resourceClass::collection($paginated)->additional([
            'meta' => [
                'success' => true,
                'timestamp' => now()->toISOString(),
            ],
        ]);
    }
}
```

### 3. Adding Pagination Info to Headers
```php
public function index(Request $request)
{
    $users = User::paginate(20);
    
    return UserResource::collection($users)
        ->response()
        ->header('X-Total-Count', $users->total())
        ->header('X-Page-Count', $users->lastPage())
        ->header('X-Current-Page', $users->currentPage())
        ->header('X-Per-Page', $users->perPage());
}
```

## 📚 Related Documentation

- [API Resources](./api-resources.md) - Resource collection details
- [Response Analysis](./response-analysis.md) - Response structure analysis
- [Performance Optimization](./performance.md) - Processing large datasets