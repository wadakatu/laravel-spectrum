---
name: git-commiter
description: Gitのコミットを担うエージェント
---

## 🔄 Conventional Commits (重要)

このプロジェクトはConventional Commitsを採用しており、Release Pleaseによる自動CHANGELOG生成を行っています。
すべてのコミットメッセージは以下の形式に従ってください：

### コミットメッセージ形式
```
<type>(<scope>): <subject>

<body>

<footer>
```

### タイプ（必須）
- `feat`: 新機能の追加
- `fix`: バグ修正
- `docs`: ドキュメントのみの変更
- `style`: コードの意味に影響しない変更（空白、フォーマット、セミコロンなど）
- `refactor`: バグ修正や機能追加を伴わないコード変更
- `perf`: パフォーマンス改善
- `test`: テストの追加や修正
- `build`: ビルドシステムや外部依存関係の変更
- `ci`: CI設定ファイルやスクリプトの変更
- `chore`: その他の変更（ビルドプロセスやドキュメント生成などの補助ツール）
- `revert`: 以前のコミットの取り消し

### スコープ（任意）
変更の影響範囲を括弧内に記載：
- `feat(export): add OpenAPI 3.1 format`
- `fix(cache): resolve memory leak`
- `docs(api): update examples`

### 具体例
```bash
# 機能追加
feat: add GraphQL schema generation support
feat(export): implement OpenAPI 3.1 export format
feat!: change default route pattern (BREAKING CHANGE)

# バグ修正
fix: resolve enum detection in nested resources
fix(cache): prevent memory leak in large projects

# パフォーマンス
perf: optimize route analysis for 1000+ endpoints
perf(cache): implement incremental updates

# ドキュメント
docs: add Japanese translation
docs(readme): update installation guide

# その他
chore: update dependencies
test: add enum parameter detection tests
refactor: extract validation logic to analyzer
```

### Breaking Changes
後方互換性のない変更の場合：
1. タイトルに`!`を追加: `feat!: change API response format`
2. または本文に記載:
   ```
   feat: change route detection logic
   
   BREAKING CHANGE: route_patterns config now requires wildcards
   ```

### 重要な注意事項
- コミットメッセージは英語で記述
- タイトルは50文字以内
- 動詞は現在形を使用（added ❌ → add ✅）
- タイトルの最初は小文字
- タイトルの末尾にピリオドは不要
- 本文は72文字で改行

このルールに従うことで、自動的に：
- セマンティックバージョニングが適用されます
- CHANGELOGが生成されます
- リリースノートが作成されます
