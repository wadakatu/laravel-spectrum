---
id: contributing
title: 貢献ガイド
sidebar_label: 貢献ガイド
---

# 貢献ガイド

Laravel Spectrumへの貢献を歓迎します！このガイドでは、プロジェクトへの貢献方法について説明します。

## 🤝 貢献の方法

### 貢献できる分野

- 🐛 **バグ修正** - 既知の問題の修正
- ✨ **新機能** - 新しい機能の提案と実装
- 📚 **ドキュメント** - ドキュメントの改善や翻訳
- 🧪 **テスト** - テストカバレッジの向上
- 🎨 **リファクタリング** - コード品質の改善
- 🌐 **翻訳** - 多言語対応

## 🚀 開発環境のセットアップ

### 1. リポジトリのフォーク

```bash
# GitHubでフォークした後
git clone https://github.com/YOUR_USERNAME/laravel-spectrum.git
cd laravel-spectrum
```

### 2. 依存関係のインストール

```bash
composer install
npm install
```

### 3. テスト環境の設定

```bash
# テスト用の.envファイルを作成
cp .env.testing.example .env.testing

# テストDBの設定（SQLiteを使用）
touch database/testing.sqlite
```

### 4. pre-commitフックの設定

```bash
# Huskyのインストール
npm run prepare

# または手動でGitフックを設定
cp .github/hooks/pre-commit .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit
```

## 📝 コーディング規約

### PHPコーディング規約

