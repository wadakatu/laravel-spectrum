---
id: performance
title: パフォーマンス最適化ガイド
sidebar_label: パフォーマンス最適化ガイド
---

# パフォーマンス最適化ガイド

大規模プロジェクトでLaravel Spectrumを最適に動作させるための設定とテクニックを説明します。

## 🚀 最適化コマンド

### 基本的な最適化生成

```bash
php artisan spectrum:generate:optimized
```

このコマンドは自動的に：
- 利用可能なCPUコアを検出
- 並列処理でルートを解析
- メモリ使用量を最適化
- 進捗状況をリアルタイム表示

### 詳細オプション

```bash
php artisan spectrum:generate:optimized \
    --workers=8 \
    --chunk-size=50 \
    --memory-limit=512M \
    --incremental
```

オプション説明：
- `--workers`: 並列ワーカー数（デフォルト: CPUコア数）
- `--chunk-size`: 各ワーカーが処理するルート数（デフォルト: 100）
- `--memory-limit`: 各ワーカーのメモリ制限
- `--incremental`: 変更されたファイルのみ処理

## 📊 パフォーマンス統計

生成完了後、以下のような統計が表示されます：

```
✅ Documentation generated successfully!

📊 Performance Statistics:
├─ Total routes processed: 1,247
├─ Generation time: 23.5 seconds
├─ Memory usage: 128 MB (peak: 256 MB)
├─ Cache hits: 892 (71.5%)
├─ Workers used: 8
└─ Average time per route: 18.8 ms
```

## ⚡ 最適化テクニック

### 1. インクリメンタル生成

ファイルの変更を追跡し、必要な部分のみ再生成：

```bash
# 初回は完全生成
php artisan spectrum:generate:optimized

# 以降は変更分のみ
php artisan spectrum:generate:optimized --incremental
```

### 2. キャッシュの活用

```php
// config/spectrum.php
'cache' => [
    'enabled' => true,
    'ttl' => null, // 無期限キャッシュ
    'directory' => storage_path('app/spectrum/cache'),
    
    // ファイル変更の追跡
    'watch_files' => [
        base_path('composer.json'),
        base_path('composer.lock'),
    ],
    
    // スマートキャッシュ無効化
    'smart_invalidation' => true,
],
```

### 3. メモリ最適化

```php
// config/spectrum.php
'performance' => [
    // ルートをチャンクで処理
    'chunk_processing' => true,
    'chunk_size' => 100,
    
    // メモリ制限
    'memory_limit' => '512M',
    
    // ガベージコレクション
    'gc_collect_cycles' => true,
    'gc_interval' => 100, // 100ルートごとにGC実行
],
```

### 4. 選択的生成

特定のルートパターンのみ生成：

```bash
# 特定のバージョンのみ
php artisan spectrum:generate --pattern="api/v2/*"

# 複数パターン
php artisan spectrum:generate --pattern="api/users/*" --pattern="api/posts/*"

# 除外パターン
php artisan spectrum:generate --exclude="api/admin/*" --exclude="api/debug/*"
```

## 🔧 設定の最適化

### 大規模プロジェクト向け設定

```php
// config/spectrum.php
return [
    // 基本設定
    'performance' => [
        'enabled' => true,
        'parallel_processing' => true,
        'workers' => env('SPECTRUM_WORKERS', 'auto'), // 'auto' でCPUコア数を使用
        'chunk_size' => env('SPECTRUM_CHUNK_SIZE', 100),
        'memory_limit' => env('SPECTRUM_MEMORY_LIMIT', '1G'),
    ],

    // 解析の最適化
    'analysis' => [
        'max_depth' => 3, // ネスト解析の深さを制限
        'skip_vendor' => true, // vendorディレクトリをスキップ
        'lazy_loading' => true, // 必要時のみファイルを読み込み
    ],

    // キャッシュ戦略
    'cache' => [
        'strategy' => 'aggressive', // 積極的なキャッシュ
        'segments' => [
            'routes' => 86400, // 24時間
            'schemas' => 3600, // 1時間
            'examples' => 7200, // 2時間
        ],
    ],
];
```

### リソース制限

```php
// 生成時のリソース制限
'limits' => [
    'max_routes' => 10000, // 最大ルート数
    'max_file_size' => '50M', // 最大出力ファイルサイズ
    'timeout' => 300, // タイムアウト（秒）
    'max_schema_depth' => 10, // スキーマの最大深さ
],
```

## 📈 ベンチマーク結果

### テスト環境
- CPU: 8コア
- RAM: 16GB
- ルート数: 1,000

| 手法 | 実行時間 | メモリ使用量 | 
|------|---------|-------------|
| 通常生成 | 120秒 | 1.2GB |
| 最適化生成（4ワーカー） | 35秒 | 400MB |
| 最適化生成（8ワーカー） | 20秒 | 600MB |
| インクリメンタル（10%変更） | 3秒 | 150MB |

## 🔍 トラブルシューティング

### メモリ不足エラー

```bash
# メモリ制限を増やす
php artisan spectrum:generate:optimized --memory-limit=2G

# またはチャンクサイズを減らす
php artisan spectrum:generate:optimized --chunk-size=25
```

### ワーカープロセスのエラー

```bash
# ワーカー数を減らす
php artisan spectrum:generate:optimized --workers=2

# またはシングルプロセスモード
php artisan spectrum:generate:optimized --workers=1
```

### キャッシュの問題

```bash
# キャッシュをクリア
php artisan spectrum:cache clear

# キャッシュを無効化して生成
php artisan spectrum:generate:optimized --no-cache
```

## 💡 ベストプラクティス

### 1. CI/CDパイプラインでの使用

```yaml
# .github/workflows/generate-docs.yml
- name: Generate API Documentation
  run: |
    php artisan spectrum:generate:optimized \
      --workers=4 \
      --chunk-size=50 \
      --no-interaction
```

### 2. 定期的なキャッシュクリア

```bash
# crontab
0 2 * * * cd /path/to/project && php artisan spectrum:cache clear --quiet
```

### 3. 監視とアラート

```php
// AppServiceProvider.php
use LaravelSpectrum\Events\DocumentationGenerated;

Event::listen(DocumentationGenerated::class, function ($event) {
    if ($event->duration > 60) {
        // 生成に60秒以上かかった場合にアラート
        Log::warning('Documentation generation took too long', [
            'duration' => $event->duration,
            'routes_count' => $event->routesCount,
        ]);
    }
});
```

## 🚀 次世代機能（実験的）

### 分散生成

大規模プロジェクトで分散処理が必要な場合は、複数ワーカーを使用した最適化コマンドを利用：

```bash
php artisan spectrum:generate:optimized \
    --workers=8 \
    --memory-limit=1G
```


## 📚 関連ドキュメント

- [基本的な使い方](./basic-usage.md) - 通常の使用方法
- [設定リファレンス](./config-reference.md) - 詳細な設定オプション
- [トラブルシューティング](./troubleshooting.md) - 問題解決ガイド