# Laravel Prism

[![Tests](https://github.com/wadakatu/laravel-prism/workflows/Tests/badge.svg)](https://github.com/wadakatu/laravel-prism/actions)
[![Code Coverage](https://codecov.io/gh/wadakatu/laravel-prism/branch/main/graph/badge.svg)](https://codecov.io/gh/wadakatu/laravel-prism)
[![Latest Stable Version](https://poser.pugx.org/wadakatu/laravel-prism/v)](https://packagist.org/packages/wadakatu/laravel-prism)
[![Total Downloads](https://poser.pugx.org/wadakatu/laravel-prism/downloads)](https://packagist.org/packages/wadakatu/laravel-prism)
[![License](https://poser.pugx.org/wadakatu/laravel-prism/license)](https://packagist.org/packages/wadakatu/laravel-prism)
[![PHP Version Require](https://poser.pugx.org/wadakatu/laravel-prism/require/php)](https://packagist.org/packages/wadakatu/laravel-prism)

Zero-annotation API documentation generator for Laravel.

## Features

- 🚀 **Zero Configuration** - Automatically detects API routes and generates documentation
- 📝 **FormRequest Support** - Extracts validation rules and generates request schemas
- 📦 **API Resource Support** - Analyzes resource classes to document responses
- 🔍 **Type Inference** - Intelligently infers types from validation rules
- 📄 **OpenAPI 3.0** - Generates standard OpenAPI specification
- 🎨 **Swagger UI Ready** - Compatible with Swagger UI for interactive documentation

## Requirements

- PHP 8.1 or higher
- Laravel 10.x, 11.x, or 12.x

## Installation

```bash
composer require laravel-prism/laravel-prism
```

## Usage

### Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=prism-config
```

### Generate Documentation

```bash
php artisan prism:generate
```

Options:
- `--format=yaml` - Generate YAML format instead of JSON
- `--output=/path/to/file` - Custom output path

### Configuration Options

```php
// config/prism.php

return [
    'title' => 'My API',
    'version' => '1.0.0',
    'description' => 'API Documentation',
    'route_patterns' => [
        'api/*',
        'api/v1/*',
    ],
    'excluded_routes' => [
        'api/health',
        'api/ping',
    ],
];
```

## Example

Given a controller with FormRequest and API Resource:

```php
// UserController.php
public function store(StoreUserRequest $request)
{
    $user = User::create($request->validated());
    return new UserResource($user);
}

// StoreUserRequest.php
public function rules(): array
{
    return [
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users',
        'age' => 'required|integer|min:18',
    ];
}

// UserResource.php
public function toArray($request): array
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'email' => $this->email,
        'created_at' => $this->created_at->toDateTimeString(),
    ];
}
```

Laravel Prism will automatically generate OpenAPI documentation with:
- Request body schema with validation rules
- Response schema from the Resource class
- Proper types inferred from validation rules
- Examples for each field

## License

MIT License