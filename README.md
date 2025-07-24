# Laravel Spectrum

<p align="center">
  <img src="assets/banner.svg" alt="Laravel Spectrum Banner" width="100%">
</p>

<p align="center">
  <img src="https://img.shields.io/github/v/release/wadakatu/laravel-spectrum?style=for-the-badge&label=LATEST%20VERSION&color=00FF00" alt="Latest Version">
</p>

[![Tests](https://github.com/wadakatu/laravel-spectrum/workflows/Tests/badge.svg)](https://github.com/wadakatu/laravel-spectrum/actions)
[![Code Coverage](https://codecov.io/gh/wadakatu/laravel-spectrum/branch/main/graph/badge.svg)](https://codecov.io/gh/wadakatu/laravel-spectrum)
[![Latest Stable Version](https://poser.pugx.org/wadakatu/laravel-spectrum/v)](https://packagist.org/packages/wadakatu/laravel-spectrum)
[![Total Downloads](https://poser.pugx.org/wadakatu/laravel-spectrum/downloads)](https://packagist.org/packages/wadakatu/laravel-spectrum)
[![License](https://poser.pugx.org/wadakatu/laravel-spectrum/license)](https://packagist.org/packages/wadakatu/laravel-spectrum)
[![PHP Version Require](https://poser.pugx.org/wadakatu/laravel-spectrum/require/php)](https://packagist.org/packages/wadakatu/laravel-spectrum)

> 🎯 **Zero-annotation API documentation generator for Laravel & Lumen**
> 
> Transform your existing Laravel/Lumen APIs into comprehensive OpenAPI 3.0 documentation without writing a single annotation or modifying your code.

## 🌟 Why Laravel Spectrum?

**Stop writing documentation. Start generating it.**

Laravel Spectrum analyzes your existing code and automatically generates beautiful, accurate API documentation. No annotations, no manual updates, no hassle.

✅ **Save Hours** - No more manual documentation writing  
✅ **Always Up-to-Date** - Documentation that evolves with your code  
✅ **Zero Learning Curve** - Works with your existing Laravel/Lumen code  
✅ **Production Ready** - Battle-tested with real-world applications

## ✨ Key Features

### 🎯 Everything You Need, Nothing You Don't

<table width="100%">
<tr>
<td width="33%" valign="top">

<div align="center">
<h3>🚀 Zero Setup</h3>
</div>

**Just works out of the box**
- No annotations needed
- No configuration files
- No manual updates
- Instant documentation

</td>
<td width="33%" valign="top">

<div align="center">
<h3>🧠 Smart Detection</h3>
</div>

**Understands your code**
- FormRequest validation
- Inline validation rules
- API Resources mapping
- Fractal Transformers

</td>
<td width="33%" valign="top">

<div align="center">
<h3>⚡ Real-time Updates</h3>
</div>

**Live documentation preview**
- Hot reload on changes
- WebSocket updates
- Smart caching
- Instant regeneration

</td>
</tr>
</table>

### 📋 Comprehensive Feature Set

<table width="100%">
<tr>
<td width="50%" valign="top">

**📝 Request Analysis**
- ✅ FormRequest validation parsing
- ✅ Inline validation detection
- ✅ File upload handling
- ✅ Query parameter extraction
- ✅ Enum constraint support
- ✅ Nested array validation
- ✅ Conditional validation rules

**📦 Response Handling**
- ✅ API Resources structure
- ✅ Fractal Transformer support
- ✅ Pagination detection
- ✅ Conditional attributes
- ✅ Nested resources
- ✅ Collection wrapping
- ✅ Realistic examples with Faker
- ✅ Custom example mapping

</td>
<td width="50%" valign="top">

**🔐 Security & Auth**
- ✅ Bearer Token (JWT/Sanctum)
- ✅ API Key authentication
- ✅ OAuth2 flow support
- ✅ Per-route security
- ✅ Custom middleware
- ✅ Global auth settings

**🛡️ Error Documentation**
- ✅ Validation errors (422)
- ✅ Auth errors (401/403)
- ✅ Not found (404)
- ✅ Custom error formats
- ✅ Field-level messages
- ✅ Multiple response types

</td>
</tr>
</table>


## 📊 Why Choose Laravel Spectrum?

### Compare with Other Solutions

| Feature | Laravel Spectrum | Swagger-PHP | L5-Swagger | Scribe |
|---------|-----------------|-------------|------------|---------|
| **Zero Annotations** | ✅ | ❌ | ❌ | ⚠️ Partial |
| **Auto-detect Validation** | ✅ | ❌ | ❌ | ✅ |
| **API Resources Support** | ✅ | ❌ | ❌ | ✅ |
| **Fractal Support** | ✅ | ❌ | ❌ | ❌ |
| **File Upload Detection** | ✅ | Manual | Manual | ✅ |
| **Query Param Detection** | ✅ | ❌ | ❌ | ⚠️ Limited |
| **Enum Support** | ✅ | Manual | Manual | ❌ |
| **Conditional Validation** | ✅ | ❌ | ❌ | ❌ |
| **Live Reload** | ✅ | ❌ | ❌ | ❌ |
| **Smart Caching** | ✅ | ❌ | ❌ | ❌ |
| **Pagination Detection** | ✅ | ❌ | ❌ | ✅ |
| **Setup Time** | < 1 min | Hours | Hours | Minutes |

### 🎯 Perfect For

- ✅ **Existing Projects** - Document your current API without any changes
- ✅ **Rapid Development** - Documentation that keeps up with your pace
- ✅ **Team Collaboration** - Everyone sees the same, accurate documentation
- ✅ **CI/CD Integration** - Generate docs as part of your build process
- ✅ **API-First Development** - Design by coding, not by writing YAML

## 🔧 Requirements

- **PHP** 8.1 or higher
- **Laravel** 10.x, 11.x, or 12.x / **Lumen** 10.x, 11.x, 12.x
- **Composer** 2.0 or higher

## 🚀 Getting Started

### 1. Install

```bash
composer require wadakatu/laravel-spectrum --dev
```

### 2. Generate Documentation

```bash
# Generate OpenAPI documentation
php artisan spectrum:generate

# Generate in YAML format
php artisan spectrum:generate --format=yaml

# Custom output path
php artisan spectrum:generate --output=public/api-impl_docs.json
```

### 3. Live Preview (Development)

```bash
# Start the watcher with hot reload
php artisan spectrum:watch

# Visit http://localhost:8080 to see your documentation
```

### 4. View Documentation

```html
<!-- Add to your blade template -->
<div id="swagger-ui"></div>
<script src="https://unpkg.com/swagger-ui-dist/swagger-ui-bundle.js"></script>
<script>
SwaggerUIBundle({
    url: "/storage/app/spectrum/openapi.json",
    dom_id: '#swagger-ui',
})
</script>
```

**That's it!** Your comprehensive API documentation is ready in seconds.

## 🎯 Advanced Features

### Conditional Validation Rules Support

Laravel Spectrum now automatically detects and documents conditional validation rules in your FormRequest classes. This feature generates OpenAPI 3.0 `oneOf` schemas to accurately represent different validation scenarios based on HTTP methods or other conditions.

#### Example FormRequest with Conditional Rules:

```php
class UserRequest extends FormRequest
{
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return [
                'email' => 'required|email|unique:users',
                'password' => 'required|min:8',
                'role' => 'required|in:admin,user,moderator',
            ];
        }

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            return [
                'email' => 'sometimes|email',
                'current_password' => 'required|string',
            ];
        }

        return [
            'name' => 'required|string',
        ];
    }
}
```

#### Generated OpenAPI Schema:

```json
{
  "requestBody": {
    "content": {
      "application/json": {
        "schema": {
          "oneOf": [
            {
              "type": "object",
              "properties": {
                "email": { "type": "string", "format": "email" },
                "password": { "type": "string", "minLength": 8 },
                "role": { "type": "string", "enum": ["admin", "user", "moderator"] }
              },
              "required": ["email", "password", "role"],
              "description": "When HTTP method is POST"
            },
            {
              "type": "object",
              "properties": {
                "email": { "type": "string", "format": "email" },
                "current_password": { "type": "string" }
              },
              "required": ["current_password"],
              "description": "When $this->isMethod('PUT') || $this->isMethod('PATCH')"
            }
          ]
        }
      }
    }
  }
}
```

#### Supported Patterns:

- **HTTP Method Conditions**: `$this->isMethod('POST')`, `request()->isMethod('GET')`
- **Nested Conditions**: Multiple levels of if/elseif/else statements
- **Complex Conditions**: `$this->user() && $this->user()->isAdmin()`
- **Variable Assignments**: Rules stored in variables and merged with `array_merge()`
- **Rule Class Methods**: `Rule::in()`, `Rule::unique()`, `Rule::requiredIf()`
- **Early Returns**: Different rule sets returned based on conditions

This feature ensures your API documentation accurately reflects the actual validation behavior of your application, making it easier for API consumers to understand exactly what data is required for each endpoint under different circumstances.


## 📚 Documentation

- **[Configuration Guide](./docs/configuration.md)** - Detailed configuration options
- **[Real-World Examples](./docs/examples.md)** - Practical examples and use cases
- **[Advanced Features](./docs/advanced-features.md)** - Advanced functionality
- **[Conditional Validation](./docs/conditional-validation.md)** - Conditional validation rules documentation
- **[Troubleshooting](./docs/troubleshooting.md)** - Common issues and solutions


## 🔧 Quick Troubleshooting

- **Routes not appearing?** Check route patterns in config match your routes
- **Validation not detected?** Ensure FormRequest is properly type-hinted
- **Need help?** See our [detailed troubleshooting guide](./docs/troubleshooting.md)

## 🤝 Contributing

We welcome contributions! Here's how you can help:

```bash
# 1. Fork and clone the repository
git clone https://github.com/wadakatu/laravel-spectrum.git
cd laravel-spectrum

# 2. Install dependencies
composer install

# 3. Run tests
composer test

# 4. Check code quality
composer analyze      # PHPStan analysis
composer format:fix   # Fix code style

# 5. Make your changes and submit a PR!
```

### Development Commands

- `composer test` - Run all tests
- `composer test-coverage` - Generate coverage report
- `composer analyze` - Run static analysis
- `composer format` - Check code style
- `composer format:fix` - Fix code style

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

---

<p align="center">
  Made with ❤️ by <a href="https://github.com/wadakatu">Wadakatu</a>
  <br><br>
  <a href="https://github.com/wadakatu/laravel-spectrum">
    <img src="https://img.shields.io/github/stars/wadakatu/laravel-spectrum?style=social" alt="Star on GitHub">
  </a>
  &nbsp;&nbsp;
  <a href="https://twitter.com/intent/tweet?text=Check%20out%20Laravel%20Spectrum%20-%20Zero-annotation%20API%20documentation%20generator%20for%20Laravel!&url=https://github.com/wadakatu/laravel-spectrum">
    <img src="https://img.shields.io/twitter/url?style=social&url=https%3A%2F%2Fgithub.com%2Fwadakatu%2Flaravel-spectrum" alt="Tweet">
  </a>
</p>