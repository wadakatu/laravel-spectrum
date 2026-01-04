---
id: pagination
title: ページネーションガイド
sidebar_label: ページネーションガイド
---

# ページネーションガイド

Laravel Spectrumは、LaravelのページネーションをOpenAPIドキュメントに自動的に反映し、適切なスキーマを生成します。

## 🎯 基本的なページネーション

### Eloquentページネーション

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

生成されるOpenAPIスキーマ：

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

## 📦 カスタムページネーション

### ページサイズの動的設定

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

### シンプルページネーション

```php
public function index(Request $request)
{
    // simplePaginateは前後のページリンクのみを提供
    $users = User::simplePaginate(20);
    
    return UserResource::collection($users);
}
```

生成されるスキーマ（シンプルページネーション）：

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

## 🔄 カーソルページネーション

### 大規模データセット向け

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

カーソルページネーションのスキーマ：

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

## 🎨 カスタムページネーションレスポンス

### メタデータの追加

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

### カスタムページネーター

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

// 使用例
public function index(Request $request)
{
    $users = User::all();
    
    // カスタムフィルタリング
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

## 🚀 高度なページネーション

### 複数リソースの統合ページネーション

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

### 無限スクロール対応

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

## 📊 ページネーションパラメータ

### クエリパラメータの定義

Laravel Spectrumは自動的に以下のクエリパラメータを検出します：

```yaml
parameters:
  - name: page
    in: query
    description: ページ番号
    schema:
      type: integer
      default: 1
      minimum: 1
  - name: per_page
    in: query
    description: 1ページあたりのアイテム数
    schema:
      type: integer
      default: 20
      minimum: 1
      maximum: 100
  - name: sort
    in: query
    description: ソートフィールド
    schema:
      type: string
      enum: [name, email, created_at]
      default: created_at
  - name: order
    in: query
    description: ソート順
    schema:
      type: string
      enum: [asc, desc]
      default: desc
```

## 🔧 パフォーマンス最適化

### 効率的なクエリ

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

### キャッシュの活用

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

## 💡 ベストプラクティス

### 1. 適切なページサイズ
- デフォルト: 20-50アイテム
- 最大値: 100アイテム
- モバイル向け: 10-20アイテム

### 2. 一貫性のあるレスポンス構造
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

### 3. ページネーション情報のヘッダー追加
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

## 📚 関連ドキュメント

- [APIリソース](./api-resources.md) - リソースコレクションの詳細
- [レスポンス解析](./response-analysis.md) - レスポンス構造の解析
- [パフォーマンス最適化](./performance.md) - 大規模データセットの処理