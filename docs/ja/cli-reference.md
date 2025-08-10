---
id: cli-reference
title: CLIコマンドリファレンス
sidebar_label: CLIコマンドリファレンス
---

# CLIコマンドリファレンス

Laravel Spectrumで利用可能なすべてのArtisanコマンドとオプションの詳細なリファレンスです。

## 📋 コマンド一覧

| コマンド | 説明 |
|---------|-----|
| `spectrum:generate` | OpenAPIドキュメントを生成 |
| `spectrum:generate:optimized` | 最適化された生成（大規模プロジェクト向け） |
| `spectrum:watch` | リアルタイムプレビューモード |
| `spectrum:mock` | モックAPIサーバーを起動 |
| `spectrum:export:postman` | Postmanコレクションにエクスポート |
| `spectrum:export:insomnia` | Insomniaワークスペースにエクスポート |
| `spectrum:cache` | キャッシュ管理（クリア、統計、ウォームアップ） |

## 🔧 spectrum:generate

APIドキュメントを生成する基本コマンドです。

### 使用方法

```bash
php artisan spectrum:generate [options]
```

### オプション

| オプション | 短縮形 | デフォルト | 説明 |
|-----------|--------|-----------|------|
| `--output` | `-o` | storage/app/spectrum/openapi.json | 出力ファイルパス |
| `--format` | `-f` | json | 出力形式（json/yaml） |
| `--pattern` | | config値 | 含めるルートパターン |
| `--exclude` | | config値 | 除外するルートパターン |
| `--no-cache` | | false | キャッシュを使用しない |
| `--force` | | false | 既存ファイルを上書き |
| `--dry-run` | | false | ファイル生成なしで実行 |
| `--incremental` | `-i` | false | 変更されたファイルのみ処理 |

### 使用例

```bash
# 基本的な生成
php artisan spectrum:generate

# 特定のパターンのみ生成
php artisan spectrum:generate --pattern="api/v2/*"

# 複数パターンの指定
php artisan spectrum:generate --pattern="api/users/*" --pattern="api/posts/*"

# 除外パターンの指定
php artisan spectrum:generate --exclude="api/admin/*" --exclude="api/debug/*"

# YAML形式で出力
php artisan spectrum:generate --format=yaml --output=docs/api.yaml

# キャッシュなしで強制再生成
php artisan spectrum:generate --no-cache --force

# ドライラン（実際には生成しない）
php artisan spectrum:generate --dry-run -vvv
```

## ⚡ spectrum:generate:optimized

大規模プロジェクト向けの最適化された生成コマンドです。

### 使用方法

```bash
php artisan spectrum:generate:optimized [options]
```

### オプション

| オプション | デフォルト | 説明 |
|-----------|-----------|------|
| `--workers` | auto | 並列ワーカー数（autoでCPUコア数） |
| `--chunk-size` | 100 | 各ワーカーが処理するルート数 |
| `--memory-limit` | 512M | 各ワーカーのメモリ制限 |
| `--incremental` | false | 変更されたファイルのみ処理 |
| `--progress` | true | 進捗バーを表示 |
| `--stats` | true | パフォーマンス統計を表示 |

### 使用例

```bash
# 自動最適化で生成
php artisan spectrum:generate:optimized

# 8ワーカーで並列処理
php artisan spectrum:generate:optimized --workers=8

# メモリとチャンクサイズの調整
php artisan spectrum:generate:optimized --memory-limit=1G --chunk-size=50

# インクリメンタル生成
php artisan spectrum:generate:optimized --incremental

# 統計なしで静かに実行
php artisan spectrum:generate:optimized --no-stats --no-progress
```

## 👁️ spectrum:watch

ファイル変更を監視してリアルタイムでドキュメントを更新します。

### 使用方法

```bash
php artisan spectrum:watch [options]
```

### オプション

| オプション | デフォルト | 説明 |
|-----------|-----------|------|
| `--port` | 8080 | プレビューサーバーのポート |
| `--host` | localhost | プレビューサーバーのホスト |
| `--no-open` | false | ブラウザを自動で開かない |
| `--poll` | false | ポーリングモードを使用 |
| `--interval` | 1000 | ポーリング間隔（ミリ秒） |

### 使用例

```bash
# 基本的な使用
php artisan spectrum:watch

# カスタムポートで起動
php artisan spectrum:watch --port=3000

# ブラウザを開かずに起動
php artisan spectrum:watch --no-open

# 外部アクセス可能にする
php artisan spectrum:watch --host=0.0.0.0

# ポーリングモード（Docker環境など）
php artisan spectrum:watch --poll --interval=2000
```

## 🎭 spectrum:mock

OpenAPIドキュメントに基づいてモックAPIサーバーを起動します。

### 使用方法

```bash
php artisan spectrum:mock [options]
```

### オプション

| オプション | デフォルト | 説明 |
|-----------|-----------|------|
| `--host` | 127.0.0.1 | バインドするホストアドレス |
| `--port` | 8081 | リッスンするポート番号 |
| `--spec` | storage/app/spectrum/openapi.json | OpenAPI仕様ファイルのパス |
| `--delay` | なし | レスポンス遅延（ミリ秒） |
| `--scenario` | success | デフォルトのレスポンスシナリオ |

