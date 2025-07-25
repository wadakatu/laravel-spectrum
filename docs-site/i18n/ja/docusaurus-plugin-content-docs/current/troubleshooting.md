# トラブルシューティングガイド

Laravel Spectrumを使用する際によくある問題とその解決方法を説明します。

## 🚨 よくある問題

### ルートが検出されない

#### 症状
```bash
php artisan spectrum:generate
# 出力: No routes found matching the configured patterns.
```

#### 原因と解決方法

1. **ルートパターンの確認**
   ```php
   // config/spectrum.php
   'route_patterns' => [
       'api/*',    // 正しい
       '/api/*',   // スラッシュ不要
   ],
   ```

2. **ルートキャッシュのクリア**
   ```bash
   php artisan route:clear
   php artisan route:cache
   ```

3. **ルート登録の確認**
   ```bash
   php artisan route:list --path=api
   ```

4. **名前空間の問題**
   ```php
   // RouteServiceProvider.php
   protected $namespace = 'App\\Http\\Controllers';
   ```

### バリデーションが検出されない

#### 症状
FormRequestを使用しているのに、パラメータがドキュメントに表示されない。

#### 解決方法

1. **タイプヒントの確認**
   ```php
   // ❌ 間違い
   public function store(Request $request)
   
   // ✅ 正しい
   public function store(StoreUserRequest $request)
   ```

2. **FormRequestの構造確認**
   ```php
   class StoreUserRequest extends FormRequest
   {
       public function authorize()
       {
           return true; // falseだと解析されない
       }
       
       public function rules()
       {
           return [
               'name' => 'required|string',
               // ...
           ];
       }
   }
   ```

3. **インラインバリデーションの場合**
   ```php
   public function store(Request $request)
   {
       // メソッドの最初に配置
       $validated = $request->validate([
           'name' => 'required|string',
       ]);
   }
   ```

### メモリ不足エラー

#### 症状
```
Fatal error: Allowed memory size of 134217728 bytes exhausted
```

#### 解決方法

1. **一時的な解決**
   ```bash
   php -d memory_limit=1G artisan spectrum:generate
   ```

2. **恒久的な解決**
   ```php
   // php.ini
   memory_limit = 512M
   ```

3. **最適化コマンドの使用**
   ```bash
   php artisan spectrum:generate:optimized --chunk-size=50
   ```

4. **不要なルートの除外**
   ```php
   'excluded_routes' => [
       'telescope/*',
       'horizon/*',
       '_debugbar/*',
   ],
   ```

### ファイルアップロードが正しく表示されない

#### 症状
ファイルフィールドが通常の文字列として表示される。

#### 解決方法

1. **バリデーションルールの確認**
   ```php
   'avatar' => 'required|file|image|max:2048',
   'document' => 'required|mimes:pdf,doc,docx',
   'images.*' => 'image|max:1024',
   ```

2. **Content-Typeの確認**
   ```php
   // FormRequestに追加
   public function rules()
   {
       return [
           'file' => 'file', // 最低限 'file' ルールが必要
       ];
   }
   ```

### レスポンス構造が検出されない

#### 症状
APIリソースを使用しているのに、レスポンススキーマが空。

#### 解決方法

1. **リターン文の確認**
   ```php
   // ❌ 間違い
   return response()->json(new UserResource($user));
   
   // ✅ 正しい
   return new UserResource($user);
   ```

2. **リソースクラスの確認**
   ```php
   class UserResource extends JsonResource
   {
       public function toArray($request)
       {
           return [
               'id' => $this->id,
               'name' => $this->name,
               // 必ずデータを返す
           ];
       }
   }
   ```

### 認証が適用されない

#### 症状
認証が必要なエンドポイントなのに、ドキュメントに認証情報が表示されない。

#### 解決方法

1. **ミドルウェアの確認**
   ```php
   Route::middleware('auth:sanctum')->group(function () {
       Route::get('/profile', [ProfileController::class, 'show']);
   });
   ```

2. **設定の確認**
   ```php
   // config/spectrum.php
   'authentication' => [
       'middleware_map' => [
           'auth:sanctum' => 'bearer',
           'auth:api' => 'bearer',
           'auth' => 'bearer',
       ],
   ],
   ```

## 🔍 デバッグ方法

### 詳細ログの有効化

