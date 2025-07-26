---
id: migration-guide
title: 移行ガイド
sidebar_label: 移行ガイド
---

# 移行ガイド

他のAPIドキュメント生成ツールからLaravel Spectrumへの移行方法を説明します。

## 🎯 移行の概要

Laravel Spectrumは既存のコードを変更することなく動作するため、段階的な移行が可能です。既存のアノテーションやドキュメントを残したまま、Laravel Spectrumを導入できます。

## 📝 Swagger-PHPからの移行

### 現状の確認

Swagger-PHPを使用している場合、以下のようなアノテーションがあるはずです：

```php
/**
 * @OA\Post(
 *     path="/api/users",
 *     summary="Create a new user",
 *     tags={"Users"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"name","email","password"},
 *             @OA\Property(property="name", type="string", example="John Doe"),
 *             @OA\Property(property="email", type="string", format="email"),
 *             @OA\Property(property="password", type="string", format="password")
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="User created successfully",
 *         @OA\JsonContent(ref="#/components/schemas/User")
 *     )
 * )
 */
public function store(Request $request)
{
    // ...
}
```

### ステップ1: Laravel Spectrumのインストール

```bash
composer require wadakatu/laravel-spectrum --dev
```

### ステップ2: 初期比較

既存のSwagger出力とLaravel Spectrumの出力を比較：

```bash
# 既存のSwaggerドキュメントをバックアップ
cp storage/api-docs/api-docs.json storage/api-docs/swagger-backup.json

# Laravel Spectrumでドキュメント生成
php artisan spectrum:generate

# 比較ツールで確認
```

### ステップ3: FormRequestへの移行

アノテーションからFormRequestへ段階的に移行：

**Before (Swagger-PHP):**
```php
/**
 * @OA\RequestBody(
 *     required=true,
 *     @OA\JsonContent(
 *         required={"name","email","password"},
 *         @OA\Property(property="name", type="string"),
 *         @OA\Property(property="email", type="string", format="email"),
 *         @OA\Property(property="password", type="string", minLength=8)
 *     )
 * )
 */
public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users',
        'password' => 'required|min:8',
    ]);
}
```

**After (Laravel Spectrum):**
```php
// FormRequestを作成
class StoreUserRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
        ];
    }
}

// コントローラーを更新
public function store(StoreUserRequest $request)
{
    $validated = $request->validated();
    // アノテーションは削除可能
}
```

### ステップ4: レスポンスの移行

**Before:**
```php
/**
 * @OA\Response(
 *     response=200,
 *     @OA\JsonContent(
 *         type="object",
 *         @OA\Property(property="id", type="integer"),
 *         @OA\Property(property="name", type="string"),
 *         @OA\Property(property="email", type="string")
 *     )
 * )
 */
public function show($id)
{
    $user = User::findOrFail($id);
    return response()->json($user);
}
```

**After:**
```php
// APIリソースを作成
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
}

// コントローラーを更新
public function show($id)
{
    $user = User::findOrFail($id);
    return new UserResource($user);
}
```

### ステップ5: 設定の移行

```php
// config/l5-swagger.php の設定を spectrum.php に移行
return [
    'title' => config('l5-swagger.documentations.default.info.title'),
    'version' => config('l5-swagger.documentations.default.info.version'),
    'description' => config('l5-swagger.documentations.default.info.description'),
    
    'servers' => array_map(function ($server) {
        return [
            'url' => $server['url'],
            'description' => $server['description'] ?? '',
        ];
    }, config('l5-swagger.documentations.default.servers', [])),
];
```

### 移行完了後のクリーンアップ

```bash
# Swagger-PHPのアンインストール（オプション）
composer remove darkaonline/l5-swagger

# アノテーションの削除スクリプト
php artisan make:command RemoveSwaggerAnnotations
```

## 🔧 L5-Swaggerからの移行

L5-SwaggerはSwagger-PHPのLaravelラッパーなので、基本的な移行手順は同じです。

### 追加の考慮事項

1. **ルート設定の移行**
   ```php
   // L5-Swaggerのルート設定を無効化
   // config/l5-swagger.php
   'routes' => [
       'api' => false, // ドキュメントルートを無効化
   ],
   ```

2. **ビューの移行**
   ```php
   // 既存のSwagger UIビューをLaravel Spectrum用に更新
   // resources/views/api/documentation.blade.php
   <script>
   SwaggerUIBundle({
       url: "{{ asset('storage/app/spectrum/openapi.json') }}",
       // L5-Swaggerの設定をそのまま使用可能
   });
   </script>
   ```

## 📚 Scribeからの移行

### 主な違い

Scribeは部分的にアノテーションフリーですが、完全ではありません：

```php
// Scribeのアノテーション例
/**
 * @group User Management
 * @authenticated
 * @response {
 *   "id": 1,
 *   "name": "John Doe"
 * }
 */
```

