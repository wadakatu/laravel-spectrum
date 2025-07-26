---
id: known-issues
title: 既知の問題
sidebar_label: 既知の問題
---

# 既知の問題

Laravel Spectrumの既知の問題と回避策をまとめています。これらの問題は将来のバージョンで修正される予定です。

## 🚨 重要な問題

### 1. 匿名クラスのFormRequest解析

**問題：**
匿名クラスとして定義されたFormRequestのバリデーションルールが正しく検出されない場合があります。

```php
// 検出されない可能性があるパターン
Route::post('/users', function (Request $request) {
    $validated = $request->validate(
        (new class extends FormRequest {
            public function rules() {
                return ['name' => 'required'];
            }
        })->rules()
    );
});
```

**回避策：**
通常のクラスとしてFormRequestを定義してください。

```php
// 推奨される方法
class StoreUserRequest extends FormRequest
{
    public function rules()
    {
        return ['name' => 'required'];
    }
}

Route::post('/users', [UserController::class, 'store']);
```

**ステータス：** v2.0で修正予定

### 2. 深くネストされた配列バリデーション

**問題：**
3階層以上の深さのネストされた配列バリデーションが完全に解析されない。

```php
// 3階層目以降が部分的にしか検出されない
public function rules()
{
    return [
        'data' => 'required|array',
        'data.*.items' => 'required|array',
        'data.*.items.*.details' => 'required|array',
        'data.*.items.*.details.*.value' => 'required|string', // これが検出されない可能性
    ];
}
```

**回避策：**
設定で解析の深さを増やすか、構造を平坦化してください。

```php
// config/spectrum.php
'analysis' => [
    'max_depth' => 5, // デフォルトは3
],
```

**ステータス：** 調査中

## ⚠️ 制限事項

### 1. 動的ルート登録

**問題：**
実行時に動的に登録されるルートが検出されない。

```php
// 検出されないパターン
if (config('features.new_api')) {
    Route::post('/new-endpoint', [NewController::class, 'store']);
}

// データベースから動的にルートを生成
foreach (Module::active() as $module) {
    Route::prefix($module->slug)->group($module->routes);
}
```

**回避策：**
静的なルート定義を使用するか、カスタムアナライザーを作成してください。

```php
// カスタムアナライザーの例
class DynamicRouteAnalyzer implements Analyzer
{
    public function analyze($target): array
    {
        // 動的ルートを解析するロジック
    }
}
```

**ステータス：** 仕様上の制限

### 2. カスタムミドルウェアの認証検出

**問題：**
標準的でないカスタムミドルウェアの認証要件が自動検出されない。

```php
// 検出されないパターン
Route::middleware('custom.auth:special')->group(function () {
    Route::get('/protected', [Controller::class, 'index']);
});
```

**回避策：**
ミドルウェアマッピングを設定してください。

```php
// config/spectrum.php
'authentication' => [
    'middleware_map' => [
        'custom.auth' => 'bearer',
        'custom.auth:special' => 'apiKey',
    ],
],
```

**ステータス：** ドキュメント化済み

## 🐛 バグ

### 1. Enum型の配列バリデーション

**問題：**
Enum型の配列バリデーションで、個々の値のEnum制約が検出されない。

```php
use App\Enums\StatusEnum;

public function rules()
{
    return [
        // 配列全体のバリデーションは検出される
        'statuses' => ['required', 'array'],
        // 個々の要素のEnum制約が検出されない
        'statuses.*' => ['required', Rule::enum(StatusEnum::class)],
    ];
}
```

**回避策：**
`in`ルールを併用してください。

```php
'statuses.*' => [
    'required',
    Rule::enum(StatusEnum::class),
    Rule::in(StatusEnum::cases()), // これも追加
],
```

**ステータス：** v1.3で修正予定

### 2. Fractalのインクルード検出

**問題：**
Fractalの`availableIncludes`が条件付きの場合、正しく検出されない。

```php
class UserTransformer extends TransformerAbstract
{
    public function __construct(private bool $isAdmin)
    {
        $this->availableIncludes = $isAdmin 
            ? ['posts', 'comments', 'privateData']
            : ['posts', 'comments'];
    }
}
```

