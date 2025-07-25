# 基本的な使い方

このガイドでは、Laravel Spectrumの基本的な使い方を説明します。

## 🎯 基本コマンド

### ドキュメント生成

最も基本的なコマンドです。プロジェクトのAPIを解析してOpenAPIドキュメントを生成します：

```bash
php artisan spectrum:generate
```

このコマンドは以下を実行します：
- すべてのAPIルートを解析
- バリデーションルールを検出
- レスポンス構造を推測
- OpenAPI 3.0形式でドキュメントを生成

### リアルタイムプレビュー

開発中にドキュメントをリアルタイムで確認：

```bash
php artisan spectrum:watch
```

- `http://localhost:8080`でドキュメントを表示
- ファイル変更を自動検出
- WebSocketでブラウザを自動更新

### キャッシュクリア

解析結果のキャッシュをクリア：

```bash
php artisan spectrum:cache:clear
```

### モックサーバー起動

生成されたドキュメントからモックAPIサーバーを起動：

```bash
php artisan spectrum:mock
# http://localhost:8081 でモックAPIが利用可能
```

## 📝 基本的な例

### シンプルなAPIコントローラー

```php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;

class UserController extends Controller
{
    /**
     * ユーザー一覧を取得
     */
    public function index()
    {
        $users = User::paginate(20);
        return UserResource::collection($users);
    }

    /**
     * 新規ユーザーを作成
     */
    public function store(CreateUserRequest $request)
    {
        $user = User::create($request->validated());
        return new UserResource($user);
    }
}
```

### FormRequestの例

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
            'role' => 'required|in:admin,user,guest',
        ];
    }
}
```

Laravel Spectrumは自動的に：
- ✅ 各フィールドの型を検出
- ✅ バリデーションルールをOpenAPIスキーマに変換
- ✅ 必須/任意フィールドを識別
- ✅ Enum値（`in:admin,user,guest`）を検出

## 🎨 ドキュメントの表示

### Bladeビューでの表示

```html
<!DOCTYPE html>
<html>
<head>
    <title>API Documentation</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist/swagger-ui.css">
</head>
<body>
    <div id="swagger-ui"></div>
    
    <script src="https://unpkg.com/swagger-ui-dist/swagger-ui-bundle.js"></script>
    <script>
    window.onload = function() {
        SwaggerUIBundle({
            url: "{{ asset('storage/app/spectrum/openapi.json') }}",
            dom_id: '#swagger-ui',
            deepLinking: true,
            presets: [
                SwaggerUIBundle.presets.apis,
            ],
            layout: "StandaloneLayout"
        });
    };
    </script>
</body>
</html>
```

### ReDocでの表示

より洗練されたドキュメントUIを使用：

```html
<!DOCTYPE html>
<html>
<head>
    <title>API Documentation</title>
    <style>
        body { margin: 0; padding: 0; }
    </style>
</head>
<body>
    <redoc spec-url="{{ asset('storage/app/spectrum/openapi.json') }}"></redoc>
    <script src="https://cdn.jsdelivr.net/npm/redoc/bundles/redoc.standalone.js"></script>
</body>
</html>
```

## 🔍 検出される要素

### 1. HTTPメソッドとパス

```php
Route::get('/api/users', [UserController::class, 'index']);
Route::post('/api/users', [UserController::class, 'store']);
Route::put('/api/users/{user}', [UserController::class, 'update']);
Route::delete('/api/users/{user}', [UserController::class, 'destroy']);
```

### 2. リクエストパラメータ

FormRequestから自動検出：
- 必須/任意フィールド
- データ型
- バリデーションルール
- デフォルト値
- Enum制約

### 3. レスポンス構造

APIリソースから自動検出：
```php
class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
```

### 4. 認証要件

ミドルウェアから自動検出：
```php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/api/profile', [ProfileController::class, 'show']);
});
```

## 💡 ベストプラクティス

### 1. FormRequestを使用する

インラインバリデーションよりFormRequestを推奨：

```php
// 推奨
public function store(CreateUserRequest $request)
{
    // ...
}

// 非推奨（でも動作します）
public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string',
        // ...
    ]);
}
```

### 2. APIリソースを使用する

一貫したレスポンス構造のために：

```php
// 推奨
return new UserResource($user);

// 非推奨（でも動作します）
return response()->json(['user' => $user]);
```

### 3. ルートグループを活用する

```php
Route::prefix('api/v1')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::apiResource('posts', PostController::class);
    });
});
```

## 🚀 次のステップ

- [機能一覧](./features.md) - すべての機能の詳細
- [高度な使い方](./advanced-features.md) - カスタマイズとテクニック
- [パフォーマンス最適化](./performance.md) - 大規模プロジェクト向け