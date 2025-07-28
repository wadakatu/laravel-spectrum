# Changelog

All notable changes to this project will be documented in this file.

## [0.1.0-beta](https://github.com/wadakatu/laravel-spectrum/releases/tag/v0.1.0-beta) - 2025-07-28

### ♻️ Code Refactoring

- Docusaurusの設定を更新（baseUrlを/に変更、docsをルートから/docsへ移動）
- ドキュメントをシンボリックリンクに変更（自動同期のため）
- エージェント設定を整理し、qa-testing-expertに統合

### ✅ Tests

- Faker統合機能のテストを追加
- 条件付きバリデーションルールの統合テストとユニットテストを追加
- エラーハンドリングの統合テストを追加
- demo-appにエラーハンドリングテスト用のファイルを追加
- demo-appにレスポンス検出のテスト用コントローラーを追加
- テスト基盤の改善とヘルパートレイト追加
- 統合テストの整理とControllerEnumParameterTestの修正
- パフォーマンステストを追加
- MockServerCommandTestのスキップを解除し、モックを使用したテストに修正

### ✨ Features

- Fakerライブラリを依存関係に追加
- Faker統合によるリアルな例データ生成機能を実装
- 例生成のための設定項目を追加
- 条件付きバリデーションルールのAST解析とoneOfスキーマ生成機能を実装
- FormRequestAnalyzerにanalyzeWithConditionalRulesメソッドを追加、匿名クラスサポートを改善
- エラーコレクタークラスを実装
- 各Analyzerにエラーハンドリングを追加
- GenerateDocsCommandにエラーレポート機能を追加
- エラーハンドリング設定を追加
- レスポンスボディ自動検出機能の実装
- ResponseSchemaGeneratorの実装とテストを追加
- 既存クラスにレスポンス解析機能を統合
- レスポンス検出の設定オプションを追加
- Rule::enum()およびnew Enum()のAST解析サポートを追加
- パフォーマンス最適化のためのコアクラスを追加
- インクリメンタルキャッシュと基本キャッシュクラスを追加
- 最適化版の生成コマンドを追加
- パフォーマンス設定セクションを追加
- OptimizedGenerateCommandをサービスプロバイダーに登録
- Postman/Insomniaエクスポート機能のコア実装を追加
- PostmanとInsomniaエクスポート用のArtisanコマンドを追加
- エクスポート機能の設定とサービスプロバイダー登録を追加
- Claude開発環境設定とカスタムエージェントを追加
- モックサーバーの設定とコマンド登録を追加
- RouteResolverクラスを実装（パスマッチングとパラメータ抽出）
- AuthenticationSimulatorクラスを実装（Bearer/APIKey/Basic/OAuth2/Sanctum認証）
- ValidationSimulatorクラスを実装（OpenAPIスキーマベースのバリデーション）
- DynamicExampleGeneratorクラスを実装（動的サンプルデータ生成）
- ResponseGeneratorクラスを実装（レスポンス生成とページネーション検出）
- RequestHandlerクラスを実装（リクエスト処理のオーケストレーション）
- MockServerクラスを実装（Workermanベースのサーバー）
- MockServerCommandを実装（spectrum:mockコマンド）
- GitHub Pagesでドキュメントを公開する設定を追加
- トップページを復元し、日本語化対応を追加
- 日本語翻訳ファイルを追加（navbar、footer、homepage機能説明）
- プロジェクト専用の画像アセットを追加（favicon、ソーシャルカード、ロゴ）
- 新しいエージェント設定を追加（task-orchestrator、php-backend-engineer、documentation-maintainer）
- add Release Please automation and update default version

### 🐛 Bug Fixes

- PHPStanのエラーを修正（EnumAnalyzerの型推論問題）
- Resourceタイプのレスポンスは既存のResourceAnalyzerを使用するように修正
- contributing.mdの壊れたリンクを修正
- シンボリックリンクをコピースクリプトに変更（ビルドエラー修正）
- docs-site/package-lock.jsonをGitに追加（CI修正）
- AST visitorがRuleオブジェクトと動的ルールを正しく処理するよう修正

### 📚 Documentation

