---
id: quickstart
title: クイックスタートガイド
sidebar_label: クイックスタートガイド
---

# クイックスタートガイド

5分でLaravel Spectrumを使い始めるためのガイドです。

## 🚀 30秒でスタート

```bash
# 1. インストール
composer require wadakatu/laravel-spectrum --dev

# 2. ドキュメント生成
php artisan spectrum:generate

# 3. プレビュー起動
php artisan spectrum:watch
```

ブラウザが自動的に開き、`http://localhost:8080`でAPIドキュメントが表示されます！

## 📋 ステップバイステップガイド

### ステップ1: インストール

```bash
composer require wadakatu/laravel-spectrum --dev
```

### ステップ2: 最初のドキュメント生成

```bash
php artisan spectrum:generate
```

このコマンドで以下が実行されます：
- ✅ すべてのAPIルートを検出
- ✅ FormRequestから自動的にパラメータを抽出
- ✅ APIリソースからレスポンススキーマを生成
- ✅ `storage/app/spectrum/openapi.json`にドキュメントを保存

### ステップ3: ドキュメントの確認

#### オプション1: リアルタイムプレビュー（推奨）

```bash
php artisan spectrum:watch
```

- ブラウザが自動的に開きます
- ファイル変更を検出して自動更新
- 開発中に最適

#### オプション2: Bladeビューに埋め込む

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

### ステップ4: モックサーバーの起動（オプション）

```bash
php artisan spectrum:mock
```

- `http://localhost:8081`でモックAPIが起動
- フロントエンド開発に最適
- 実際のバックエンドなしでテスト可能

## 🎯 基本的な例

### シンプルなコントローラー

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

### APIリソース

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

### ルート定義

```php
// routes/api.php
Route::apiResource('posts', PostController::class);
```

**これだけで完全なAPIドキュメントが生成されます！**

## ⚡ よく使うコマンド

```bash
# 基本的な生成
php artisan spectrum:generate

# キャッシュなしで再生成
php artisan spectrum:generate --no-cache

# 特定のルートパターンのみ
php artisan spectrum:generate --pattern="api/v2/*"

# リアルタイムプレビュー
php artisan spectrum:watch

# モックサーバー起動
php artisan spectrum:mock

# Postmanエクスポート
php artisan spectrum:export:postman

# キャッシュクリア
php artisan spectrum:cache clear
```

## 💡 プロのヒント

### 1. 設定ファイルを公開する

```bash
php artisan vendor:publish --provider="LaravelSpectrum\SpectrumServiceProvider"
```

### 2. カスタムタグの設定

```php
// config/spectrum.php
'tags' => [
    'api/auth/*' => 'Authentication',
    'api/users/*' => 'User Management',
    'api/posts/*' => 'Blog Posts',
],
```

### 3. CI/CDに組み込む

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

## 🔍 トラブルシューティング

### ルートが表示されない

```php
// config/spectrum.php
'route_patterns' => [
    'api/*',     // これがルートと一致するか確認
    'api/v1/*',
],
```

### バリデーションが検出されない

FormRequestが正しくタイプヒントされているか確認：

```php
// ✅ 正しい
public function store(StorePostRequest $request)

// ❌ 間違い
public function store(Request $request)
```

## 📚 次のステップ

1. [設定オプション](./config-reference.md) - 詳細なカスタマイズ
2. [機能一覧](./features.md) - すべての機能を探索
3. [エクスポート機能](./export.md) - PostmanやInsomniaへエクスポート
4. [モックサーバー](./mock-server.md) - モックAPIの活用

---

**🎉 おめでとうございます！** Laravel Spectrumを使い始める準備ができました。