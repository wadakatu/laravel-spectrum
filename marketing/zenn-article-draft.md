---
title: "LaravelのAPIドキュメントを自動生成する「Laravel Spectrum」を作った"
emoji: "🌈"
type: "tech"
topics: ["Laravel", "PHP", "OpenAPI", "Swagger", "API"]
published: false
---

# はじめに

LaravelでAPIを開発していると、必ずぶつかる問題があります。

**APIドキュメント、どうやって管理していますか？**

選択肢としてはこんなところでしょうか：

1. **Swagger-PHP** でアノテーションを書く → 面倒すぎる
2. **Scribe** を使う → 設定が複雑
3. **手書きでドキュメント** → すぐ古くなる
4. **ドキュメントなし** → チーム開発で詰む

私は1のSwagger-PHPを使っていたのですが、こんなコードを見るたびに心が折れていました：

```php
/**
 * @OA\Post(
 *     path="/api/users",
 *     summary="ユーザー作成",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"name", "email", "password"},
 *             @OA\Property(property="name", type="string", example="John"),
 *             @OA\Property(property="email", type="string", format="email"),
 *             @OA\Property(property="password", type="string", minLength=8),
 *         )
 *     ),
 *     @OA\Response(response="201", description="Created")
 * )
 */
public function store(StoreUserRequest $request)
{
    // ...
}
```

**「いや、この情報全部FormRequestに書いてあるじゃん」**

そう思ったのがきっかけで、**Laravel Spectrum** を作りました。

# Laravel Spectrum とは

**既存のLaravelコードを解析して、OpenAPI（Swagger）ドキュメントを自動生成する**ツールです。

https://github.com/wadakatu/laravel-spectrum

最大の特徴は **アノテーション不要** ということ。

FormRequestやAPI Resourceなど、すでに書いてあるコードから自動的に情報を抽出します。

# クイックスタート（30秒）

```bash
# インストール
composer require wadakatu/laravel-spectrum --dev

# ドキュメント生成
php artisan spectrum:generate

# 完了！ storage/app/spectrum/openapi.json に出力されます
```

これだけです。設定ファイルすら不要で動きます。

# 何が自動検出されるのか

| Laravelのコード | 生成されるドキュメント |
|----------------|---------------------|
| `FormRequest::rules()` | リクエストボディのスキーマ |
| `$request->validate([...])` | インラインバリデーション |
| `API Resource` | レスポンススキーマ |
| 認証ミドルウェア | セキュリティスキーム |
| ルートパラメータ | パスパラメータ |
| `@deprecated` PHPDoc | 非推奨フラグ |

## 例：FormRequestからの自動生成

```php
class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'min:8', 'confirmed'],
        ];
    }
}
```

↓ これが自動的に以下のOpenAPIスキーマに変換されます：

```yaml
requestBody:
  content:
    application/json:
      schema:
        type: object
        required: [name, email, password]
        properties:
          name:
            type: string
            maxLength: 255
          email:
            type: string
            format: email
          password:
            type: string
            minLength: 8
          password_confirmation:
            type: string
```

`confirmed` ルールから `password_confirmation` フィールドまで自動生成されます。

# 主な機能

## 1. リアルタイムプレビュー

```bash
php artisan spectrum:watch
```

コードを変更すると、ブラウザが自動的にリロードされてドキュメントが更新されます。

## 2. モックサーバー

```bash
php artisan spectrum:mock
```

生成したOpenAPIドキュメントから、動作するモックAPIサーバーを起動できます。
フロントエンドチームがバックエンドの完成を待たずに開発を進められます。

## 3. Postman/Insomniaエクスポート

```bash
php artisan spectrum:export postman
php artisan spectrum:export insomnia
```

API クライアント用のコレクションファイルも一発で生成。

## 4. HTML出力

```bash
php artisan spectrum:generate --format=html
```

Swagger UI付きのHTMLファイルを生成。そのまま共有できます。

# 他のツールとの比較

| 機能 | Laravel Spectrum | Swagger-PHP | Scribe |
|------|:---:|:---:|:---:|
| アノテーション不要 | ✅ | ❌ | △ |
| セットアップ時間 | 30秒 | 数時間 | 30分 |
| FormRequest解析 | ✅ | ❌ | ✅ |
| モックサーバー | ✅ | ❌ | ❌ |
| ライブリロード | ✅ | ❌ | ❌ |
| OpenAPI 3.1対応 | ✅ | ✅ | ❌ |

# 動作要件

- PHP 8.2以上
- Laravel 11.x または 12.x

# まとめ

Laravel Spectrumは「**すでに書いてあるコードがドキュメント**」という思想で作りました。

アノテーションを書く時間をコード開発に使えるようになれば嬉しいです。

GitHub: https://github.com/wadakatu/laravel-spectrum

ぜひ試してみてください。フィードバックやIssue、PRも歓迎です！

---

**追記**: この記事を読んで使ってみた方は、ぜひコメントで感想を教えてください 🙏
