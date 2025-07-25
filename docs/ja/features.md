# 機能一覧

Laravel Spectrumの全機能を詳しく説明します。

## 📝 リクエスト解析

### FormRequestバリデーション

FormRequestクラスから自動的にバリデーションルールを検出し、OpenAPIスキーマに変換します。

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

生成されるスキーマ：
- `name`: オプション、文字列、最大255文字
- `email`: オプション、メール形式
- `age`: null許可、整数、0-150の範囲
- `role`: 必須、enum ['admin', 'editor', 'viewer']
- `tags`: 配列、各要素は文字列
- `profile.avatar`: ファイルアップロード、画像形式、最大2MB

### インラインバリデーション

コントローラー内の`validate()`メソッドも検出：

```php
public function update(Request $request, $id)
{
    $validated = $request->validate([
        'status' => 'required|in:draft,published,archived',
        'published_at' => 'required_if:status,published|date',
    ]);
}
```

### 条件付きバリデーション

HTTPメソッドや他の条件に基づく動的バリデーション：

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

## 📦 レスポンス解析

### APIリソース

Laravel APIリソースの構造を自動解析：

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

検出される要素：
- 基本フィールド（id, title, slug）
- 条件付きフィールド（`when()`, `mergeWhen()`）
- ネストされたリソース
- リレーション（`whenLoaded()`）
- メタデータ構造

### Fractalトランスフォーマー

League/Fractalを使用している場合も対応：

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

### ページネーション

自動的にページネーションレスポンスを検出：

```php
// コントローラー
public function index()
{
    return PostResource::collection(
        Post::with('author')->paginate(15)
    );
}
```

生成されるスキーマには以下が含まれます：
- `data`: アイテムの配列
- `links`: ページネーションリンク
- `meta`: ページ情報（current_page, total, per_pageなど）

## 🔍 クエリパラメータ検出

コントローラーメソッド内の`$request->input()`や`$request->query()`から自動検出：

```php
public function index(Request $request)
{
    $query = Post::query();
    
    // 検索
    if ($search = $request->input('search')) {
        $query->where('title', 'like', "%{$search}%");
    }
    
    // フィルタリング
    if ($status = $request->query('status')) {
        $query->where('status', $status);
    }
    
    // ソート
    $sortBy = $request->input('sort_by', 'created_at');
    $sortOrder = $request->input('sort_order', 'desc');
    $query->orderBy($sortBy, $sortOrder);
    
    // ページネーション
    $perPage = $request->input('per_page', 15);
    
    return PostResource::collection($query->paginate($perPage));
}
```

検出されるパラメータ：
- `search`: 文字列、オプション
- `status`: 文字列、オプション
- `sort_by`: 文字列、デフォルト 'created_at'
- `sort_order`: 文字列、デフォルト 'desc'
- `per_page`: 整数、デフォルト 15

## 📤 ファイルアップロード

ファイルアップロードの自動検出：

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

適切な`multipart/form-data`エンコーディングでドキュメント化されます。

## 🔐 認証とセキュリティ

### ミドルウェア検出

```php
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/api/profile', [ProfileController::class, 'show']);
});

Route::middleware('auth:api')->group(function () {
    Route::apiResource('posts', PostController::class);
});
```

### カスタムミドルウェア

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

## 🎨 例データ生成

### Fakerを使用した動的な例

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

### モデルファクトリーとの統合

モデルファクトリーが定義されている場合、自動的に使用：

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

## 🏷️ タグとグループ化

### 自動タグ生成

コントローラー名やルートプレフィックスから自動生成：

```
/api/v1/users/* → "Users"
/api/v1/posts/* → "Posts"
/api/admin/* → "Admin"
```

### カスタムタグマッピング

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

## ⚡ パフォーマンス機能

### インクリメンタル生成

変更されたファイルのみを再解析：

```bash
php artisan spectrum:generate --incremental
```

### 並列処理

マルチコア処理で高速化：

```bash
php artisan spectrum:generate:optimized --workers=8
```

### スマートキャッシング

- ルート定義のキャッシュ
- FormRequest解析結果のキャッシュ
- APIリソース構造のキャッシュ
- 依存関係の追跡

## 🔄 リアルタイム機能

### ホットリロード

```bash
php artisan spectrum:watch
```

- ファイル変更の自動検出
- WebSocketによるブラウザ更新
- 差分更新で高速
- 複数ブラウザの同期

### ウォッチ対象

- `app/Http/Controllers/**`
- `app/Http/Requests/**`
- `app/Http/Resources/**`
- `routes/**`
- `config/spectrum.php`

## 🎭 モックサーバー機能

### 自動モックAPI生成

OpenAPIドキュメントから完全に機能するモックAPIサーバーを起動：

```bash
php artisan spectrum:mock
```

### 主な機能

- **動的レスポンス生成**: OpenAPIスキーマに基づいて自動生成
- **認証シミュレーション**: Bearer、API Key、Basic認証に対応
- **バリデーション**: リクエストの自動検証
- **シナリオベース**: 成功/エラーなど複数のシナリオ
- **遅延シミュレーション**: ネットワーク遅延の再現

### 使用例

```bash
# 基本的な起動
php artisan spectrum:mock

# カスタムポートとレスポンス遅延
php artisan spectrum:mock --port=3000 --delay=200

# エラーシナリオでテスト
curl http://localhost:8081/api/users?_scenario=error
```

詳細は[モックサーバーガイド](./mock-server.md)を参照してください。

## 📚 次のステップ

- [パフォーマンス最適化](./performance.md) - 大規模プロジェクト向け設定
- [エクスポート機能](./export.md) - PostmanやInsomniaへのエクスポート
- [カスタマイズ](./customization.md) - 高度なカスタマイズ方法