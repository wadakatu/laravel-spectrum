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

| オプション | デフォルト | 説明 |
|-----------|-----------|------|
| `--output` | storage/app/spectrum/openapi.json | 出力ファイルパス |
| `--format` | json | 出力形式（json/yaml/html） |
| `--no-cache` | false | キャッシュを使用しない |
| `--clear-cache` | false | 生成前にキャッシュをクリア |
| `--fail-on-error` | false | 最初のエラーで実行を停止 |
| `--ignore-errors` | false | エラーを無視して生成を続行 |
| `--error-report` | なし | エラーレポートをファイルに保存 |
| `--no-try-it-out` | false | HTML出力の"Try It Out"機能を無効化 |

### 使用例

```bash
# 基本的な生成
php artisan spectrum:generate

# YAML形式で出力
php artisan spectrum:generate --format=yaml --output=docs/api.yaml

# HTML形式で出力（Swagger UI付き）
php artisan spectrum:generate --format=html --output=docs/api.html

# キャッシュをクリアして再生成
php artisan spectrum:generate --clear-cache

# キャッシュを使用せず生成
php artisan spectrum:generate --no-cache

# エラー時に停止
php artisan spectrum:generate --fail-on-error

# エラーを無視して続行
php artisan spectrum:generate --ignore-errors

# エラーレポートを保存
php artisan spectrum:generate --error-report=storage/spectrum-errors.json

# 詳細な出力で生成
php artisan spectrum:generate -vvv
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
| `--format` | json | 出力形式（json/yaml） |
| `--output` | storage/app/spectrum/openapi.json | 出力ファイルパス |
| `--parallel` | false | 並列処理を有効化 |
| `--workers` | auto | 並列ワーカー数（autoでCPUコア数） |
| `--chunk-size` | auto | 各ワーカーが処理するルート数 |
| `--memory-limit` | 512M | 各ワーカーのメモリ制限 |
| `--incremental` | false | 変更されたファイルのみ処理 |

### 使用例

```bash
# 自動最適化で生成
php artisan spectrum:generate:optimized

# 並列処理を有効化
php artisan spectrum:generate:optimized --parallel

# 8ワーカーで並列処理
php artisan spectrum:generate:optimized --parallel --workers=8

# メモリとチャンクサイズの調整
php artisan spectrum:generate:optimized --memory-limit=1G --chunk-size=50

# インクリメンタル生成
php artisan spectrum:generate:optimized --incremental

# YAML形式で出力
php artisan spectrum:generate:optimized --format=yaml

# 詳細な出力で生成
php artisan spectrum:generate:optimized -v
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
| `--host` | 127.0.0.1 | プレビューサーバーのホスト |
| `--no-open` | false | ブラウザを自動で開かない |

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
| `--output` | storage/app/spectrum/postman | 出力ディレクトリ |
| `--environments` | local | エクスポートする環境（カンマ区切り） |
| `--single-file` | false | 環境を埋め込んだ単一ファイルでエクスポート |

### 使用例

```bash
# 基本的なエクスポート
php artisan spectrum:export:postman

# カスタム出力先
php artisan spectrum:export:postman --output=postman/

# 複数環境をエクスポート
php artisan spectrum:export:postman --environments=local,staging,production

# 単一ファイルでエクスポート
php artisan spectrum:export:postman --single-file
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
| `--output` | storage/app/spectrum/insomnia/insomnia_collection.json | 出力ファイルパス |

### 使用例

```bash
# 基本的なエクスポート
php artisan spectrum:export:insomnia

# カスタム出力先（ファイルパス）
php artisan spectrum:export:insomnia --output=insomnia/api.json

# カスタム出力先（ディレクトリ）
php artisan spectrum:export:insomnia --output=insomnia/
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
	php artisan spectrum:export:postman --environments=local
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
