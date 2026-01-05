# Social Media Post Templates

## X (Twitter) Posts

### Launch Announcement (Japanese)

```
Laravel Spectrum v1.0.0 をリリースしました！

LaravelアプリからOpenAPI/Swaggerドキュメントを自動生成するツールです。

特徴：
- アノテーション不要
- 5分でセットアップ完了
- ホットリロード対応
- モックサーバー内蔵

https://github.com/wadakatu/laravel-spectrum

#Laravel #PHP #OpenAPI #Swagger
```

### Launch Announcement (English)

```
Excited to announce Laravel Spectrum v1.0.0!

Generate OpenAPI/Swagger docs from your Laravel app automatically.

No annotations needed. Just:
1. composer require wadakatu/laravel-spectrum
2. php artisan spectrum:generate
3. Done!

https://github.com/wadakatu/laravel-spectrum

#Laravel #PHP #OpenAPI #WebDev
```

### Feature Highlight Posts (Thread-worthy)

**Post 1: Problem**
```
Tired of writing Swagger annotations for every endpoint?

I built Laravel Spectrum to solve this.

It analyzes your existing code and generates OpenAPI docs automatically.

Thread on how it works:
```

**Post 2: How It Works**
```
How Laravel Spectrum works:

1. Scans your routes
2. Analyzes FormRequest validation → Request schemas
3. Analyzes API Resources → Response schemas
4. Detects auth middleware → Security schemes

All without touching your code.
```

**Post 3: Demo**
```
Here's a 30-second demo:

[GIF or video]

composer require wadakatu/laravel-spectrum
php artisan spectrum:generate

That's it. Full OpenAPI spec generated.
```

### Weekly Tips (Rotate these)

```
Laravel tip: Need a mock API server for frontend development?

Laravel Spectrum has one built-in:

php artisan spectrum:mock

Instant mock server from your OpenAPI spec.

https://github.com/wadakatu/laravel-spectrum
```

```
Laravel tip: Keep your API docs always up-to-date with:

php artisan spectrum:watch

Hot reloads your browser when code changes.

No more outdated documentation!

#Laravel #DevTips
```

```
Did you know? Laravel Spectrum can export to:
- Postman collections
- Insomnia workspaces

One command: php artisan spectrum:export postman

https://github.com/wadakatu/laravel-spectrum
```

---

## Reddit Posts

### r/laravel Introduction Post

**Title:** `[Package] Laravel Spectrum v1.0.0 - Zero-annotation OpenAPI/Swagger doc generator`

**Body:**
```markdown
Hey r/laravel!

I've been working on Laravel Spectrum, a tool that generates OpenAPI/Swagger documentation from your existing Laravel code - no annotations required.

**The Problem**

We've all been there: you need API documentation, but adding Swagger annotations to every endpoint is tedious and they quickly get out of sync with your actual code.

**The Solution**

Laravel Spectrum analyzes your existing code:
- FormRequest validation rules → Request schemas
- API Resources → Response schemas
- Controller return types → Response definitions
- Auth middleware → Security schemes

**Quick Start**

```bash
composer require wadakatu/laravel-spectrum
php artisan spectrum:generate
```

That's it. Check `storage/app/spectrum/openapi.json`.

**Features**

- Zero annotations required
- Hot reload with `spectrum:watch`
- Built-in mock server with `spectrum:mock`
- Export to Postman/Insomnia
- Supports Laravel 11 & 12, PHP 8.2+

**Links**

- GitHub: https://github.com/wadakatu/laravel-spectrum
- Docs: [URL]
- Packagist: https://packagist.org/packages/wadakatu/laravel-spectrum

Would love to hear your feedback! What features would you like to see?
```

---

### r/php Post

**Title:** `Laravel Spectrum: Zero-annotation API documentation generator`

**Body:**
```markdown
Just released v1.0.0 of Laravel Spectrum - a tool that generates OpenAPI documentation from Laravel applications without requiring any annotations.

It uses PHP-Parser for AST analysis and Reflection to understand your code structure:

- Parses FormRequest::rules() for request validation
- Analyzes API Resource transformations
- Detects response types from controller methods
- Identifies auth requirements from middleware

Built with:
- nikic/php-parser for static analysis
- spatie/fork for parallel processing
- workerman for the live-reload server

Happy to discuss the implementation or answer questions!

GitHub: https://github.com/wadakatu/laravel-spectrum
```

---

## LinkedIn Post (Optional)

```
Excited to announce the v1.0.0 release of Laravel Spectrum!

After months of development, this open-source tool helps Laravel developers generate API documentation automatically from their existing codebase.

Key features:
- Zero annotation approach
- Automatic validation rule detection
- Built-in mock server for frontend development
- Real-time documentation updates

Perfect for teams who want up-to-date API docs without the maintenance overhead.

Check it out: https://github.com/wadakatu/laravel-spectrum

#OpenSource #Laravel #PHP #APIDocumentation #DeveloperTools
```

---

## Post Schedule Suggestion

| Week | Platform | Content |
|------|----------|---------|
| 1 | X (EN + JA) | Launch announcement |
| 1 | Reddit r/laravel | Introduction post |
| 2 | X | Feature highlight: mock server |
| 2 | Zenn/Qiita | Full article |
| 3 | X | Feature highlight: hot reload |
| 3 | Dev.to | Full article |
| 4 | Reddit r/php | Technical deep-dive |
| 4 | X | Tip of the week |

## Hashtags Reference

**X (Twitter)**
- English: `#Laravel` `#PHP` `#OpenAPI` `#Swagger` `#WebDev` `#API` `#OpenSource`
- Japanese: `#Laravel` `#PHP` `#OpenAPI` `#Swagger` `#API開発`

**Reddit**
- r/laravel, r/php, r/webdev

**Dev.to / Hashnode**
- laravel, php, openapi, swagger, webdev, tutorial
