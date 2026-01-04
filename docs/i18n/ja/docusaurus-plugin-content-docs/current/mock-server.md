---
id: mock-server
title: モックサーバー機能ガイド
sidebar_label: モックサーバー機能ガイド
---

# モックサーバー機能ガイド

Laravel Spectrumのモックサーバー機能を使用すると、生成されたOpenAPIドキュメントから自動的にモックAPIサーバーを起動できます。これにより、フロントエンド開発やAPI統合テストを実際のバックエンドなしで行うことができます。

## 🎭 概要

モックサーバーは以下の機能を提供します：

- **自動レスポンス生成** - OpenAPIスキーマに基づいてリアルなレスポンスを生成
- **認証シミュレーション** - Bearer Token、API Key、Basic認証をシミュレート
- **バリデーション** - リクエストパラメータの検証
- **シナリオベースレスポンス** - 成功/エラーなど複数のシナリオに対応
- **レスポンス遅延** - ネットワーク遅延のシミュレーション

## 🚀 基本的な使い方

### モックサーバーの起動

```bash
# デフォルト設定で起動
php artisan spectrum:mock

# カスタムポートで起動
php artisan spectrum:mock --port=3000

# カスタムホストとポート
php artisan spectrum:mock --host=0.0.0.0 --port=8080
```

### 起動時の出力例

```
🚀 Starting Laravel Spectrum Mock Server...
📄 Loading spec from: storage/app/spectrum/openapi.json

🎭 Mock Server Configuration:
+------------------+-------------------------+
| Setting          | Value                   |
+------------------+-------------------------+
| API Title        | Laravel API             |
| API Version      | 1.0.0                   |
| Server URL       | http://127.0.0.1:8081   |
| Total Endpoints  | 24                      |
| Default Scenario | success                 |
+------------------+-------------------------+

📋 Available Endpoints:
+--------+------------------------+--------------------------------+
| Method | Path                   | Description                    |
+--------+------------------------+--------------------------------+
| GET    | /api/users             | List all users                 |
| POST   | /api/users             | Create a new user              |
| GET    | /api/users/{id}        | Get user by ID                 |
| PUT    | /api/users/{id}        | Update user                    |
| DELETE | /api/users/{id}        | Delete user                    |
+--------+------------------------+--------------------------------+

🎯 Mock server running at http://127.0.0.1:8081
Press Ctrl+C to stop
```

## 🔧 コマンドオプション

### 利用可能なオプション

```bash
php artisan spectrum:mock [options]
```

| オプション | デフォルト | 説明 |
|-----------|----------|------|
| `--host` | 127.0.0.1 | バインドするホストアドレス |
| `--port` | 8081 | リッスンするポート番号 |
| `--spec` | storage/app/spectrum/openapi.json | OpenAPI仕様ファイルのパス |
| `--delay` | なし | レスポンス遅延（ミリ秒） |
| `--scenario` | success | デフォルトのレスポンスシナリオ |

### 使用例

```bash
# カスタムOpenAPIファイルを使用
php artisan spectrum:mock --spec=docs/api-spec.json

# 500msの遅延を追加
php artisan spectrum:mock --delay=500

# エラーシナリオをデフォルトに設定
php artisan spectrum:mock --scenario=error
```

## 🎯 レスポンスシナリオ

### シナリオの指定方法

クエリパラメータ `_scenario` を使用してレスポンスシナリオを動的に切り替えられます：

```bash
# 成功レスポンス
curl http://localhost:8081/api/users?_scenario=success

# エラーレスポンス
curl http://localhost:8081/api/users?_scenario=error

# 空のレスポンス
curl http://localhost:8081/api/users?_scenario=empty
```

### 利用可能なシナリオ

- **success** - 正常なレスポンス（デフォルト）
- **error** - エラーレスポンス（通常500エラー）
- **empty** - 空のデータレスポンス
- **unauthorized** - 認証エラー（401）
- **forbidden** - 権限エラー（403）
- **not_found** - リソースが見つからない（404）
- **validation_error** - バリデーションエラー（422）

## 🔐 認証シミュレーション

### Bearer Token認証

```bash
# 有効なトークンでリクエスト
curl -H "Authorization: Bearer mock-token-123" \
     http://localhost:8081/api/protected/resource

# 無効なトークンでリクエスト（401エラー）
curl -H "Authorization: Bearer invalid-token" \
     http://localhost:8081/api/protected/resource
```

### API Key認証

```bash
# ヘッダーでAPI Keyを送信
curl -H "X-API-Key: mock-api-key-123" \
     http://localhost:8081/api/protected/resource

# クエリパラメータでAPI Keyを送信
curl http://localhost:8081/api/protected/resource?api_key=mock-api-key-123
```

### Basic認証

```bash
# Basic認証でリクエスト
curl -u username:password \
     http://localhost:8081/api/protected/resource
```

### 認証トークンのモック

モックサーバーは以下のトークンを有効として認識します：
- Bearer: `mock-token-*` のパターン
- API Key: `mock-api-key-*` のパターン
- Basic: 任意のユーザー名/パスワード