### 移行手順

1. **グループ/タグの移行**
   ```php
   // config/spectrum.php
   'tags' => [
       'api/users/*' => 'User Management',
       'api/posts/*' => 'Content Management',
   ],
   ```

2. **認証設定の移行**
   ```php
   // Scribeの @authenticated を自動検出に置き換え
   Route::middleware('auth:sanctum')->group(function () {
       // これらのルートは自動的に認証必須として検出される
   });
   ```

3. **例データの移行**
   ```php
   // Scribeの例をFactoryやSeederに移行
   class UserFactory extends Factory
   {
       public function definition()
       {
           return [
               'name' => $this->faker->name(),
               'email' => $this->faker->unique()->safeEmail(),
           ];
       }
   }
   ```

## 🔄 API Blueprintからの移行

### Blueprint形式からの変換

API Blueprint（`.apib`ファイル）を使用している場合：

```apib
# Group Users
## User Collection [/users]
### List Users [GET]
+ Response 200 (application/json)
    + Attributes (array[User])
```

### 移行アプローチ

1. **ルート構造の確認**
   ```bash
   # Laravelルートと照合
   php artisan route:list --path=api
   ```

2. **データ構造の移行**
    - BlueprintのData StructuresをLaravel Resourcesに変換
    - AttributesをFormRequestsに変換

## 🎯 段階的移行戦略

### フェーズ1: 共存期間

```php
// 両方のドキュメントを生成
"scripts": {
    "docs:swagger": "php artisan l5-swagger:generate",
    "docs:spectrum": "php artisan spectrum:generate",
    "docs:all": "npm run docs:swagger && npm run docs:spectrum"
}
```

### フェーズ2: 検証期間

```php
// カスタムコマンドで差分を確認
class CompareDocumentationCommand extends Command
{
    public function handle()
    {
        $swagger = json_decode(file_get_contents('storage/api-docs/api-docs.json'), true);
        $spectrum = json_decode(file_get_contents('storage/app/spectrum/openapi.json'), true);
        
        // パスを比較
        $swaggerPaths = array_keys($swagger['paths'] ?? []);
        $spectrumPaths = array_keys($spectrum['paths'] ?? []);
        
        $missing = array_diff($swaggerPaths, $spectrumPaths);
        $extra = array_diff($spectrumPaths, $swaggerPaths);
        
        $this->info('Missing paths: ' . implode(', ', $missing));
        $this->info('Extra paths: ' . implode(', ', $extra));
    }
}
```

### フェーズ3: 切り替え

```php
// 環境変数で制御
if (env('USE_SPECTRUM_DOCS', false)) {
    return redirect('/api/documentation/spectrum');
} else {
    return redirect('/api/documentation/swagger');
}
```

## 💡 移行のベストプラクティス

### 1. バックアップの作成

```bash
# 既存のドキュメントをバックアップ
git add .
git commit -m "Backup: Before Laravel Spectrum migration"
git tag pre-spectrum-migration
```

### 2. チームへの周知

```markdown
## ドキュメント移行のお知らせ

- 移行期間: 2週間
- 影響: APIドキュメントの自動生成方式が変更
- メリット: アノテーション不要、メンテナンス削減
- 作業: FormRequestの使用を推奨
```

### 3. CI/CDの更新

```yaml
# .github/workflows/api-docs.yml
- name: Generate API Documentation
  run: |
    # 一時的に両方を生成
    php artisan l5-swagger:generate || true
    php artisan spectrum:generate
    
    # 比較レポートを生成
    php artisan docs:compare > docs-comparison.txt
```

### 4. 移行チェックリスト

- [ ] Laravel Spectrumのインストール
- [ ] 設定ファイルの作成
- [ ] サンプルエンドポイントでテスト
- [ ] FormRequestへの段階的移行
- [ ] APIリソースの作成
- [ ] ドキュメントの比較検証
- [ ] チームレビュー
- [ ] 本番環境への展開
- [ ] 旧ツールの削除

## 🔍 トラブルシューティング

### 移行後にパスが見つからない

```php
// デバッグモードで詳細を確認
php artisan spectrum:generate -vvv

// 特定のパスをテスト
php artisan spectrum:analyze "api/users"
```

### スキーマの不一致

```php
// カスタムマッピングを追加
Spectrum::addSchemaMapping(function ($path, $method) {
    if ($path === '/api/legacy/endpoint') {
        return [
            'deprecated' => true,
            'x-legacy-schema' => true,
        ];
    }
});
```

## 📚 関連ドキュメント

- [インストールと設定](./installation.md) - 初期セットアップ
- [他ツールとの比較](./comparison.md) - 機能比較表
- [FAQ](./faq.md) - よくある質問