**回避策：**
静的なプロパティとして定義してください。

```php
protected array $availableIncludes = ['posts', 'comments', 'privateData'];

public function includePrivateData(User $user)
{
    if (!$this->isAdmin) {
        return null;
    }
    return $this->item($user->privateData, new PrivateDataTransformer);
}
```

**ステータス：** 修正中

## 💻 環境固有の問題

### 1. Windows環境でのファイル監視

**問題：**
Windows環境で`spectrum:watch`コマンドがファイル変更を検出しない。

**回避策：**
ポーリングモードを使用してください。

```bash
php artisan spectrum:watch --poll
```

または設定で有効化：

```php
// config/spectrum.php
'watch' => [
    'polling' => [
        'enabled' => true,
        'interval' => 1000,
    ],
],
```

**ステータス：** プラットフォーム制限

### 2. Dockerコンテナ内でのパフォーマンス

**問題：**
Docker for Mac/Windowsでボリュームマウントを使用すると生成が遅い。

**回避策：**
1. キャッシュを積極的に使用
2. 生成をコンテナ内で実行
3. 結果のみをホストにコピー

```dockerfile
# Dockerfile
RUN php artisan spectrum:generate && \
    cp -r storage/app/spectrum /tmp/spectrum

# docker-compose.yml
volumes:
  - ./storage/app/spectrum:/tmp/spectrum
```

**ステータス：** Docker側の制限

## 🔧 パフォーマンス問題

### 1. 大規模プロジェクトでのメモリ使用

**問題：**
1000以上のルートがあるプロジェクトでメモリ不足が発生する可能性。

**回避策：**
最適化コマンドを使用：

```bash
php artisan spectrum:generate:optimized --chunk-size=50
```

**ステータス：** 継続的に改善中

### 2. 循環参照の検出

**問題：**
リソース間の循環参照がスタックオーバーフローを引き起こす可能性。

```php
// 循環参照の例
class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'posts' => PostResource::collection($this->posts),
        ];
    }
}

class PostResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'author' => new UserResource($this->author), // 循環参照
        ];
    }
}
```

**回避策：**
最大深度を設定するか、条件付きロードを使用：

```php
'author' => $this->when(!$request->has('no_author'), function () {
    return new UserResource($this->author);
}),
```

**ステータス：** v1.4で改善予定

## 📝 マイナーな問題

### 1. カスタム日付フォーマット

**問題：**
カスタム日付フォーマットが`date-time`形式として認識されない。

```php
'created_at' => $this->created_at->format('Y年m月d日'),
```

**回避策：**
ISO 8601形式を使用するか、カスタムフォーマットを文字列として扱ってください。

### 2. 日本語フィールド名

**問題：**
日本語のフィールド名がOpenAPIで正しくエスケープされない場合がある。

**回避策：**
英語のフィールド名を使用し、`description`に日本語を記載：

```php
public function attributes()
{
    return [
        'name' => '名前',
        'email' => 'メールアドレス',
    ];
}
```

## 🔄 更新情報

### 最近修正された問題

- **v1.2.0**: ページネーションのメタデータ検出を改善
- **v1.1.5**: Lumenでのサービスプロバイダー登録問題を修正
- **v1.1.4**: PHP 8.2での非推奨警告を修正

### 修正予定

- **v1.3.0**: Enum配列バリデーションの完全サポート
- **v1.4.0**: 循環参照の自動検出と処理
- **v2.0.0**: 匿名クラスの完全サポート

## 📞 問題の報告

新しい問題を発見した場合は、以下の情報と共に報告してください：

1. **環境情報**
   ```bash
   php artisan spectrum:info
   ```

2. **再現手順**
    - 最小限の再現コード
    - 期待される動作
    - 実際の動作

3. **エラーログ**
   ```bash
   tail -n 100 storage/logs/laravel.log
   ```

**報告先：** [GitHub Issues](https://github.com/wadakatu/laravel-spectrum/issues)

## 📚 関連ドキュメント

- [トラブルシューティング](./troubleshooting.md) - 一般的な問題の解決
- [FAQ](./faq.md) - よくある質問