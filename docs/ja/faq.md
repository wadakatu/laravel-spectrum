---
id: faq
title: よくある質問（FAQ）
sidebar_label: よくある質問（FAQ）
---

# よくある質問（FAQ）

Laravel Spectrumに関するよくある質問と回答をまとめました。

## 🚀 基本的な質問

### Q: Laravel Spectrumとは何ですか？

**A:** Laravel Spectrumは、Laravel向けのゼロアノテーションAPIドキュメント生成ツールです。既存のコードを解析して自動的にOpenAPI 3.0仕様のドキュメントを生成します。アノテーションやコメントを書く必要がなく、コードから直接ドキュメントを生成できます。

### Q: どのバージョンのLaravelに対応していますか？

**A:** 以下のバージョンに対応しています：
- Laravel 10.x、11.x、12.x
- PHP 8.1以上

### Q: 既存のプロジェクトに導入できますか？

**A:** はい、既存のプロジェクトに簡単に導入できます。コードの変更は不要で、Composerでインストールしてコマンドを実行するだけです：

```bash
composer require wadakatu/laravel-spectrum --dev
php artisan spectrum:generate
```

### Q: Swagger-PHPやScribeとの違いは何ですか？

**A:** 主な違いは以下の通りです：

| 特徴 | Laravel Spectrum | Swagger-PHP | Scribe |
|-----|-----------------|-------------|---------|
| アノテーション | 不要 | 必要 | 部分的に必要 |
| セットアップ時間 | 1分以内 | 数時間 | 数十分 |
| メンテナンス | 自動 | 手動 | 手動 |
| リアルタイム更新 | あり | なし | なし |
| モックサーバー | あり | なし | なし |

詳細は[他ツールとの比較](./comparison.md)をご覧ください。

## 📝 ドキュメント生成

### Q: どのような情報が自動的に検出されますか？

**A:** 以下の情報を自動的に検出します：

- **ルート情報**: HTTPメソッド、パス、ミドルウェア
- **リクエスト**: FormRequestバリデーション、インラインバリデーション、ファイルアップロード
- **レスポンス**: APIリソース、Fractalトランスフォーマー、ページネーション
- **認証**: Bearer Token、API Key、Basic認証、OAuth2
- **その他**: Enum制約、クエリパラメータ、条件付きバリデーション

### Q: FormRequestを使っていない場合でも動作しますか？

**A:** はい、動作します。以下のパターンも検出されます：

```php
// インラインバリデーション
public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string',
        'email' => 'required|email',
    ]);
}

// Validatorファサード
$validator = Validator::make($request->all(), [
    'title' => 'required|max:255',
]);
```

ただし、FormRequestの使用を推奨します。

### Q: カスタムレスポンスフォーマットに対応していますか？

**A:** はい、対応しています。APIリソース、Fractalトランスフォーマー、カスタムレスポンスクラスなど、様々なパターンを検出します：

```php
// APIリソース
return new UserResource($user);

// コレクション
return UserResource::collection($users);

// カスタムレスポンス
return response()->json([
    'data' => $users,
    'meta' => ['total' => $count],
]);
```

### Q: 条件付きバリデーションは検出されますか？

**A:** はい、検出されます。HTTPメソッドベースや動的な条件も対応：

```php
public function rules()
{
    $rules = ['name' => 'required'];
    
    if ($this->isMethod('POST')) {
        $rules['password'] = 'required|min:8';
    }
    
    return $rules;
}
```

## ⚡ パフォーマンス

### Q: 大規模プロジェクト（1000以上のルート）でも使えますか？

**A:** はい、最適化コマンドを使用することで高速に処理できます：

```bash
php artisan spectrum:generate:optimized --workers=8
```

詳細は[パフォーマンス最適化ガイド](./performance.md)をご覧ください。

### Q: 生成にどのくらい時間がかかりますか？

**A:** プロジェクトの規模によりますが、目安は以下の通りです：

- 100ルート以下: 数秒
- 100-500ルート: 10-30秒
- 500-1000ルート: 30秒-1分
- 1000ルート以上: 最適化コマンドで1-2分

### Q: メモリ不足エラーが発生します

**A:** 以下の方法で解決できます：

1. **一時的な解決**:
   ```bash
   php -d memory_limit=1G artisan spectrum:generate
   ```

2. **最適化コマンドの使用**:
   ```bash
   php artisan spectrum:generate:optimized --chunk-size=50
   ```

3. **不要なルートの除外**:
   ```php
   // config/spectrum.php
   'excluded_routes' => [
       'telescope/*',
       'horizon/*',
   ],
   ```

## 🔧 設定とカスタマイズ

### Q: 特定のルートを除外したい

**A:** 設定ファイルで除外パターンを指定できます：

```php
// config/spectrum.php
'excluded_routes' => [
    'api/internal/*',
    'api/debug/*',
    'api/health',
],
```

### Q: APIのバージョンごとに分けたい

**A:** ルートパターンでバージョンを指定できます：

```php
// config/spectrum.php
'route_patterns' => [
    'api/v1/*',
    'api/v2/*',
],
```

