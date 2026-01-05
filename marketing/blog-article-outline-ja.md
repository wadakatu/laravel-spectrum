# ブログ記事アウトライン（日本語）

## 記事1: 導入記事（Zenn/Qiita向け）

### タイトル案
- 「Laravel APIドキュメントを5分で自動生成！アノテーション不要のLaravel Spectrum」
- 「Swagger-PHPのアノテーション地獄から解放される方法」
- 「既存のLaravelプロジェクトにOpenAPIドキュメントを後付けする最速の方法」

### 構成

#### 1. リード文（問題提起）
- API開発でドキュメント作成は面倒
- Swagger-PHPはアノテーションだらけになる
- コードとドキュメントの乖離問題

#### 2. Laravel Spectrumとは
- ゼロアノテーションで自動生成
- 既存のコードをそのまま解析
- v1.0.0リリース！

#### 3. クイックスタート（5分で完了）
```bash
composer require wadakatu/laravel-spectrum
php artisan vendor:publish --tag=spectrum-config
php artisan spectrum:generate
```

#### 4. 何が解析されるか
- FormRequestのvalidation rules
- API Resourceの構造
- コントローラーのレスポンス
- 認証ミドルウェア

#### 5. 便利な機能紹介
- `spectrum:watch` でホットリロード
- `spectrum:mock` でモックサーバー起動
- Postman/Insomniaエクスポート

#### 6. 競合ツールとの比較表
| 機能 | Spectrum | Swagger-PHP | Scribe |
|------|----------|-------------|--------|
| アノテーション不要 | ✅ | ❌ | △ |
| セットアップ時間 | 5分 | 数時間 | 30分 |
| モックサーバー | ✅ | ❌ | ❌ |

#### 7. まとめ・CTA
- GitHubへのリンク
- ドキュメントへのリンク
- Star / Followのお願い

---

## 記事2: 比較記事

### タイトル案
- 「2025年版：LaravelのAPIドキュメント生成ツール徹底比較」
- 「Swagger-PHP vs Scribe vs Laravel Spectrum：どれを選ぶべき？」

### 構成
1. 各ツールの概要
2. 機能比較表
3. ユースケース別おすすめ
4. 移行ガイド（Swagger-PHP → Spectrum）
5. ROI計算（時間節約効果）

---

## 記事3: ユースケース記事

### タイトル案
- 「レガシーLaravelプロジェクトにAPIドキュメントを導入した話」
- 「チームのAPI開発効率を劇的に改善したツール」

### 構成
1. 背景・課題
2. ツール選定の経緯
3. 導入手順
4. Before/After比較
5. チームの反応・効果測定

---

## 記事共通のCTA

```markdown
## リンク

- GitHub: https://github.com/wadakatu/laravel-spectrum
- ドキュメント: [公式ドキュメントURL]
- Packagist: https://packagist.org/packages/wadakatu/laravel-spectrum

気に入ったらGitHubでStarをお願いします！
```

## ハッシュタグ

Qiita/Zenn: `#Laravel` `#PHP` `#OpenAPI` `#Swagger` `#API`
