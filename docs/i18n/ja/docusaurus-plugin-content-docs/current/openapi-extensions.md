# OpenAPI 拡張

Laravel Spectrum は、機能を強化するためにいくつかの OpenAPI 拡張を使用しています。このドキュメントでは、各拡張の出典、目的、各種ツールとの互換性について説明します。

## 概要

OpenAPI では `x-` プレフィックスを使用してベンダー固有の拡張を定義できます。Laravel Spectrum はこれらの拡張を以下の目的で使用しています：

- ドキュメントビューアの表示を強化
- Laravel 固有のメタデータを保存
- ファイルアップロードのバリデーション詳細をサポート

| 拡張 | 出典 | 目的 | 互換性 |
|------|------|------|--------|
| `x-tagGroups` | Redoc/Stoplight | タグのグループ化 | Redoc, Stoplight |
| `x-middleware` | Spectrum 内部 | ミドルウェア情報の保存 | モックサーバーのみ |
| `x-rate-limit` | Spectrum 内部 | レート制限情報 | モックサーバーのみ |
| `maxSize` | Spectrum カスタム | ファイルサイズ制限 | Spectrum のみ |
| `contentMediaType` | JSON Schema（非標準配置） | ファイル MIME タイプ | 部分的 |

### 出典カテゴリ

- **Redoc/Stoplight**: ドキュメントツールによって定義された業界標準の拡張
- **Spectrum 内部**: Laravel Spectrum が内部使用（モックサーバー）のために追加するメタデータ
- **Spectrum カスタム**: Laravel Spectrum が追加するカスタムプロパティ（標準拡張ではない）
- **JSON Schema**: OpenAPI 内の非標準の場所で使用される標準的な概念

## x-tagGroups

この拡張をサポートするドキュメントビューアで、関連するタグをグループ化します。

:::info 出典
**Redoc/Stoplight** - これは Redoc によって最初に定義され、Stoplight でもサポートされている業界標準の拡張です。Laravel Spectrum はこの拡張を定義しているのではなく、単に使用しています。
:::

### 配置場所

OpenAPI ドキュメントのルートレベル。

### スキーマ

```yaml
x-tagGroups:
  - name: string      # グループの表示名
    tags: string[]    # タグ名の配列
```

### 例

```yaml
x-tagGroups:
  - name: ユーザー管理
    tags:
      - User
      - Profile
      - Authentication
  - name: コンテンツ
    tags:
      - Post
      - Comment
```

### 設定

`config/spectrum.php` でタググループを有効にします：

```php
'tag_groups' => [
    'enabled' => true,
    'groups' => [
        'User Management' => ['User', 'Profile'],
        'Content' => ['Post', 'Comment'],
    ],
    'uncategorized_group_name' => 'Other',
],
```

### 互換性

| ツール | サポート |
|--------|----------|
| Redoc | 完全サポート |
| Swagger UI | 無視（影響なし） |
| Stoplight | 完全サポート |
| Postman | 無視 |

### 標準的な代替手段

タググループ化に相当する標準的な OpenAPI の仕様はありません。タグ自体は標準ですが、グループ化はベンダー拡張です。

---

## x-middleware

各オペレーションの Laravel ミドルウェア情報を保存します。認証シミュレーション用にモックサーバーで内部的に使用されます。

:::info 出典
**Spectrum 内部** - この拡張は Laravel Spectrum によって内部使用のために定義・使用されています。Laravel ルートミドルウェアのメタデータを保存し、主にモックサーバーで使用されます。外部ツールはこの拡張を無視します。
:::

### 配置場所

オペレーションレベル（パスアイテムメソッド内）。

### スキーマ

```yaml
x-middleware:
  - string  # ミドルウェア名またはクラス
```

### 例

```yaml
paths:
  /api/users:
    get:
      x-middleware:
        - auth:sanctum
        - throttle:60,1
        - verified
```

### 使用方法

この拡張はルートミドルウェアから自動的に設定され、主に以下で使用されます：

- **モックサーバー**: 認証要件をシミュレート
- **ドキュメント**: 認証が必要なエンドポイントを表示

### 互換性

| ツール | サポート |
|--------|----------|
| Redoc | 無視 |
| Swagger UI | 無視 |
| モックサーバー | 完全サポート |

### 標準的な代替手段

認証要件には `security` フィールドを使用します：

```yaml
paths:
  /api/users:
    get:
      security:
        - bearerAuth: []
```

Laravel Spectrum は最大限の互換性のために、標準の `security` フィールドと `x-middleware` の両方を生成します。

---

## x-rate-limit

オペレーションのレート制限設定を保存します。モックサーバーでスロットリングをシミュレートするために使用されます。

:::info 出典
**Spectrum 内部** - この拡張は Laravel Spectrum によって内部使用のために定義・使用されています。Laravel の `throttle` ミドルウェアからレート制限情報を抽出し、モックサーバーで使用されます。外部ツールはこの拡張を無視します。
:::

### 配置場所

オペレーションレベル（パスアイテムメソッド内）。

### スキーマ

```yaml
x-rate-limit:
  limit: integer    # 最大リクエスト数
  period: string    # 時間期間（例: "1 minute"）
```

