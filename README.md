# Laravel Spectrum

<p align="center">
  <img src="/assets/banner.svg" alt="Laravel Spectrum Banner" width="100%">
</p>

<p align="center">
  <a href="https://github.com/wadakatu/laravel-spectrum/actions"><img src="https://github.com/wadakatu/laravel-spectrum/workflows/Tests/badge.svg" alt="Tests"></a>
  <a href="https://codecov.io/gh/wadakatu/laravel-spectrum"><img src="https://codecov.io/gh/wadakatu/laravel-spectrum/branch/main/graph/badge.svg" alt="Code Coverage"></a>
  <a href="https://packagist.org/packages/wadakatu/laravel-spectrum"><img src="https://poser.pugx.org/wadakatu/laravel-spectrum/v" alt="Latest Stable Version"></a>
  <a href="https://packagist.org/packages/wadakatu/laravel-spectrum"><img src="https://poser.pugx.org/wadakatu/laravel-spectrum/downloads" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/wadakatu/laravel-spectrum"><img src="https://poser.pugx.org/wadakatu/laravel-spectrum/license" alt="License"></a>
</p>

> 🎯 **Zero-annotation API documentation generator for Laravel**
>
> Laravel Spectrum analyzes your existing code and automatically generates OpenAPI 3.0 documentation. No annotations required, minimal configuration, ready to use immediately.

## ✨ Why Laravel Spectrum?

**Stop writing documentation. Start generating it.**

- 🚀 **Zero Configuration** - Just install and run the command
- 🧠 **Smart Detection** - Automatically analyzes FormRequests, validation rules, and API Resources
- ⚡ **Real-time Updates** - Instantly reflects code changes in documentation
- 📤 **Export Features** - Direct export to Postman and Insomnia
- 🎭 **Mock Server** - Automatically launches mock API from OpenAPI documentation
- 🎯 **Production Ready** - High-performance even for large-scale projects

## 🚀 Quick Start

### 1. Installation

```bash
composer require wadakatu/laravel-spectrum --dev
```

### 2. Generate Documentation

```bash
php artisan spectrum:generate
```

### 3. Live Preview (Development)

```bash
php artisan spectrum:watch
# Visit http://localhost:8080 to see your documentation
```

### 4. View in Browser

```html
<!-- Add to your Blade template -->
<div id="swagger-ui"></div>
<script src="https://unpkg.com/swagger-ui-dist/swagger-ui-bundle.js"></script>
<script>
SwaggerUIBundle({
    url: "/storage/app/spectrum/openapi.json",
    dom_id: '#swagger-ui',
})
</script>
```

### 5. Launch Mock Server

```bash
php artisan spectrum:mock
# Mock API server launches at http://localhost:8081
```

**That's it!** Your comprehensive API documentation is ready in seconds.

## 📚 Documentation

📖 **[View Full Documentation](https://wadakatu.github.io/laravel-spectrum/)**

For detailed usage and advanced features, visit our comprehensive documentation site.

The documentation covers:

- 🔧 **Getting Started** - Installation, configuration, basic usage
- 🎯 **Features Guide** - Validation detection, response analysis, authentication, mock server
- ⚡ **Advanced Usage** - Performance optimization, export features, CI/CD integration
- 📖 **Reference** - CLI commands, configuration options, troubleshooting
- 🤝 **More** - Comparison with other tools, contribution guide

## 🤝 Contributing

We welcome bug reports, feature requests, and pull requests! See the [Contribution Guide](./contributing.md) for details.

## 📄 License

Laravel Spectrum is open-source software licensed under the MIT license. See the [LICENSE](../../LICENSE) file for details.

---

<p align="center">
  Made with ❤️ by <a href="https://github.com/wadakatu">Wadakatu</a>
  <br><br>
  <a href="https://github.com/wadakatu/laravel-spectrum">
    <img src="https://img.shields.io/github/stars/wadakatu/laravel-spectrum?style=social" alt="Star on GitHub">
  </a>
</p>