### 使用例

```bash
# 基本的な起動
php artisan spectrum:mock

# カスタムポートとホスト
php artisan spectrum:mock --host=0.0.0.0 --port=3000

# レスポンス遅延の追加
php artisan spectrum:mock --delay=500

# エラーシナリオをデフォルトに
php artisan spectrum:mock --scenario=error

# カスタムOpenAPIファイル
php artisan spectrum:mock --spec=docs/custom-api.json
```

## 📤 spectrum:export:postman

PostmanコレクションとしてAPIドキュメントをエクスポートします。

### 使用方法

```bash
php artisan spectrum:export:postman [options]
```

### オプション

| オプション | デフォルト | 説明 |
|-----------|-----------|------|
| `--output` | storage/app/spectrum/postman/collection.json | 出力ファイルパス |
| `--include-examples` | true | リクエスト/レスポンス例を含める |
| `--include-tests` | false | テストスクリプトを生成 |
| `--environment` | false | 環境変数ファイルも生成 |
| `--base-url` | APP_URL | ベースURL |

### 使用例

```bash
# 基本的なエクスポート
php artisan spectrum:export:postman

# テストスクリプト付きでエクスポート
php artisan spectrum:export:postman --include-tests

# 環境変数ファイルも生成
php artisan spectrum:export:postman --environment

# カスタム出力先
php artisan spectrum:export:postman --output=postman/my-api.json

# 完全なエクスポート
php artisan spectrum:export:postman \
    --include-tests \
    --environment \
    --base-url=https://api.example.com
```

## 🦊 spectrum:export:insomnia

InsomniaワークスペースとしてAPIドキュメントをエクスポートします。

### 使用方法

```bash
php artisan spectrum:export:insomnia [options]
```

### オプション

| オプション | デフォルト | 説明 |
|-----------|-----------|------|
| `--output` | storage/app/spectrum/insomnia/workspace.json | 出力ファイルパス |
| `--workspace-name` | APP_NAME API | ワークスペース名 |
| `--include-environments` | true | 環境設定を含める |
| `--folder-structure` | true | フォルダ構造で整理 |

### 使用例

```bash
# 基本的なエクスポート
php artisan spectrum:export:insomnia

# カスタムワークスペース名
php artisan spectrum:export:insomnia --workspace-name="My Cool API"

# フォルダ構造なしでフラット
php artisan spectrum:export:insomnia --no-folder-structure

# カスタム出力先
php artisan spectrum:export:insomnia --output=insomnia/api.json
```

## 🗑️ spectrum:cache

Laravel Spectrumのキャッシュを管理します（クリア、統計表示、ウォームアップ）。

### 使用方法

```bash
php artisan spectrum:cache {action}
```

### アクション

| アクション | 説明 |
|-----------|------|
| `clear` | キャッシュされたすべてのドキュメントをクリア |
| `stats` | キャッシュ統計（サイズ、ファイル数など）を表示 |
| `warm` | キャッシュをクリアして再生成 |

### 使用例

```bash
# すべてのキャッシュをクリア
php artisan spectrum:cache clear

# キャッシュ統計を表示
php artisan spectrum:cache stats

# キャッシュをウォームアップ（クリア＆再生成）
php artisan spectrum:cache warm
```

## 🔍 グローバルオプション

すべてのコマンドで使用可能なLaravelのグローバルオプション：

| オプション | 短縮形 | 説明 |
|-----------|--------|------|
| `--help` | `-h` | ヘルプを表示 |
| `--quiet` | `-q` | 出力を抑制 |
| `--verbose` | `-v/-vv/-vvv` | 詳細度を増加 |
| `--version` | `-V` | バージョンを表示 |
| `--ansi` | | ANSI出力を強制 |
| `--no-ansi` | | ANSI出力を無効化 |
| `--no-interaction` | `-n` | 対話的な質問をしない |
| `--env` | | 環境を指定 |

## 💡 便利な使い方

### エイリアスの設定

```bash
# ~/.bashrc または ~/.zshrc に追加
alias specgen="php artisan spectrum:generate"
alias specwatch="php artisan spectrum:watch"
alias specmock="php artisan spectrum:mock"
```

### Makefileの活用

```makefile
# Makefile
.PHONY: docs docs-watch docs-mock

docs:
	php artisan spectrum:generate

docs-watch:
	php artisan spectrum:watch

docs-mock:
	php artisan spectrum:mock

docs-export:
	php artisan spectrum:export:postman --environment
	php artisan spectrum:export:insomnia
```

### npm scriptsとの統合

```json
{
  "scripts": {
    "api:docs": "php artisan spectrum:generate",
    "api:watch": "php artisan spectrum:watch",
    "api:mock": "php artisan spectrum:mock",
    "dev": "concurrently \"npm run api:mock\" \"npm run serve\""
  }
}
```

## 📚 関連ドキュメント

- [基本的な使い方](./basic-usage.md) - 基本的な使用方法
- [設定リファレンス](./config-reference.md) - 設定ファイルの詳細
- [トラブルシューティング](./troubleshooting.md) - 問題解決ガイド