### 例

```yaml
paths:
  /api/users:
    get:
      x-rate-limit:
        limit: 60
        period: 1 minute
```

### 互換性

| ツール | サポート |
|--------|----------|
| Redoc | 無視 |
| Swagger UI | 無視 |
| モックサーバー | 完全サポート |

### 標準的な代替手段

レート制限に相当する標準的な OpenAPI フィールドはありません。一部の API では `description` フィールドやレスポンスヘッダーでこれを文書化しています。

---

## maxSize（ファイルアップロード）

ファイルアップロードフィールドの最大ファイルサイズを示すカスタムプロパティ。

:::info 出典
**Spectrum カスタム** - これは Laravel Spectrum によって追加されるカスタムプロパティです。標準的な OpenAPI や JSON Schema のプロパティではありません。このプロパティを認識しないツールとの互換性のために、値は `description` フィールドにも含まれます。
:::

### 配置場所

スキーマプロパティレベル（ファイルフィールドの `properties` 内）。

### スキーマ

```yaml
maxSize: integer  # 最大サイズ（バイト単位）
```

### 例

```yaml
components:
  schemas:
    FileUploadRequest:
      type: object
      properties:
        document:
          type: string
          format: binary
          maxSize: 5242880  # 5MB（バイト）
          description: "PDFドキュメント（最大5MB）"
```

### ソース

この値は Laravel のバリデーションルールから抽出されます：

```php
$request->validate([
    'document' => 'required|file|max:5120',  // 5MB (5120 KB)
]);
```

### 互換性

| ツール | サポート |
|--------|----------|
| Redoc | 無視（description に表示） |
| Swagger UI | 無視（description に表示） |
| Postman | 無視 |

### 標準的な代替手段

OpenAPI 3.1 は文字列に対して `maxLength` をサポートしていますが、バイナリコンテンツには適用されません。推奨されるアプローチは `description` フィールドにサイズ制限を文書化することで、Laravel Spectrum はこれを自動的に行います：

```yaml
description: "PDFドキュメント（最大5MB）"
```

---

## contentMediaType（ファイルアップロード）

ファイルアップロードフィールドで許可される MIME タイプを示します。`contentMediaType` は JSON Schema の一部ですが、Laravel Spectrum は OpenAPI スキーマ内の非標準の場所で使用しています。

:::info 出典
**JSON Schema（非標準配置）** - `contentMediaType` は標準的な JSON Schema のキーワードですが、JSON Schema では通常異なる方法で使用されます。Laravel Spectrum は利便性のためにファイルアップロードプロパティに直接配置しています。標準的な OpenAPI アプローチでは `encoding` オブジェクトを使用しますが、Laravel Spectrum は互換性のためにこちらも生成します。
:::

### 配置場所

スキーマプロパティレベル（ファイルフィールドの `properties` 内）。

### スキーマ

```yaml
contentMediaType: string  # カンマ区切りの MIME タイプ
```

### 例

```yaml
components:
  schemas:
    ImageUploadRequest:
      type: object
      properties:
        avatar:
          type: string
          format: binary
          contentMediaType: "image/jpeg, image/png, image/gif"
          description: "プロフィール画像（JPEG、PNG、または GIF）"
```

### ソース

この値は Laravel のバリデーションルールから抽出されます：

```php
$request->validate([
    'avatar' => 'required|image|mimes:jpeg,png,gif',
]);
```

### 互換性

| ツール | サポート |
|--------|----------|
| Redoc | 部分的（表示される場合あり） |
| Swagger UI | 無視 |
| Postman | 無視 |

### 標準的な代替手段

OpenAPI 3.0+ では、特定のメディアタイプで `content` キーを使用する標準的なアプローチがあります：

```yaml
requestBody:
  content:
    multipart/form-data:
      schema:
        type: object
        properties:
          avatar:
            type: string
            format: binary
      encoding:
        avatar:
          contentType: image/jpeg, image/png, image/gif
```

Laravel Spectrum は最大限の互換性のために両方のアプローチを含めています。

---

## 拡張の無効化

カスタム拡張なしの OpenAPI 出力が必要な場合は、生成されたドキュメントを後処理できます：

```php
use LaravelSpectrum\Facades\Spectrum;

$openapi = Spectrum::generate();

// カスタム拡張を削除
unset($openapi['x-tagGroups']);

foreach ($openapi['paths'] as $path => $methods) {
    foreach ($methods as $method => $operation) {
        if (is_array($operation)) {
            unset($openapi['paths'][$path][$method]['x-middleware']);
            unset($openapi['paths'][$path][$method]['x-rate-limit']);
        }
    }
}

// スキーマから削除
if (isset($openapi['components']['schemas'])) {
    array_walk_recursive($openapi['components']['schemas'], function (&$value, $key) {
        if ($key === 'maxSize' || $key === 'contentMediaType') {
            $value = null;
        }
    });
}
```

## 関連ドキュメント

- [設定リファレンス](./config-reference.md) - タググループやその他のオプションの設定
- [モックサーバー](./mock-server.md) - 拡張がモックサーバーでどのように使用されるか
- [エクスポート形式](./export.md) - 異なる形式へのエクスポート