```php
// config/spectrum.php
'debug' => [
    'enabled' => true,
    'log_channel' => 'spectrum',
    'verbose' => true,
],
```

### デバッグコマンド

```bash
# 単一ルートのデバッグ
php artisan spectrum:debug api/users

# 詳細な出力
php artisan spectrum:generate -vvv

# ドライラン（ファイル生成なし）
php artisan spectrum:generate --dry-run
```

### ログファイルの確認

```bash
# Laravel ログ
tail -f storage/logs/laravel.log

# Spectrum専用ログ
tail -f storage/logs/spectrum.log
```

## ⚠️ エラーメッセージ対処法

### "Class not found" エラー

```bash
# Composerオートロードの再生成
composer dump-autoload

# キャッシュのクリア
php artisan cache:clear
php artisan config:clear
```

### "Cannot redeclare class" エラー

```bash
# opcacheのリセット
php artisan opcache:clear

# または開発環境でopcacheを無効化
# php.ini
opcache.enable=0
```

### パーミッションエラー

```bash
# ストレージディレクトリの権限設定
chmod -R 775 storage
chmod -R 775 bootstrap/cache
chown -R www-data:www-data storage

# SELinuxの場合
semanage fcontext -a -t httpd_sys_rw_content_t "/path/to/storage(/.*)?"
restorecon -Rv /path/to/storage
```

## 🛠️ 環境別の問題

### Docker環境

```dockerfile
# Dockerfile
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    && docker-php-ext-install zip

# メモリ制限の設定
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/memory.ini
```

### Laravel Sail

```bash
# Sailコンテナ内で実行
sail artisan spectrum:generate

# メモリ不足の場合
sail php -d memory_limit=1G artisan spectrum:generate
```

### Homestead/Vagrant

```yaml
# Homestead.yaml
sites:
    - map: api.test
      to: /home/vagrant/api/public
      php: "8.2"
      params:
          - key: memory_limit
            value: 512M
```

## 💻 IDE関連の問題

### PhpStorm

FormRequestが認識されない場合：

1. File → Invalidate Caches / Restart
2. Laravel IDEヘルパーの再生成：
   ```bash
   php artisan ide-helper:generate
   php artisan ide-helper:models
   ```

### VSCode

拡張機能の推奨：
- PHP Intelephense
- Laravel Extension Pack
- Laravel Blade Snippets

## 🚀 パフォーマンス問題

### 生成が遅い

1. **キャッシュの有効化**
   ```php
   'cache' => [
       'enabled' => true,
   ],
   ```

2. **並列処理の使用**
   ```bash
   php artisan spectrum:generate:optimized
   ```

3. **不要な機能の無効化**
   ```php
   'features' => [
       'example_generation' => false,
       'deep_analysis' => false,
   ],
   ```

### ファイルサイズが大きすぎる

1. **出力の分割**
   ```bash
   php artisan spectrum:generate --split-by-tag
   ```

2. **不要な情報の除外**
   ```php
   'output' => [
       'include_examples' => false,
       'include_descriptions' => false,
   ],
   ```

## 📞 サポート

### 問題が解決しない場合

1. **イシューの作成**
    - [GitHub Issues](https://github.com/wadakatu/laravel-spectrum/issues)
    - エラーメッセージ全文を含める
    - 環境情報を記載（PHP/Laravel/Spectrumのバージョン）

2. **デバッグ情報の収集**
   ```bash
   php artisan spectrum:info > debug-info.txt
   ```

3. **最小限の再現コード**
   問題を再現できる最小限のコードサンプルを提供

### よくある質問（FAQ）

**Q: Lumenでも使えますか？**
A: はい、Lumen 10.x以降で使用できます。`bootstrap/app.php`でサービスプロバイダーを登録してください。

**Q: 既存のSwaggerアノテーションと併用できますか？**
A: 可能ですが、Laravel Spectrumはアノテーションを無視します。必要に応じて生成後に手動でマージしてください。

**Q: プライベートAPIのドキュメントを生成できますか？**
A: はい、認証ミドルウェアがあってもドキュメントは生成されます。アクセス制御は別途実装してください。

## 📚 関連ドキュメント

- [インストールと設定](./installation.md) - セットアップガイド
- [設定リファレンス](./config-reference.md) - 詳細な設定オプション
- [FAQ](./faq.md) - よくある質問と回答