# Changelog

All notable changes to this project will be documented in this file.

## [1.0.1](https://github.com/wadakatu/laravel-spectrum/releases/tag/v1.0.1) - 2026-01-04

### 🐛 Bug Fixes

- switch Packagist badges from poser.pugx.org to shields.io

### 📚 Documentation

- rewrite README for v1.0.0 release
- improve SEO for documentation site
- configure Algolia DocSearch

## [1.0.0](https://github.com/wadakatu/laravel-spectrum/releases/tag/v1.0.0) - 2026-01-04

### ♻️ Code Refactoring

- extract responsibilities from OpenApiGenerator
- simplify code and remove duplicate tests
- extract RuleRequirementAnalyzer from FormRequestAnalyzer
- extract FormatInferrer from FormRequestAnalyzer
- extract ValidationDescriptionGenerator from FormRequestAnalyzer
- extract ParameterBuilder from FormRequestAnalyzer
- extract FormRequestAstExtractor from FormRequestAnalyzer
- extract AnonymousClassAnalyzer from FormRequestAnalyzer
- consolidate PHP parsing into FormRequestAstExtractor
- extract prepareAnalysisContext helper in FormRequestAnalyzer
- extract AstHelper for shared AST operations
- use AstHelper in FractalTransformerAnalyzer
- remove unused DocumentationCache from Services namespace
- centralize PHP-Parser instantiation through DI
- extend ParserFactory centralization to AstHelper and AnonymousClassAnalyzer
- extract duplicate formatFileSize() to FileSizeFormatter utility class
- standardize error handling across analyzers
- extract MethodSourceExtractor utility class
- consolidate type inference logic into AstTypeInferenceEngine
- add traverse() helper to AstHelper for AST traversal
- extract anonymous visitors to dedicated classes
- extract AST value extraction to AstNodeValueExtractor
- consolidate example generation with Strategy pattern
- remove useNewFormat parameter from ResourceAnalyzer
- extract property mapping to SchemaPropertyMapper
- extract ValidationRuleTypeMapper for type inference
- address PR review suggestions
- remove unused dependencies from FormRequestAnalyzer
- switch from cebe/php-openapi to devizzent/cebe-php-openapi
- improve comment accuracy in OpenAPI validation tests
- address PR review suggestions for E2E tests
- replace mixed with union types
- simplify schema registration and add global security tests
- improve ParallelProcessor testability with DI
- add DI support and comprehensive tests
- introduce ValueObject/DTO classes for type-safe parameter handling
- apply DTOs to analyzers and generators
- introduce ControllerInfo DTO with nested type-safe DTOs
- introduce RouteInfo and RouteParameterInfo DTOs
- introduce Validation DTOs for FormRequest analysis
- apply OpenApiRequestBody DTO to RequestBodyGenerator
- apply OpenApiResponse DTO to ErrorResponseGenerator
- apply AuthenticationScheme DTO to SecuritySchemeGenerator
- apply OpenApiOperation DTO to OpenApiGenerator
- apply OpenApiParameter DTO fully to ParameterGenerator
- apply ResourceInfo DTO to ResourceAnalyzer and generators
- apply ResponseInfo DTO to ControllerInfo
- apply InlineValidationInfo DTO to ControllerInfo
- apply EnumInfo DTO to EnumAnalyzer and callers
- apply ParameterDefinition DTO to ParameterBuilder
- apply AuthenticationResult DTO to AuthenticationAnalyzer
- apply PaginationInfo DTO to PaginationAnalyzer
- add FractalTransformerResult DTO for type-safe transformer analysis
- apply ControllerInfo DTO to generator classes
- add analyzeToResult() method to FormRequestAnalyzer
- add InlineParameterInfo DTO for type-safe parameter generation
- add type-safe analyzeRulesToResult() method to FileUploadAnalyzer
- add TypeInfo DTO for AST type inference
- add MethodSignatureInfo DTO for enum method analysis
- introduce FieldPatternConfig DTO for type-safe pattern configuration
- remove redundant first_name and last_name pattern entries
- remove dead code patterns and add image tests
- convert FieldPatternRegistryTest to use data providers
- remove unreachable dead code patterns
- add ResourceDetectionResult DTO for type-safe resource detection
- add ErrorEntry DTO for type-safe error collection
- add DiagnosticReport DTO for type-safe diagnostic reporting
- add DetectedQueryParameter DTO for type-safe query parameter detection
- add ConditionResult DTO for type-safe conditional rules
- add FormRequestAnalysisContext DTO for type-safe analysis context
- introduce ResourceFieldInfo DTO for API Resource field type info
- eliminate redundant DTO-to-array conversion in AnonymousClassAnalyzer
- use ParameterDefinition[] in ValidationAnalysisResult
- use OpenApiResponse[] in OpenApiOperation
- add ConditionalRule DTO for type-safe conditional validation rules
- introduce ConditionalRuleDetail DTO for type-safe conditional rules
- introduce TagGroup and TagDefinition DTOs for type-safe tag handling
- add AbstractCollection base class and ValidationRuleCollection
- apply ValidationRuleCollection across codebase
- enhance ValidationRuleCollection::from() to accept null
- run PHPStan on single PHP version (8.4)
- add PHPDoc type annotations to reduce PHPStan baseline
- add PHPStan type definitions to Generator classes
- consolidate docs-site into docs directory