- Faker統合機能のドキュメントを追加
- 条件付きバリデーションルール機能のドキュメントを追加、PHPStan設定を更新
- GitHubソーシャルプレビュー画像を追加
- パフォーマンス最適化機能の説明を追加し、表のレイアウトを改善
- パフォーマンス最適化の詳細ガイドを追加
- エクスポート機能とCLIリファレンスのドキュメントを追加
- READMEを更新し、エクスポート機能の説明を追加・整理
- 日本語ドキュメントを追加
- 日本語ドキュメントを追加（APIリソース、エラーハンドリング、ミドルウェア、ページネーション、プラグイン開発、セキュリティ）
- 日本語ドキュメントの不正なリンクを修正
- 日本語ドキュメントのREADMEを簡潔化し、index.mdへの参照に変更
- FAQ内のドキュメントリンクをindex.mdに修正
- READMEのバッジスタイルを更新
- 日本語ドキュメントを英語に翻訳

### 📦 Build System

- Workermanパッケージを追加（モックサーバー用）

### 🚀 Continuous Integration

- replace Release Please with manual release and git-cliff CHANGELOG generation

## [0.0.18-alpha](https://github.com/wadakatu/laravel-spectrum/releases/tag/v0.0.18-alpha) - 2025-07-23

### ✅ Tests

- LiveReloadServerテストをファイルベース通信に対応
- add fixtures for enum integration testing
- add comprehensive enum integration tests
- 実機動作確認用のdemo-appを追加
- ページネーション機能のテストを追加
- demo-appにページネーションテスト用エンドポイントを追加
- Query Parameter検出機能のテストを追加
- Query Parameter検出用のテストフィクスチャを追加
- demo-appにQuery Parameter検出テスト用コントローラーを追加
- match式からのEnum値検出のテストケースを追加
- ファイルアップロード機能の統合テストとフィクスチャを追加
- demo-appにExample生成機能の動作確認用エンドポイントを追加
- コントローラーEnum型パラメータの統合テストを追加
- demo-appにEnum型パラメータの動作確認用コードを追加
- PHPUnitの@testアノテーションを#[Test]属性に移行
- 配列形式ファイルアップロード機能のテストを追加

### ✨ Features

- add EnumExtractor utility for extracting enum values
- add UseStatementExtractorVisitor for namespace resolution
- add EnumAnalyzer for detecting enum validation rules
- enhance AST visitor and type inference for enum support
- integrate enum detection into validation analyzers
- update SchemaGenerator to handle enum constraints
- ページネーション検出機能の実装
- ページネーション機能を既存コンポーネントに統合
- Query Parameter自動検出機能のコアクラスを追加
- Query Parameter検出を既存コンポーネントに統合
- PHP 8.0以降のMatch式サポートを追加
- ファイルアップロード検出機能の基本実装を追加
- multipart/form-dataスキーマ生成機能を追加
- FormRequestとInlineValidationAnalyzerにファイルアップロード検出を統合
- SchemaGeneratorとOpenApiGeneratorでmultipart/form-dataに対応
- add request()->validate() pattern detection
- add $request->validate() pattern detection
- HasExamplesインターフェースとFieldNameInferenceサポートクラスを追加
- ExampleGeneratorとExampleValueFactoryクラスを実装
- OpenApiGeneratorとResourceAnalyzerにExample生成機能を統合
- EnumAnalyzerのextractEnumInfoメソッドをpublicに変更
- ControllerAnalyzerでEnum型パラメータの検出機能を追加
- OpenApiGeneratorでEnum型パラメータをOpenAPIスキーマに含める
- 配列形式ファイルアップロードのスキーマ生成を改善
- multipart/form-dataのContent-Type設定と説明文生成を追加
- ネストした配列ファイルパターンの検出機能を追加

### 🐛 Bug Fixes

- URLクエリパラメータが累積する問題を修正
- Query Parameter検出のエッジケースを修正
- PHPStan静的解析エラーを修正
- ファイルディメンション制約の説明文生成を修正

### 📚 Documentation

- 詳細ドキュメントをdocsディレクトリに移動
- READMEを簡潔化し、見やすくリデザイン

### 🚀 Continuous Integration