コマンドでも指定可能：

```bash
php artisan spectrum:generate --pattern="api/v1/*"
```

### Q: カスタム認証ミドルウェアを使っています

**A:** ミドルウェアマッピングを設定します：

```php
// config/spectrum.php
'authentication' => [
    'middleware_map' => [
        'custom-auth' => 'bearer',
        'api-key-auth' => 'apiKey',
    ],
],
```

### Q: 例データをカスタマイズしたい

**A:** カスタムジェネレーターを設定できます：

```php
// config/spectrum.php
'example_generation' => [
    'custom_generators' => [
        'user_id' => fn() => rand(1000, 9999),
        'email' => fn($faker) => $faker->companyEmail(),
        'status' => fn() => 'active',
    ],
],
```

## 🎭 モックサーバー

### Q: モックサーバーとは何ですか？

**A:** 生成されたOpenAPIドキュメントから自動的にモックAPIサーバーを起動する機能です。実際のバックエンドなしでフロントエンド開発やテストができます：

```bash
php artisan spectrum:mock
```

### Q: モックサーバーはどのような認証に対応していますか？

**A:** 以下の認証方式をシミュレートします：

- Bearer Token（JWT形式）
- API Key（ヘッダーまたはクエリ）
- Basic認証
- OAuth2（部分的）

### Q: モックサーバーのレスポンスをカスタマイズできますか？

**A:** シナリオパラメータで切り替えられます：

```bash
# 成功レスポンス
curl http://localhost:8081/api/users?_scenario=success

# エラーレスポンス
curl http://localhost:8081/api/users?_scenario=error

# 空レスポンス
curl http://localhost:8081/api/users?_scenario=empty
```

## 🚨 トラブルシューティング

### Q: ルートが検出されません

**A:** 以下を確認してください：

1. **ルートパターンの確認**:
   ```php
   // config/spectrum.php
   'route_patterns' => ['api/*'], // 'api/'ではなく'api/*'
   ```

2. **ルートキャッシュのクリア**:
   ```bash
   php artisan route:clear
   ```

3. **ルート一覧の確認**:
   ```bash
   php artisan route:list --path=api
   ```

### Q: バリデーションが検出されません

**A:** 以下を確認してください：

1. **FormRequestの`authorize()`メソッド**:
   ```php
   public function authorize()
   {
       return true; // falseだと検出されません
   }
   ```

2. **タイプヒントの確認**:
   ```php
   // ✅ 正しい
   public function store(StoreUserRequest $request)
   
   // ❌ 検出されない
   public function store(Request $request)
   ```

### Q: ドキュメントが更新されません

**A:** キャッシュをクリアしてください：

```bash
php artisan spectrum:cache:clear
php artisan spectrum:generate --no-cache
```

## 🔄 CI/CD

### Q: CI/CDパイプラインに組み込めますか？

**A:** はい、簡単に組み込めます：

```yaml
# GitHub Actions
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

### Q: ドキュメントを自動的に公開したい

**A:** GitHub Pagesやその他の静的ホスティングサービスで公開できます：

```yaml
- name: Deploy to GitHub Pages
  uses: peaceiris/actions-gh-pages@v3
  with:
    github_token: ${{ secrets.GITHUB_TOKEN }}
    publish_dir: ./storage/app/spectrum
```

## 📦 エクスポート

### Q: PostmanやInsomniaで使えますか？

**A:** はい、専用のエクスポートコマンドがあります：

```bash
# Postmanコレクション
php artisan spectrum:export:postman

# Insomniaワークスペース
php artisan spectrum:export:insomnia
```

### Q: エクスポートにテストスクリプトを含められますか？

**A:** はい、Postmanエクスポートでは自動的にテストスクリプトを生成できます：

```bash
php artisan spectrum:export:postman --include-tests
```

## 🤝 貢献

### Q: バグを見つけました

**A:** [GitHub Issues](https://github.com/wadakatu/laravel-spectrum/issues)で報告してください。以下の情報を含めてください：

- Laravelのバージョン
- PHPのバージョン
- Laravel Spectrumのバージョン
- エラーメッセージ（あれば）
- 再現手順

### Q: 機能リクエストがあります

**A:** [GitHub Issues](https://github.com/wadakatu/laravel-spectrum/issues)で提案してください。プルリクエストも歓迎します！

## 📚 その他

### Q: ライセンスは何ですか？

**A:** MITライセンスです。商用利用も可能です。

### Q: サポートはありますか？

**A:** 以下の方法でサポートを受けられます：

1. [ドキュメント](index.md)を確認
2. [GitHub Issues](https://github.com/wadakatu/laravel-spectrum/issues)で質問
3. [GitHub Discussions](https://github.com/wadakatu/laravel-spectrum/discussions)でディスカッション

### Q: 他の言語版はありますか？

**A:** 現在は日本語と英語のドキュメントがあります。他の言語への翻訳の貢献も歓迎します！

---

**質問が解決しない場合は、[GitHub Issues](https://github.com/wadakatu/laravel-spectrum/issues)でお気軽にお問い合わせください。**