## 📝 バリデーションシミュレーション

モックサーバーはOpenAPIスキーマに基づいてリクエストを検証します：

### 必須フィールドの検証

```bash
# 必須フィールドが不足している場合（422エラー）
curl -X POST http://localhost:8081/api/users \
     -H "Content-Type: application/json" \
     -d '{"email": "test@example.com"}'

# レスポンス
{
  "message": "The given data was invalid.",
  "errors": {
    "name": ["The name field is required."]
  }
}
```

### 型の検証

```bash
# 型が間違っている場合
curl -X POST http://localhost:8081/api/users \
     -H "Content-Type: application/json" \
     -d '{"name": "John", "age": "twenty"}'

# レスポンス
{
  "message": "The given data was invalid.",
  "errors": {
    "age": ["The age must be an integer."]
  }
}
```

## 🎨 レスポンスカスタマイズ

### 動的なレスポンス生成

モックサーバーは、OpenAPIスキーマから動的にレスポンスを生成します：

```yaml
# OpenAPIスキーマ例
responses:
  200:
    content:
      application/json:
        schema:
          type: object
          properties:
            id:
              type: integer
              example: 123
            name:
              type: string
              example: "John Doe"
            email:
              type: string
              format: email
            created_at:
              type: string
              format: date-time
```

生成されるレスポンス：

```json
{
  "id": 123,
  "name": "John Doe",
  "email": "john.doe@example.com",
  "created_at": "2024-01-15T10:30:00Z"
}
```

### ページネーションサポート

```bash
# ページネーションパラメータ
curl "http://localhost:8081/api/users?page=2&per_page=10"

# レスポンス
{
  "data": [...],
  "links": {
    "first": "http://localhost:8081/api/users?page=1",
    "last": "http://localhost:8081/api/users?page=5",
    "prev": "http://localhost:8081/api/users?page=1",
    "next": "http://localhost:8081/api/users?page=3"
  },
  "meta": {
    "current_page": 2,
    "from": 11,
    "to": 20,
    "total": 50,
    "per_page": 10,
    "last_page": 5
  }
}
```

## 🛠️ 高度な使用方法

### CI/CDでの使用

```yaml
# GitHub Actions例
- name: Start Mock Server
  run: |
    php artisan spectrum:generate
    php artisan spectrum:mock --host=0.0.0.0 --port=8080 &
    sleep 5

- name: Run Frontend Tests
  run: |
    npm test
  env:
    API_URL: http://localhost:8080
```

### Dockerでの使用

```dockerfile
# Dockerfile
FROM php:8.2-cli

# ... 他の設定 ...

EXPOSE 8081

CMD ["php", "artisan", "spectrum:mock", "--host=0.0.0.0"]
```

```yaml
# docker-compose.yml
services:
  mock-api:
    build: .
    ports:
      - "8081:8081"
    volumes:
      - ./storage/app/spectrum:/app/storage/app/spectrum
    command: php artisan spectrum:mock --host=0.0.0.0
```

### 複数バージョンのモック

```bash
# v1 API
php artisan spectrum:mock --spec=docs/v1/openapi.json --port=8081

# v2 API
php artisan spectrum:mock --spec=docs/v2/openapi.json --port=8082
```

## 💡 ベストプラクティス

### 1. 開発環境での使用

```bash
# package.jsonに追加
{
  "scripts": {
    "mock-api": "php artisan spectrum:mock",
    "dev": "concurrently \"npm run mock-api\" \"npm run serve\""
  }
}
```

### 2. テスト環境の設定

```javascript
// jest.config.js
module.exports = {
  setupFilesAfterEnv: ['./tests/setup.js'],
  testEnvironment: 'node',
  globals: {
    API_URL: 'http://localhost:8081'
  }
};
```

### 3. 環境変数の使用

```bash
# .env.testing
API_MOCK_HOST=0.0.0.0
API_MOCK_PORT=8081
API_MOCK_DELAY=100
```

```bash
# 環境変数を使用して起動
php artisan spectrum:mock \
  --host=$API_MOCK_HOST \
  --port=$API_MOCK_PORT \
  --delay=$API_MOCK_DELAY
```

## 🔍 トラブルシューティング

### ポートが使用中

```bash
# エラー: Address already in use
# 解決策: 別のポートを使用
php artisan spectrum:mock --port=8082
```

### OpenAPIファイルが見つからない

```bash
# エラー: OpenAPI specification file not found
# 解決策: ドキュメントを生成
php artisan spectrum:generate
php artisan spectrum:mock
```

### CORS エラー

モックサーバーはデフォルトでCORSを許可しています。問題がある場合は、ブラウザの開発者ツールでネットワークタブを確認してください。

## 📚 関連ドキュメント

- [基本的な使い方](./basic-usage.md) - ドキュメント生成の基本
- [エクスポート機能](./export.md) - Postman/Insomniaへのエクスポート
- [CI/CD統合](./ci-cd-integration.md) - 継続的インテグレーション