Laravel Spectrumは[PSR-12](https://www.php-fig.org/psr/psr-12/)に従います。

```php
<?php

namespace LaravelSpectrum\Analyzers;

use Illuminate\Support\Collection;
use LaravelSpectrum\Contracts\Analyzer;

class ExampleAnalyzer implements Analyzer
{
    /**
     * コンストラクタの依存性注入
     */
    public function __construct(
        private readonly Collection $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * 解析を実行
     *
     * @param mixed $target 解析対象
     * @return array 解析結果
     * @throws AnalysisException
     */
    public function analyze($target): array
    {
        try {
            // 実装
            return $this->performAnalysis($target);
        } catch (\Exception $e) {
            $this->logger->error('Analysis failed', [
                'target' => $target,
                'error' => $e->getMessage(),
            ]);
            
            throw new AnalysisException(
                'Failed to analyze target',
                previous: $e
            );
        }
    }
}
```

### コードスタイルの自動修正

```bash
# Laravel Pintを使用
composer format

# または特定のファイルのみ
vendor/bin/pint path/to/file.php

# ドライラン（変更を確認）
vendor/bin/pint --test
```

### 静的解析

```bash
# PHPStanを実行
composer analyze

# レベルを指定
vendor/bin/phpstan analyze --level=8
```

## 🧪 テストの作成

### ユニットテスト

```php
namespace LaravelSpectrum\Tests\Unit\Analyzers;

use LaravelSpectrum\Analyzers\ExampleAnalyzer;
use LaravelSpectrum\Tests\TestCase;

class ExampleAnalyzerTest extends TestCase
{
    private ExampleAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new ExampleAnalyzer();
    }

    /** @test */
    public function it_analyzes_simple_case(): void
    {
        // Arrange
        $input = ['key' => 'value'];
        
        // Act
        $result = $this->analyzer->analyze($input);
        
        // Assert
        $this->assertArrayHasKey('processed', $result);
        $this->assertEquals('value', $result['processed']['key']);
    }

    /** @test */
    public function it_throws_exception_for_invalid_input(): void
    {
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('Invalid input');
        
        $this->analyzer->analyze(null);
    }
}
```

### 機能テスト

```php
namespace LaravelSpectrum\Tests\Feature;

use LaravelSpectrum\Tests\TestCase;

class DocumentGenerationTest extends TestCase
{
    /** @test */
    public function it_generates_documentation_for_simple_api(): void
    {
        // ルートを定義
        Route::get('/api/test', fn() => ['status' => 'ok']);
        
        // コマンドを実行
        $this->artisan('spectrum:generate')
            ->assertExitCode(0)
            ->assertSee('Documentation generated successfully');
        
        // 生成されたファイルを確認
        $this->assertFileExists(storage_path('app/spectrum/openapi.json'));
        
        $openapi = json_decode(
            file_get_contents(storage_path('app/spectrum/openapi.json')),
            true
        );
        
        $this->assertArrayHasKey('/api/test', $openapi['paths']);
    }
}
```

### テストの実行

```bash
# すべてのテストを実行
composer test

# 特定のテストを実行
composer test -- --filter=ExampleAnalyzerTest

# カバレッジレポート付き
composer test-coverage
```

## 🔄 プルリクエストのプロセス

### 1. ブランチの作成

```bash
# 機能追加
git checkout -b feature/amazing-feature

# バグ修正
git checkout -b fix/issue-123

# ドキュメント
git checkout -b docs/improve-readme
```

### 2. コミットメッセージ

[Conventional Commits](https://www.conventionalcommits.org/)に従います：

```bash
# 機能追加
git commit -m "feat: add support for GraphQL schema generation"

# バグ修正
git commit -m "fix: correctly detect nested array validation rules"

# ドキュメント
git commit -m "docs: add Japanese translation"

# 破壊的変更
git commit -m "feat!: change default output format to YAML

BREAKING CHANGE: The default output format has been changed from JSON to YAML."
```

### 3. プルリクエストのテンプレート

```markdown
## 概要
このPRで解決する問題や追加する機能の簡潔な説明

## 変更内容
- [ ] 具体的な変更点1
- [ ] 具体的な変更点2

## テスト
- [ ] ユニットテストを追加/更新
- [ ] 機能テストを追加/更新
- [ ] 手動でテスト済み

## 関連Issue
Fixes #123

## スクリーンショット（UIの変更がある場合）
変更前と変更後のスクリーンショット

## チェックリスト
- [ ] コードスタイルガイドラインに従っている
- [ ] セルフレビュー実施済み
- [ ] ドキュメントを更新した
- [ ] 破壊的変更がない（ある場合は明記）
```

### 4. レビュープロセス

1. **自動チェック** - CI/CDが自動的に実行されます
2. **コードレビュー** - メンテナーがコードをレビュー
3. **フィードバック** - 必要に応じて修正
4. **マージ** - 承認後にマージ

## 📋 コントリビューターライセンス契約（CLA）

最初の貢献時に、CLAへの同意が必要です。これは自動的に処理されます。

## 🏗️ プロジェクト構造

```
laravel-spectrum/
├── src/
│   ├── Analyzers/          # コード解析クラス
│   ├── Cache/              # キャッシュ関連
│   ├── Console/            # Artisanコマンド
│   ├── Contracts/          # インターフェース
│   ├── Events/             # イベントクラス
│   ├── Exceptions/         # 例外クラス
│   ├── Exporters/          # エクスポート機能
│   ├── Facades/            # Laravelファサード
│   ├── Formatters/         # フォーマッター
│   ├── Generators/         # ジェネレーター
│   ├── MockServer/         # モックサーバー
│   ├── Services/           # サービスクラス
│   └── Support/            # ヘルパークラス
├── tests/
│   ├── Unit/               # ユニットテスト
│   ├── Feature/            # 機能テスト
│   └── Fixtures/           # テスト用フィクスチャ
├── config/                 # 設定ファイル
└── docs/                   # ドキュメント
```

## 🎯 重点分野

現在、特に以下の分野での貢献を求めています：

### 1. パフォーマンス改善
- 大規模プロジェクトでの最適化
- メモリ使用量の削減
- 並列処理の改善

### 2. 新機能
- GraphQL対応
- gRPC対応
- WebSocket API対応

### 3. エコシステム
- IDE拡張機能
- CIツールとの統合
- 他フレームワークへの移植

## 🌐 翻訳

### 新しい言語の追加

1. `resources/lang/{locale}`ディレクトリを作成
2. 既存の言語ファイルをコピーして翻訳
3. ドキュメントの翻訳（`docs/{locale}/`）

### 翻訳のガイドライン

- 技術用語は無理に翻訳しない
- 読みやすさを重視
- 原文の意図を正確に伝える

## 📞 コミュニケーション

### GitHub Issues
- バグ報告
- 機能リクエスト
- 質問

### GitHub Discussions
- アイデアの議論
- RFCs（Request for Comments）
- コミュニティサポート

### その他
- Twitter: [@LaravelSpectrum](https://twitter.com/LaravelSpectrum)
- Email: contribute@laravel-spectrum.dev

## 🏆 コントリビューター

貢献者はプロジェクトのREADME.mdに記載されます。

## 📄 ライセンス

貢献されたコードは、プロジェクトと同じMITライセンスでリリースされます。

---

**ありがとうございます！** あなたの貢献がLaravel Spectrumをより良いものにします。 🎉