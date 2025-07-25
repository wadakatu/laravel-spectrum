# Export Features

Laravel Spectrum provides powerful export capabilities to convert your OpenAPI documentation into formats compatible with popular API testing tools like Postman and Insomnia.

## Table of Contents

- [Overview](#overview)
- [Postman Export](#postman-export)
- [Insomnia Export](#insomnia-export)
- [Configuration](#configuration)
- [Smart Example Generation](#smart-example-generation)
- [Advanced Usage](#advanced-usage)
- [Troubleshooting](#troubleshooting)

## Overview

The export feature allows you to:

- Convert OpenAPI 3.0 documentation to tool-specific formats
- Generate environment configurations automatically
- Include authentication presets
- Create realistic request examples
- Organize requests by tags/folders
- Add test scripts and pre-request scripts (Postman)

## Postman Export

### Basic Usage

```bash
# Export with default settings
php artisan spectrum:export:postman

# Output files:
# - storage/app/spectrum/postman/postman_collection.json
# - storage/app/spectrum/postman/postman_environment_local.json
```

### Command Options

```bash
php artisan spectrum:export:postman [options]

Options:
  --output=<path>              Custom output directory (default: storage/app/spectrum/postman)
  --environments=<envs>        Comma-separated list of environments (default: local)
  --single-file               Export as single file with embedded environments
```

### Examples

```bash
# Export with multiple environments
php artisan spectrum:export:postman --environments=local,staging,production

# Custom output directory
php artisan spectrum:export:postman --output=./exports/postman

# Full example
php artisan spectrum:export:postman \
  --output=./api-collections \
  --environments=local,staging,production
```

### Features

#### 1. Collection Organization

Routes are automatically organized into folders based on their tags:

```
📁 User Management
  ├── GET /users
  ├── POST /users
  ├── GET /users/{id}
  ├── PUT /users/{id}
  └── DELETE /users/{id}
📁 Authentication
  ├── POST /auth/login
  ├── POST /auth/logout
  └── POST /auth/refresh
```

#### 2. Pre-request Scripts

Automatically generated scripts for:
- Timestamp generation: `{{timestamp}}`
- Request ID generation: `{{request_id}}`
- Dynamic variables based on your API needs

```javascript
// Example pre-request script
pm.variables.set('timestamp', new Date().toISOString());
pm.variables.set('request_id', pm.variables.replaceIn('{{$guid}}'));
```

#### 3. Test Scripts

Automated test generation for each endpoint:

```javascript
// Status code validation
pm.test("Status code is successful", function () {
    pm.response.to.have.status(200);
});

// Response time check
pm.test("Response time is less than 500ms", function () {
    pm.expect(pm.response.responseTime).to.be.below(500);
});

// Response structure validation
pm.test("Response structure", function () {
    const jsonData = pm.response.json();
    pm.expect(jsonData).to.have.property('id');
    pm.expect(jsonData).to.have.property('name');
});
```

#### 4. Environment Variables

Automatically generated environment files include:

- `base_url`: Your API base URL
- `bearer_token`: For Bearer authentication
- `api_key`: For API Key authentication
- Custom variables from your configuration

### Newman Integration

Run your exported collection with Newman:

```bash
# Install Newman
npm install -g newman

# Run collection with environment
newman run postman_collection.json \
  -e postman_environment_local.json

# Run with additional options
newman run postman_collection.json \
  -e postman_environment_production.json \
  --reporters cli,json \
  --reporter-json-export results.json
```

## Insomnia Export

### Basic Usage

```bash
# Export with default settings
php artisan spectrum:export:insomnia

# Output file:
# - storage/app/spectrum/insomnia/insomnia_collection.json
```

### Command Options

```bash
php artisan spectrum:export:insomnia [options]

Options:
  --output=<path>    Custom output file path
```

### Examples

```bash
# Custom output file
php artisan spectrum:export:insomnia --output=./exports/insomnia.json

# Export to specific directory (auto-appends filename)
php artisan spectrum:export:insomnia --output=./api-collections/
```

### Features

#### 1. Workspace Organization

Insomnia export creates a complete workspace with:

- Base environment configuration
- Sub-environments for different deployment stages
- Folder structure based on API tags
- Request groups for better organization

#### 2. Environment Configuration

```json
{
  "base_url": "{{ _.base_url }}",
  "bearer_token": "{{ _.bearer_token }}",
  "api_key": "{{ _.api_key }}"
}
```

#### 3. Request Chaining

Supports request dependencies and variable extraction:

```json
{
  "name": "Create User",
  "body": {
    "text": "{\n  \"name\": \"John Doe\",\n  \"email\": \"john@example.com\"\n}"
  },
  "environment": {
    "user_id": "{% response 'body', '$.id' %}"
  }
}
```

#### 4. Git Sync Support

The exported format is optimized for Git synchronization:

- Stable IDs for requests and folders
- Consistent ordering
- Human-readable JSON formatting

### Team Collaboration

1. Export your collection:
   ```bash
   php artisan spectrum:export:insomnia --output=./insomnia-workspace.json
   ```

2. Commit to Git:
   ```bash
   git add insomnia-workspace.json
   git commit -m "Update API documentation"
   git push
   ```

3. Team members import:
   - Open Insomnia
   - Go to Application → Preferences → Data → Import Data
   - Select the workspace file
   - Enable Git Sync for automatic updates

## Configuration

### Export Configuration

Add to your `config/spectrum.php`:

```php
'export' => [
    'postman' => [
        'include_examples' => true,
        'include_tests' => true,
        'include_pre_request_scripts' => true,
        'group_by_tags' => true,
    ],
    'insomnia' => [
        'include_examples' => true,
        'include_environments' => true,
        'default_timeout' => 30000, // milliseconds
    ],
    'environment_variables' => [
        'custom_var' => 'default_value',
        'api_version' => 'v1',
    ],
    'auth_presets' => [
        'bearer' => [
            'token_name' => 'bearer_token',
            'header_name' => 'Authorization',
            'prefix' => 'Bearer',
        ],
        'api_key' => [
            'token_name' => 'api_key',
            'header_name' => 'X-API-Key',
        ],
    ],
],
```

### Authentication Mapping

Laravel Spectrum automatically maps OpenAPI security schemes:

| OpenAPI Security | Postman Auth | Insomnia Auth |
|-----------------|--------------|---------------|
| `http` + `bearer` | Bearer Token | Bearer Token |
| `apiKey` in header | API Key | Header |
| `apiKey` in query | API Key | Query Parameter |
| `oauth2` | OAuth 2.0 | OAuth 2.0 |

## Smart Example Generation

The export feature includes intelligent example generation based on:

### Field Name Detection

| Field Name Pattern | Generated Example |
|-------------------|------------------|
| `email`, `*_email` | `user@example.com` |
| `phone`, `*_phone` | `+1234567890` |
| `url`, `*_url`, `link` | `https://example.com` |
| `uuid`, `*_uuid` | `550e8400-e29b-41d4-a716-446655440000` |
| `password` | `SecurePass123!` |
| `token`, `*_token` | `eyJhbGciOiJIUzI1NiIs...` |
| `ip`, `*_ip`, `ip_address` | `192.168.1.1` |
| `date`, `*_date` | `2024-01-15` |
| `time`, `*_time` | `14:30:00` |
| `timestamp` | `2024-01-15T14:30:00Z` |

### Validation Rule Detection

Examples are generated based on Laravel validation rules:

```php
// Validation rules
'age' => 'required|integer|min:18|max:100'
// Generated: 25

'status' => 'required|in:active,inactive,pending'
// Generated: "active"

'tags' => 'array|min:1|max:5'
// Generated: ["tag1", "tag2"]

'price' => 'numeric|min:0.01|max:9999.99'
// Generated: 99.99
```

### Format-based Generation

OpenAPI formats are respected:

| Format | Example |
|--------|---------|
| `email` | `user@example.com` |
| `date` | `2024-01-15` |
| `date-time` | `2024-01-15T14:30:00Z` |
| `uuid` | `550e8400-e29b-41d4-a716-446655440000` |
| `uri` | `https://example.com/resource` |
| `hostname` | `api.example.com` |
| `ipv4` | `192.168.1.1` |
| `ipv6` | `2001:0db8:85a3:0000:0000:8a2e:0370:7334` |

## Advanced Usage

### Custom Example Providers

You can create custom example providers for specific fields:

```php
// app/Spectrum/ExampleProviders/UserExampleProvider.php
namespace App\Spectrum\ExampleProviders;

use LaravelSpectrum\Contracts\ExampleProvider;

class UserExampleProvider implements ExampleProvider
{
    public function generateExample(string $type, string $fieldName): mixed
    {
        return match($fieldName) {
            'company_name' => 'Acme Corporation',
            'department' => 'Engineering',
            'employee_id' => 'EMP-' . rand(1000, 9999),
            default => null
        };
    }
}
```

Register in a service provider:

```php
public function boot()
{
    $this->app->extend(RequestExampleFormatter::class, function ($formatter) {
        return $formatter->addProvider(new UserExampleProvider());
    });
}
```

### Export Hooks

Execute custom logic during export:

```php
// app/Spectrum/Hooks/PostmanExportHook.php
namespace App\Spectrum\Hooks;

class PostmanExportHook
{
    public function beforeExport(array &$collection): void
    {
        // Add custom variables
        $collection['variable'][] = [
            'key' => 'custom_header',
            'value' => 'X-Custom-Value',
            'type' => 'string',
        ];
    }

    public function afterExport(array &$collection): void
    {
        // Modify the exported collection
        $collection['info']['description'] .= "\n\nGenerated on: " . now();
    }
}
```

### Programmatic Export

Export collections programmatically:

```php
use LaravelSpectrum\Exporters\PostmanExporter;
use LaravelSpectrum\Generators\OpenApiGenerator;

// Generate OpenAPI documentation
$openapi = app(OpenApiGenerator::class)->generate($routes);

// Export to Postman
$exporter = app(PostmanExporter::class);
$collection = $exporter->export($openapi);
$environment = $exporter->exportEnvironment(
    $openapi['servers'] ?? [],
    $openapi['components']['securitySchemes'] ?? [],
    'production'
);

// Save files
Storage::put('postman/collection.json', json_encode($collection));
Storage::put('postman/environment.json', json_encode($environment));
```

## Troubleshooting

### Common Issues

#### 1. Empty Collection

**Problem**: Exported collection has no requests

**Solution**: Ensure routes are properly detected
```bash
# Check detected routes
php artisan spectrum:generate --dry-run

# Verify route patterns in config/spectrum.php
'route_patterns' => [
    'api/*',
    'v1/*',
    'v2/*',
],
```

#### 2. Missing Authentication

**Problem**: Authentication not included in export

**Solution**: Ensure security schemes are defined in OpenAPI
```php
// Check OpenAPI generation includes security
$openapi = app(OpenApiGenerator::class)->generate($routes);
dd($openapi['components']['securitySchemes']);
```

#### 3. Invalid Examples

**Problem**: Generated examples don't match your data format

**Solution**: Use custom example providers or explicit examples in validation
```php
'email' => 'required|email|example:admin@company.com'
```

#### 4. Large Export Files

**Problem**: Export files are too large

**Solution**: Use chunked export for large APIs
```php
// Split by tags
$tags = ['Users', 'Auth', 'Products'];
foreach ($tags as $tag) {
    Artisan::call('spectrum:export:postman', [
        '--output' => "exports/{$tag}",
        '--tag' => $tag,
    ]);
}
```

### Performance Tips

1. **Cache OpenAPI generation** before export:
   ```bash
   php artisan spectrum:cache
   php artisan spectrum:export:postman
   ```

2. **Export specific tags** only:
   ```bash
   php artisan spectrum:export:postman --tags=Users,Auth
   ```

3. **Disable features** you don't need:
   ```php
   'export' => [
       'postman' => [
           'include_tests' => false,
           'include_pre_request_scripts' => false,
       ],
   ],
   ```

## Best Practices

1. **Version Control**: Commit exported collections to track API changes
2. **CI/CD Integration**: Auto-export on deployment
3. **Environment Management**: Use separate environments for each deployment stage
4. **Team Workflows**: Share collections via Git for consistency
5. **Testing**: Use Newman in CI/CD to validate API changes

## Next Steps

- Learn about [Performance Optimization](./performance.md)
- Explore [Advanced Features](./advanced-features.md)
- Read the [Configuration Guide](./configuration.md)