- PHP 8.4をテストマトリックスに追加

## [0.0.17-alpha](https://github.com/wadakatu/laravel-spectrum/releases/tag/v0.0.17-alpha) - 2025-07-12

### ✅ Tests

- LiveReloadServerテストを静的変数に対応

### 🐛 Bug Fixes

- WebSocket通知が送信されない問題を修正

## [0.0.16-alpha](https://github.com/wadakatu/laravel-spectrum/releases/tag/v0.0.16-alpha) - 2025-07-11

### ✨ Features

- 自動リロード機能のデバッグログを追加

## [0.0.15-alpha](https://github.com/wadakatu/laravel-spectrum/releases/tag/v0.0.15-alpha) - 2025-07-11

### ✅ Tests

- WatchCommandテストを修正

### 🐛 Bug Fixes

- WatchCommandで子プロセスを使用してドキュメント生成を実行

## [0.0.14-alpha](https://github.com/wadakatu/laravel-spectrum/releases/tag/v0.0.14-alpha) - 2025-07-10

### ♻️ Code Refactoring

- DocumentationCacheをServicesからCacheディレクトリに移動

### ✨ Features

- --no-cacheオプションの動作を改善

## [0.0.13-alpha](https://github.com/wadakatu/laravel-spectrum/releases/tag/v0.0.13-alpha) - 2025-07-10

### ✅ Tests

- WatchCommandテストで--no-cacheオプションを期待するよう修正

### 🐛 Bug Fixes

- ルート再読み込み時のエラーハンドリングを改善

## [0.0.12-alpha](https://github.com/wadakatu/laravel-spectrum/releases/tag/v0.0.12-alpha) - 2025-07-10

### ✨ Features

- ルートファイル変更時の強制リロード機能を実装
- ルートリロード機能の改善とデバッグ情報の追加

## [0.0.11-alpha](https://github.com/wadakatu/laravel-spectrum/releases/tag/v0.0.11-alpha) - 2025-07-10

### ✅ Tests

- WatchCommandテストにキャッシュ検証用のモックを追加

### ✨ Features

- watchコマンドにルートファイル変更時の強制キャッシュクリア機能を追加
- LiveReloadServerにキャッシュ制御とデバッグ機能を追加

## [0.0.10-alpha](https://github.com/wadakatu/laravel-spectrum/releases/tag/v0.0.10-alpha) - 2025-07-09

### 🐛 Bug Fixes

- watchコマンドでルート変更時のキャッシュクリア問題を修正

## [0.0.9-alpha](https://github.com/wadakatu/laravel-spectrum/releases/tag/v0.0.9-alpha) - 2025-07-09

### ✅ Tests

- WatchCommandテストのモックを修正

### ✨ Features

- GenerateDocsCommandに詳細なデバッグ情報を追加
- WatchCommandにファイル生成の確認とデバッグ機能を追加
- LiveReloadServerのキャッシュ対策を強化

### 🐛 Bug Fixes

- パッケージ開発環境でのstorage_path()互換性対応

## [0.0.8-alpha](https://github.com/wadakatu/laravel-spectrum/releases/tag/v0.0.8-alpha) - 2025-07-08

### ✅ Tests

- verboseモード確認のテストを更新

### 🐛 Bug Fixes

- 重複する--verboseオプション定義を削除

## [0.0.7-alpha](https://github.com/wadakatu/laravel-spectrum/releases/tag/v0.0.7-alpha) - 2025-07-08

### ✅ Tests

- DocumentationCacheの新機能に対するテストを追加
- WatchCommandテストにoption()メソッドのモックを追加

### ✨ Features

- キャッシュのデバッグ機能とステータス確認メソッドを追加
- WatchCommandにキャッシュ状態の可視化機能を追加

### 🐛 Bug Fixes

- 環境変数名をPRISMからSPECTRUMに統一

## [0.0.6-alpha](https://github.com/wadakatu/laravel-spectrum/releases/tag/v0.0.6-alpha) - 2025-07-08

### ♻️ Code Refactoring

- 未使用のキャッシュクリアメソッドを削除
- WatchCommandで差分キャッシュクリアを実装

### ✅ Tests