### ✅ Tests

- improve test coverage for OpenAPI 3.1.0 support
- add unit tests for ResponseStructureVisitor and IncrementalCache
- add comprehensive tests for AnonymousClassAnalyzer
- add ValidatesOpenApi trait for OpenAPI spec validation
- add comprehensive OpenAPI spec validation tests
- add snapshot testing for OpenAPI output stability
- add E2E tests for demo app integration
- add additional E2E test coverage
- improve DocumentationCache test coverage
- add null-safe operator test cases
- add tests for collectUsedTags to improve mutation coverage
- add tests for requiresAuth method to improve mutation coverage
- add tests for SchemaRegistry injection and clearing
- improve test coverage across multiple components
- enhance test coverage for support classes and services
- improve coverage for analyzers, formatters and generators (Phase 3)
- improve coverage for FormRequestAnalyzer and ControllerAnalyzer (Phase 4)
- improve coverage for AST visitors and analyzers (Phase 5)
- improve coverage for SchemaGenerator and ResourceAnalyzer (Phase 6)
- add tests to catch escaped mutants in generateConditionKey
- improve coverage for CollectionAnalyzer and QueryParameterDetector
- improve Performance component test coverage (Phase 8)
- add comprehensive tests for GenerateDocsCommand (Phase 10)
- improve WatchCommand test coverage (Phase 11)
- improve method coverage with additional tests
- improve method coverage from 75% to 78%
- improve MockServer test coverage and fix test reliability
- improve WatchCommand test coverage from 59% to 73%
- improve method coverage for GenerateDocsCommand and LiveReloadServer
- add tests to kill mutation testing escaped mutants
- improve GenerateDocsCommand coverage from 70% to 95%+
- improve LiveReloadServer coverage from 73% to 90%
- improve Exporter test coverage for PostmanExporter and InsomniaExporter
- add comprehensive tests for DTO edge cases
- add edge case tests for DTO conversion methods
- add FractalInfo fromArray default value tests
- add edge case tests for zero values and partial dimensions
- add edge case tests for EnumInfo DTO
- add edge case tests for OpenAPI output DTOs
- add coverage for apiKey name->headerName fallback
- add missing test coverage per PR review suggestions
- add edge case tests for TypeInfo DTO per review
- add tests for escaped mutants in FieldPatternRegistry
- add comprehensive pattern tests to kill mutation escapes
- add last_name pattern test to kill mutation escape
- add phone and phonenumber pattern tests
- add countrycode pattern test to kill mutation escape
- add country pattern test to kill mutation escape
- add tests for zipcode, lon, and avatar patterns to kill mutation escapes
- add tests for postal_code, thumbnail, photo, picture patterns
- add tests for cover, company, jobtitle, department patterns
- add missing pattern tests for mutation coverage
- improve method coverage for ParameterDefinition and OpenApiOperation
- improve DTO method coverage to 100%
- add edge case tests for collection classes
- add comprehensive demo-app patterns for OpenAPI testing
- add test for hybrid controller to kill mutation
- add comprehensive edge case tests for PCRE delimiter stripping
- add missing tests for string length constraints
- add float constraint tests for numeric types
- add tests for file detection in conditional rules
- add coverage for array items with required_array_keys
- add comprehensive tests for File:: static call detection
- add comprehensive tests for Password rule components
- add edge case tests for dynamic relation names
- add edge case test for required with conditional rule

