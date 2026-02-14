# Laravel Spectrum

<p align="center">
  <img src="/assets/banner.svg" alt="Laravel Spectrum Banner" width="100%">
</p>

<p align="center">
  <a href="https://github.com/wadakatu/laravel-spectrum/actions"><img src="https://github.com/wadakatu/laravel-spectrum/workflows/Tests/badge.svg" alt="Tests"></a>
  <a href="https://codecov.io/gh/wadakatu/laravel-spectrum"><img src="https://codecov.io/gh/wadakatu/laravel-spectrum/branch/main/graph/badge.svg" alt="Code Coverage"></a>
  <a href="https://packagist.org/packages/wadakatu/laravel-spectrum"><img src="https://img.shields.io/packagist/v/wadakatu/laravel-spectrum" alt="Latest Stable Version"></a>
  <a href="https://packagist.org/packages/wadakatu/laravel-spectrum"><img src="https://img.shields.io/packagist/dt/wadakatu/laravel-spectrum" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/wadakatu/laravel-spectrum"><img src="https://img.shields.io/packagist/l/wadakatu/laravel-spectrum" alt="License"></a>
</p>

<p align="center">
  <strong>Zero-annotation OpenAPI documentation generator for Laravel</strong>
  <br>
  <em>Generate complete API docs from your existing code in seconds. No annotations required.</em>
</p>

<p align="center">
  <a href="https://wadakatu.github.io/laravel-spectrum/">Documentation</a> •
  <a href="https://wadakatu.github.io/laravel-spectrum/docs/quick-start">Quick Start</a> •
  <a href="https://wadakatu.github.io/laravel-spectrum/docs/comparison">Compare</a>
</p>

---

## The Problem

```php
// ❌ Traditional approach: Annotations everywhere
/**
 * @OA\Post(
 *     path="/api/users",
 *     @OA\RequestBody(
 *         @OA\JsonContent(
 *             @OA\Property(property="name", type="string"),
 *             @OA\Property(property="email", type="string", format="email"),
 *             // ... 50 more lines of annotations
 *         )
 *     ),
 *     @OA\Response(response="200", description="Success")
 * )
 */
public function store(StoreUserRequest $request) { ... }
```

**With Laravel Spectrum: Zero annotations needed.** Your existing `FormRequest` and `Resource` classes are your documentation.

---

## Quick Start (30 seconds)

```bash
# Install
composer require wadakatu/laravel-spectrum --dev

# Generate OpenAPI documentation
php artisan spectrum:generate

# View in browser (HTML with Swagger UI)
php artisan spectrum:generate --format=html
# Open: storage/app/spectrum/openapi.html
```

**That's it.** Full OpenAPI 3.1 documentation generated from your existing code.

---

## What Gets Analyzed Automatically

| Your Code | Generated Documentation |
|-----------|------------------------|
| `FormRequest::rules()` | Request body schemas with validation |
| `$request->validate([...])` | Inline validation rules |
| `API Resources` | Response schemas |
| Auth middleware (`auth:sanctum`) | Security schemes |
| Route parameters (`{user}`) | Path parameters with types |
| `@deprecated` PHPDoc | Deprecated operation flags |

---

## Key Features

### Real-time Documentation
```bash
php artisan spectrum:watch
# Browser auto-refreshes when you change code
```

### Built-in Mock Server
```bash
php artisan spectrum:mock
# Frontend team can develop without waiting for backend
```

### Export to API Clients
```bash
php artisan spectrum:export postman    # Postman collection
php artisan spectrum:export insomnia   # Insomnia workspace
```

### High Performance
- Parallel processing for large codebases
- Incremental generation (only changed files)
- Smart caching

---

## Why Laravel Spectrum?

| | Laravel Spectrum | Swagger-PHP | Scribe |
|---|:---:|:---:|:---:|
| Zero annotations | ✅ | ❌ | Partial |
| Setup time | **30 sec** | Hours | ~30 min |
| FormRequest detection | ✅ | ❌ | ✅ |
| Mock server | ✅ | ❌ | ❌ |
| Live reload | ✅ | ❌ | ❌ |
| Postman/Insomnia export | ✅ | ❌ | ✅ |
| OpenAPI 3.1 | ✅ | ✅ | ❌ |

---

## Requirements

- PHP 8.2+
- Laravel 11.x or 12.x

## Compliance Check (Demo App Matrix)

Use the bundled demo apps to verify OpenAPI 3.0/3.1 compliance in one run:

```bash
./demo-app/check-openapi-compliance.sh
```

Details: `demo-app/README.md`

---

## Documentation

📖 **[Full Documentation](https://wadakatu.github.io/laravel-spectrum/)**

- [Installation](https://wadakatu.github.io/laravel-spectrum/docs/installation)
- [Configuration](https://wadakatu.github.io/laravel-spectrum/docs/config-reference)
- [CLI Commands](https://wadakatu.github.io/laravel-spectrum/docs/cli-reference)
- [Comparison with Other Tools](https://wadakatu.github.io/laravel-spectrum/docs/comparison)

---

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](./CONTRIBUTING.md) for details.

## License

Laravel Spectrum is open-source software licensed under the [MIT license](./LICENSE).

---

<p align="center">
  <a href="https://github.com/wadakatu/laravel-spectrum">
    <img src="https://img.shields.io/github/stars/wadakatu/laravel-spectrum?style=social" alt="Star on GitHub">
  </a>
  <br><br>
  Made with ❤️ by <a href="https://github.com/wadakatu">wadakatu</a>
</p>
