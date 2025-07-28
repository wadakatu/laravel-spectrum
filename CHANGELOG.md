# Changelog

All notable changes to this project will be documented in this file.

## [0.1.0-alpha](https://github.com/wadakatu/laravel-spectrum/compare/v0.0.18-alpha...v0.1.0-alpha) (2025-07-28)


### Features

* add Release Please automation and update default version ([4f79b9a](https://github.com/wadakatu/laravel-spectrum/commit/4f79b9a5fdc3a2f664985e2fc125b70de51b1590))
* AuthenticationSimulatorクラスを実装（Bearer/APIKey/Basic/OAuth2/Sanctum認証） ([8825f6b](https://github.com/wadakatu/laravel-spectrum/commit/8825f6b17b0c3965b6e8a9398cbb791f86e5f7f4))
* Claude開発環境設定とカスタムエージェントを追加 ([477c749](https://github.com/wadakatu/laravel-spectrum/commit/477c74963a7450e389947e6249883e3495c51750))
* DynamicExampleGeneratorクラスを実装（動的サンプルデータ生成） ([62c2a5b](https://github.com/wadakatu/laravel-spectrum/commit/62c2a5b21410940c217f76ed77148498a2a6f119))
* Fakerライブラリを依存関係に追加 ([0b6e6fb](https://github.com/wadakatu/laravel-spectrum/commit/0b6e6fba6ed42a40719c1cef0e126dea51d134a1))
* Faker統合によるリアルな例データ生成機能を実装 ([4846c72](https://github.com/wadakatu/laravel-spectrum/commit/4846c72f06656a7ebce128c429d90664c8a19e97))
* FormRequestAnalyzerにanalyzeWithConditionalRulesメソッドを追加、匿名クラスサポートを改善 ([e91d491](https://github.com/wadakatu/laravel-spectrum/commit/e91d491dc804da616bac91f252b51b0e94e51e17))
* GenerateDocsCommandにエラーレポート機能を追加 ([b389f41](https://github.com/wadakatu/laravel-spectrum/commit/b389f414085058d018b68341b3cf506a828cadb6))
* GitHub Pagesでドキュメントを公開する設定を追加 ([0154879](https://github.com/wadakatu/laravel-spectrum/commit/01548799fc9a72b2563a406815d0a67308058fe7))
* MockServerCommandを実装（spectrum:mockコマンド） ([7cec421](https://github.com/wadakatu/laravel-spectrum/commit/7cec421606aa8274128f3447d1b939ad14794320))
* MockServerクラスを実装（Workermanベースのサーバー） ([5fd4527](https://github.com/wadakatu/laravel-spectrum/commit/5fd45272f19477c2bd52e3447d6ef39a8d495cbf))
* OptimizedGenerateCommandをサービスプロバイダーに登録 ([9fb0f46](https://github.com/wadakatu/laravel-spectrum/commit/9fb0f467831e86a0e37e642af5668ef761495eca))
* Postman/Insomniaエクスポート機能のコア実装を追加 ([5929fe4](https://github.com/wadakatu/laravel-spectrum/commit/5929fe433ba2789f5b4d72ff67b15083a726c7ef))
* PostmanとInsomniaエクスポート用のArtisanコマンドを追加 ([d65722d](https://github.com/wadakatu/laravel-spectrum/commit/d65722db487ac0a29ef450009605768db0b2dd59))
* RequestHandlerクラスを実装（リクエスト処理のオーケストレーション） ([6f4ffc3](https://github.com/wadakatu/laravel-spectrum/commit/6f4ffc304cc3d53e0d655cd11dbd0e5121aa644f))
* ResponseGeneratorクラスを実装（レスポンス生成とページネーション検出） ([319f4c4](https://github.com/wadakatu/laravel-spectrum/commit/319f4c43ecd794932a0a6f194914af26fa47f805))
* ResponseSchemaGeneratorの実装とテストを追加 ([05bdd45](https://github.com/wadakatu/laravel-spectrum/commit/05bdd45ce0e184d7b7886a7468ef0942fd91a9d2))
* RouteResolverクラスを実装（パスマッチングとパラメータ抽出） ([eb922c8](https://github.com/wadakatu/laravel-spectrum/commit/eb922c8a68e5360dd9287111b390f95dd9ddd6ad))
* Rule::enum()およびnew Enum()のAST解析サポートを追加 ([6b47318](https://github.com/wadakatu/laravel-spectrum/commit/6b473180de14d074b25ab9d71306719a4a1a2e4e))
* ValidationSimulatorクラスを実装（OpenAPIスキーマベースのバリデーション） ([cc5c76e](https://github.com/wadakatu/laravel-spectrum/commit/cc5c76ee3f7b5c5e6f851a682ee436832eb561cf))
* インクリメンタルキャッシュと基本キャッシュクラスを追加 ([cf309f1](https://github.com/wadakatu/laravel-spectrum/commit/cf309f160247acce512d86a0853ca63faa45bcae))
* エクスポート機能の設定とサービスプロバイダー登録を追加 ([5437031](https://github.com/wadakatu/laravel-spectrum/commit/5437031495ff24d5c123085848685f14abd8f2bc))
* エラーコレクタークラスを実装 ([8954849](https://github.com/wadakatu/laravel-spectrum/commit/895484924f1822b60929d4bb70e16d1666b1c420))
* エラーハンドリング設定を追加 ([969c7fe](https://github.com/wadakatu/laravel-spectrum/commit/969c7fe30d4bed424e7f55d37faf90eb34d7e29c))
* トップページを復元し、日本語化対応を追加 ([5383cf3](https://github.com/wadakatu/laravel-spectrum/commit/5383cf3d53acbedfc8d4d09b8a2ccfed379735a6))
* パフォーマンス最適化のためのコアクラスを追加 ([2d7fa96](https://github.com/wadakatu/laravel-spectrum/commit/2d7fa96de95d3a5ca5ba5ac58d5f8b08b8b1350a))
* パフォーマンス設定セクションを追加 ([edb08c8](https://github.com/wadakatu/laravel-spectrum/commit/edb08c8d1b5e58aac192c6f645d012d6bb308496))
* プロジェクト専用の画像アセットを追加（favicon、ソーシャルカード、ロゴ） ([b43aa83](https://github.com/wadakatu/laravel-spectrum/commit/b43aa839d45730b00c7c9447fc7521bb93b3c5e6))
* モックサーバーの設定とコマンド登録を追加 ([cf400fc](https://github.com/wadakatu/laravel-spectrum/commit/cf400fc034a5b1604a1192fb5fcb244c58a8dea9))
* レスポンスボディ自動検出機能の実装 ([6d84e33](https://github.com/wadakatu/laravel-spectrum/commit/6d84e33aa4080ba958c7365dcd9e108bcabf6562))
* レスポンス検出の設定オプションを追加 ([f6c7706](https://github.com/wadakatu/laravel-spectrum/commit/f6c770656ca43389e93f40b3a381d026853804b9))
* 例生成のための設定項目を追加 ([511305f](https://github.com/wadakatu/laravel-spectrum/commit/511305f7338a5fd95acf2af08c7a9589ff88df84))
* 最適化版の生成コマンドを追加 ([ec59267](https://github.com/wadakatu/laravel-spectrum/commit/ec59267490785bb72664080448b6e438e8d137bc))
* 各Analyzerにエラーハンドリングを追加 ([be4ce55](https://github.com/wadakatu/laravel-spectrum/commit/be4ce550967c924615d94331f61f39850be5accf))
* 新しいエージェント設定を追加（task-orchestrator、php-backend-engineer、documentation-maintainer） ([d7651a6](https://github.com/wadakatu/laravel-spectrum/commit/d7651a6659b96667c89721b66cdad820b68da901))
* 既存クラスにレスポンス解析機能を統合 ([627a5c9](https://github.com/wadakatu/laravel-spectrum/commit/627a5c9c98fa818bf309aa5cb99baec9a235e3c9))
* 日本語翻訳ファイルを追加（navbar、footer、homepage機能説明） ([1d7c37b](https://github.com/wadakatu/laravel-spectrum/commit/1d7c37bc70bf04cd345e135dfe608fd5aef4d3b7))
* 条件付きバリデーションルールのAST解析とoneOfスキーマ生成機能を実装 ([d2172b0](https://github.com/wadakatu/laravel-spectrum/commit/d2172b037a270c5b08f20b2606c913bcd2fd5990))


### Bug Fixes

* AST visitorがRuleオブジェクトと動的ルールを正しく処理するよう修正 ([a8135dd](https://github.com/wadakatu/laravel-spectrum/commit/a8135dda50aca95db51230ac9d4a9f1a0677d745))
* contributing.mdの壊れたリンクを修正 ([6a0fd76](https://github.com/wadakatu/laravel-spectrum/commit/6a0fd765831e10ae99a09cc3be598b0a4130ab65))
* docs-site/package-lock.jsonをGitに追加（CI修正） ([8d8b41d](https://github.com/wadakatu/laravel-spectrum/commit/8d8b41d6cdfc718c352a64014110012ad99de78e))
* PHPStanのエラーを修正（EnumAnalyzerの型推論問題） ([fea63cf](https://github.com/wadakatu/laravel-spectrum/commit/fea63cff6dd3ae417db300304a16cf4229bfd0b6))
* Resourceタイプのレスポンスは既存のResourceAnalyzerを使用するように修正 ([cdd04cc](https://github.com/wadakatu/laravel-spectrum/commit/cdd04cce3d6d6ea198d00f6c203a6f37a3586f0d))
* シンボリックリンクをコピースクリプトに変更（ビルドエラー修正） ([43f1806](https://github.com/wadakatu/laravel-spectrum/commit/43f1806e80036a9936a3ce00eb79f497fada746e))


### Miscellaneous Chores

* GitHubアクションの自動化改善とセキュリティ強化 ([f18162b](https://github.com/wadakatu/laravel-spectrum/commit/f18162b04e2c003080a847f3862cc56e8f8fb66b))
* PHPUnit deprecation対応 - [@test](https://github.com/test)アノテーションを#[Test]属性に移行 ([e50fd23](https://github.com/wadakatu/laravel-spectrum/commit/e50fd230c9d91c1def5a5e2c6c364e9f6ba3d94b))
* spatie/forkパッケージを追加 ([ce1e04b](https://github.com/wadakatu/laravel-spectrum/commit/ce1e04bc9aa571687e0f7fc59eca427edc6a9bc8))
* update banner version to v0.0.18-alpha ([c48f11c](https://github.com/wadakatu/laravel-spectrum/commit/c48f11c14092c3dfce3a2e419d012dceedda73d0))
* デモアプリケーションでFaker統合の動作確認 ([17bab7f](https://github.com/wadakatu/laravel-spectrum/commit/17bab7f0df860e26f7e859822781f83782bd9d29))
* 不要なファイルを削除（blogディレクトリ、サンプルページ） ([71ac080](https://github.com/wadakatu/laravel-spectrum/commit/71ac080eff15fb76498e008f7d863f01a982cb84))

## [0.1.0-beta](https://github.com/wadakatu/laravel-spectrum/releases/tag/v0.1.0-beta) (2025-07-28)

### ✨ Features

* Zero-annotation API documentation generation
* Automatic analysis of FormRequests and validation rules
* API Resource detection and schema generation
* Real-time documentation preview with hot reload
* Export to Postman and Insomnia formats
* Built-in mock server from OpenAPI specs
* Performance optimization with intelligent caching
* Support for Laravel 10.x, 11.x, and 12.x
* Support for PHP 8.1+

### 🚀 Initial Release

This is the first beta release of Laravel Spectrum. We're excited to share this tool with the Laravel community!

<!-- Release notes generated by Release Please. DO NOT EDIT. -->