### ✨ Features

- add HTML documentation output with Swagger UI integration
- add OpenAPI 3.1.0 specification support
- add tag groups and tag descriptions support
- add Claude Code skills for quality checks and PR review
- add category-based analyzer interfaces
- add Infection mutation testing
- implement controllers and form requests for comprehensive testing
- add version checking for automatic cache invalidation
- add null-safe operator support for Resource analysis
- add Post model and enhance Resource examples
- implement $ref schema references for API resources
- add style/explode support for array parameters ([#204](https://github.com/wadakatu/laravel-spectrum/issues/204))
- use configured OpenAPI version in base structure ([#207](https://github.com/wadakatu/laravel-spectrum/issues/207))
- add contact, license, termsOfService to info object ([#206](https://github.com/wadakatu/laravel-spectrum/issues/206))
- introduce Response DTOs for type-safe response handling
- introduce FileUploadInfo and FileDimensions DTOs
- introduce EnumBackingType enum and EnumInfo DTO
- introduce OpenAPI output DTOs
- introduce AuthenticationType enum and AuthenticationScheme DTO
- add OpenID Connect support per OpenAPI 3.0 spec
- add named constructors and additional tests for FieldPatternConfig
- add $ref validation to prevent broken references
- generate confirmation field for confirmed validation rule
- convert Password rule constraints to OpenAPI schema properties
- detect @deprecated PHPDoc annotation on controller methods
- detect route where() constraints and map to OpenAPI schema
- support request body for DELETE requests with validation
- detect request headers used in controller
- integrate validateReferences() into OpenAPI generation
- add OpenApiSpec DTO to replace array<string, mixed> types
- add @phpstan-type OpenApiOperationType for operation arrays
- add @phpstan-type OpenApiSchemaType for schema arrays
- add @phpstan-type RouteDefinition for route arrays
- add @phpstan-type for Postman and Insomnia export formats
- add MockResponse PHPStan type definition
- add PHPStan type definitions to MockServer classes
- add PHPStan type definitions to Formatter classes
- add PHPStan types to AST Visitor classes
- add specific types to Analyzer classes ([#360](https://github.com/wadakatu/laravel-spectrum/issues/360))
- add specific types to Support and Infrastructure classes ([#361](https://github.com/wadakatu/laravel-spectrum/issues/361))

### 🐛 Bug Fixes

- cast floor() return value to int for array key
- address PR review feedback
- remove nullable: false from output and add $ref tests
- address PR review findings and add test coverage
- add input validation and defensive error handling
- strengthen IncrementalCacheTest assertions
- address PR review findings for support classes
- address PR review findings for ValidationDescriptionGenerator
- add defensive validation for malformed conditional rules input
- address PR review findings for ParameterBuilder
- address PR review findings for FormRequestAstExtractor
- address all PR review findings (critical, important, suggestions)
- address PR review findings for AnonymousClassAnalyzer
- address PR review findings for AnonymousClassAnalyzer
- address PR review findings for FormRequestAstExtractor
- share ErrorCollector between FormRequestAnalyzer and AstExtractor
- address PR review findings for prepareAnalysisContext helper
- address PR review findings for AstHelper extraction
- address PR review findings for FractalTransformerAnalyzer
- improve commit message guidance in post-pr-review skill
- address PR review suggestions for FileSizeFormatter
- address PR review feedback for error handling improvements
- address PR review findings for MethodSourceExtractor
- address PR review findings for AstTypeInferenceEngine
- address PR review findings for traverse() method
- address PR review findings for visitor classes
- address PR review findings for AstNodeValueExtractor
- address PR review findings for example generation refactoring
- address PR review feedback for analyzer interfaces
- address PR review suggestions for SchemaPropertyMapper
- update demo-app files to use container resolution
- add .env file creation step for E2E workflow
- update Docusaurus to 3.9.2 to fix security vulnerabilities
- add missing return and parameter type hints
- resolve strict comparison and missing type errors
- add type annotations and improve type safety
- address PR review suggestions
- ignore unmatched baseline errors for PHP version compatibility
- improve error handling and documentation
- address PR review feedback
- handle object, boolean, and null values in YAML conversion
- prevent example state leak between different resources
- set memory_limit=-1 in php.ini for coverage generation
- add explicit memory_limit in test bootstrap
- run coverage on PHP 8.2 instead of 8.3
- use correct storage path in LiveReloadServer test
- improve assertion clarity in ControllerAnalyzerTest
- add declare(strict_types=1) to fixture files
- address PR review feedback
- add strict_types declaration and complete test assertions
- use project's base TestCase and correct namespace
- address PR review feedback
- make Faker seed test more robust across PHP versions
- make Faker seed test verify functionality not exact values
- remove plugin-development from sidebar configuration
- improve error handling and add test cleanup
- simplify applyStyleAndExplode to array-only and add test cleanup
- improve test quality per review feedback
- support inline validation detection for anonymous classes
- add guard clause and tests per PR review
- add error handling to analyzeWithConditionalRulesToResult
- address PR review feedback
- address PR review feedback
- address PR review feedback
- add PHPDoc and improve test coverage per review feedback
- consistent DTO serialization in toArray() methods
- serialize enumInfo consistently in InlineParameterInfo::toArray()
- update hasWidthConstraints/hasHeightConstraints to include exact dimensions
- skip memory limit test when Xdebug is enabled
- revert to PCOV for coverage, skip test when coverage enabled
- make ResourceDetectionResult constructor private for invariant safety
- recalculate counts from actual arrays in fromArray()
- add missing typed method aliases to TYPED_METHODS constant
- update SchemaGenerator and tests to use ConditionResult DTO
- make FormRequestAnalysisContext constructor private
- remove PHP 8.3 typed constant for backward compatibility
- accept all Japanese phone number formats in test
- relax phone number regex pattern in FakerIntegrationTest
- relax Japanese phone regex to allow 1-digit middle group
- handle keyless array items in ResourceStructureVisitor
- convert nested array validation to proper OpenAPI schema
- add defensive null coalescing and fallback items schema
- support union return types with oneOf in OpenAPI schema
- validate union requires at least 2 resource classes
- detect __invoke method for invokable controllers
- generate correct example for accepted/declined rules
- generate accurate decimal example based on rule parameters
- add format property to conditional parameters
- exclude fields with exclude validation rule from schema
- convert regex patterns to OpenAPI pattern property
- convert string min/max/size rules to minLength/maxLength
- convert numeric validation rules to OpenAPI constraints
- convert array validation rules to OpenAPI minItems/maxItems
- add between rule support and additional tests for array constraints
- add ulid format mapping and tests for validation rule formats
- extract enum values from Rule::in() objects
- address PR review feedback for confirmed rule
- detect file uploads in conditional validation rules
- reflect required_array_keys validation in OpenAPI schema
- detect File:: static call strings as file upload rules
- whenCounted and whenAggregated return valid OpenAPI types
- convert GET request validation rules to query parameters
- remove unused dependency and add missing tests
- only mark fields as required with unconditional 'required' rule
- rename undocumentedMethod to methodWithoutDocblock
- support Laravel's native whereUuid() pattern and add helper tests
- add error logging and improve test coverage for header detection
- improve PHPDoc completeness based on PR review
- add hasWebhooks() method and test coverage for webhooks
- add missing required property to requestBody type
- add missing schema properties and class description
- add params key to RouteDefinition type and remove unused import
- correct type definitions in AST Visitors
- add missing fields to ResourceStructure type
- change extractReturnedArray visibility to private
- update broken README link in contributing.md
- update docs-deploy workflow paths after docs consolidation
- remove gitignore rules for docs content, add documentation files

### 📚 Documentation

- update CHANGELOG.md for v0.2.2-beta
- add PHPDoc and beforeTraverse resets to visitor classes
- improve PHPDoc comments for snapshot normalization methods
- add modular Claude Code rules for better organization
- update CLI and config reference to match implementation
- remove unimplemented plugin system documentation
- add CONTRIBUTING.md to project root
- add stability and backward compatibility document
- remove Lumen references from documentation
- add documentation rules for Claude Code
- add v1.0.0 release notes and fix STABILITY.md PHP version

### 📦 Build System

- bump js-yaml from 3.14.1 to 3.14.2 in /docs-site
- bump node-forge from 1.3.1 to 1.3.2 in /docs-site
- bump mdast-util-to-hast from 13.2.0 to 13.2.1 in /docs-site
- add cebe/php-openapi dependency for spec validation

### 🚀 Continuous Integration

- increase memory limit to 256M for coverage generation
- set memory_limit=256M in phpunit.xml for coverage generation
- use PCOV instead of Xdebug for code coverage
- increase memory limit to 512M for code coverage
- increase PHPUnit memory limit to 512M for coverage generation
- pass memory_limit directly to PHP for coverage generation
- optimize coverage generation to only run where needed
- lower mutation testing threshold for file-level diffing
- increase PHP memory limit for coverage generation
- increase PHP memory limit for coverage generation
- use unlimited memory limit for coverage generation
- debug and force memory_limit via custom ini file
- add pcov.directory setting to limit coverage scope
- revert to main branch configuration for coverage
- remove ini-values to match main branch exactly
- switch coverage generation to PHP 8.4
- try PHP 8.2 for coverage generation
- revert mutation testing threshold to original 65%
- add debug output for PHP memory settings
- increase memory limit for coverage generation

## [0.2.2-beta](https://github.com/wadakatu/laravel-spectrum/releases/tag/v0.2.2-beta) - 2025-08-11

### ✅ Tests

- add Laravel 11 and 12 demo apps for testing
- enhance Laravel 11 demo app with comprehensive test routes
- 匿名FormRequestクラス解析のテストケースを追加

### ✨ Features

- add Laravel 11 support docs and restructure demo apps
- require PHP 8.2 minimum
- 匿名FormRequestクラスのバリデーションルール検出機能を実装
- 匿名FormRequest機能の動作確認用コントローラーとルートを追加

### 🐛 Bug Fixes

- prevent git rebase error with unstaged changes in changelog workflow
- support Laravel 12 enum validation rules

### 📚 Documentation

- add comprehensive test report for Laravel 11/12 compatibility
- ドキュメントと実装の乖離を修正

## [0.2.1-beta](https://github.com/wadakatu/laravel-spectrum/releases/tag/v0.2.1-beta) - 2025-08-07

### ✨ Features

- drop Laravel 10 support
- remove Lumen compatibility layer
- remove Lumen configuration options
- update package description to remove Lumen reference

### 🐛 Bug Fixes

- prevent push conflicts in changelog update workflow

### 📚 Documentation

- remove Lumen references from documentation

### 🚀 Continuous Integration

- remove PHP 8.1 support from test matrix

## [0.2.0-beta](https://github.com/wadakatu/laravel-spectrum/releases/tag/v0.2.0-beta) - 2025-08-07

### ♻️ Code Refactoring

- make ParallelProcessor testable with optional constructor params
- ExportPostmanCommandTestのコード整形と不要なインポートを削除
- improve directory detection logic in ExportInsomniaCommand
- PHPDocアノテーションを削除（PHPStanのbaselineで対応済み）

### ✅ Tests

- add comprehensive tests for Performance namespace classes
- add comprehensive test coverage for ModelSchemaExtractor
- ExportPostmanCommandのテストスイートを追加
- ExportInsomniaCommandの包括的なテストスイートを追加
- add comprehensive test suites for AST Visitors
- add CollectionAnalyzer test suite
- LiveReloadServerの包括的なテストカバレッジを追加
- add comprehensive test suite for OpenApiGenerator
- add comprehensive test suite for ResponseSchemaGenerator
- enhance ParallelProcessor unit test coverage
- add advanced unit tests for ParallelProcessor
- add Orchestra Testbench integration tests
- enhance FormRequestAnalyzer test coverage
- add comprehensive RouteAnalyzer test coverage
- add comprehensive test coverage for AnonymousClassFindingVisitor
- add comprehensive large-scale performance tests
- 大規模FormRequestのパフォーマンステストを追加

### ✨ Features

- add Closure validation rule detection support
- add support for conditional validation rules
- add support for date-related validation rules

### 🐛 Bug Fixes

- improve memory limit parsing in MemoryManager
- handle missing Fork class in ParallelProcessor for CI environments
- handle unlimited memory (-1) in MemoryManager and tests
- correct Fork usage in ParallelProcessor
- replace PHPUnit 11 deprecated mock methods with stubs
- FormRequest解析テストをスキップに変更
- FormRequest解析テストを実装し、format推論を追加
- resolve API route detection in Laravel 11/12 environments

### 📚 Documentation

- update CHANGELOG.md for v0.1.0-beta
- Artisanコマンド全般の問題であることを明確化するためコメントを修正

### 📦 Build System

- add PHPUnit 12 support

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