- 差分キャッシュクリア機能のテストを追加

### ✨ Features

- キャッシュの差分削除機能を追加

### 🐛 Bug Fixes

- spectrum:watchコマンドでキャッシュを無効化

## [0.0.5-alpha](https://github.com/wadakatu/laravel-spectrum/releases/tag/v0.0.5-alpha) - 2025-07-08

### 🐛 Bug Fixes

- Swagger UI v5でStandaloneLayoutエラーを修正

## [0.0.4-alpha](https://github.com/wadakatu/laravel-spectrum/releases/tag/v0.0.4-alpha) - 2025-07-08

### 🐛 Bug Fixes

- spectrum:watchコマンドのWorkerMan起動引数を修正

## [0.0.3-alpha](https://github.com/wadakatu/laravel-spectrum/releases/tag/v0.0.3-alpha) - 2025-07-08

### ♻️ Code Refactoring

- プロジェクト名をLaravel PrismからLaravel Spectrumに変更

### ✅ Tests

- OpenAPIタグ生成機能の単体テストを追加

### ✨ Features

- OpenAPIタグ生成ロジックを改善
- タグマッピング設定セクションを追加

### 🐛 Bug Fixes

- バナー更新ワークフローで任意のバージョン番号に対応できるよう正規表現を修正
- OpenAPI 3.0仕様に準拠するようパラメータのtype定義を修正

### 📚 Documentation

- タグ生成機能のドキュメントを追加

## [0.0.2-alpha](https://github.com/wadakatu/laravel-spectrum/releases/tag/v0.0.2-alpha) - 2025-07-08

### ♻️ Code Refactoring

- バナー更新ワークフローをシンプルに再実装

### ✅ Tests

- ルートプレフィックスのテストを追加
- exampleキーが存在しない場合のSchemaGeneratorのテストを追加

### 🐛 Bug Fixes

- GitHub Actionsのdetached HEADエラーを修正
- GitHub Actionsバナー更新ワークフローをPR経由に修正
- バナー更新をPR経由で実行するように変更
- バナー更新ワークフローのブランチ重複エラーを修正
- バナー更新ワークフローの根本的な修正
- バナーバージョン抽出とブランチクリーンアップの修正
- バナーのバージョン置換コマンドを修正
- バナー更新のsedコマンドを最終修正
- SchemaGeneratorでexampleキーが存在しない場合のエラーを修正

### 📚 Documentation

- composer requireコマンドに--devフラグを追加

## [0.0.1-alpha](https://github.com/wadakatu/laravel-spectrum/releases/tag/v0.0.1-alpha) - 2025-07-07

### ♻️ Code Refactoring

- FormRequestAnalyzerをASTベースに完全書き換え
- composer scriptsの名前を統一
- Visitorクラスの不要なnullチェックを削除
- ResourceAnalyzerをASTベースに完全書き換え

### ✅ Tests

- FormRequestAnalyzerの新機能に対応したテストを追加
- ResourceAnalyzer用の複雑なテストフィクスチャを追加
- ResourceAnalyzerにAST解析用の新しいテストケースを追加

### ✨ Features

- Add CI/CD setup with GitHub Actions
- AST解析用のVisitorクラスを追加
- ResourceStructureVisitorを追加（条件付きフィールド・ネストしたリソース対応）
- バナーバージョンの自動更新機能を追加

### 🐛 Bug Fixes

- Update GitHub Actions workflow for Laravel 12 support
- Remove Laravel Pint and PHPStan for PHP 8.1 compatibility
- PHPUnit configuration and RouteAnalyzer closure handling
- Add PHPUnit 9 compatibility for prefer-lowest tests
- Improve PHPUnit version detection for configuration selection
- Simplify PHPUnit configuration handling
- Remove deprecated PHPUnit attributes from legacy config
- Add orchestra/testbench v10 support for Laravel 12
- Add PHPUnit 11 support for Laravel 12 compatibility
- テストでの古いPrismServiceProvider参照を修正
- テストファイル内の残りの古い名前空間参照を修正

### 📚 Documentation

- README.mdにバナーを追加

### 📦 Build System

- nikic/php-parserパッケージを追加

