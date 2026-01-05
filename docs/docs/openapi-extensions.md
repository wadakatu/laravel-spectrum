# OpenAPI Extensions

Laravel Spectrum uses several OpenAPI extensions to provide enhanced functionality. This document describes each extension, its origin, purpose, and compatibility with different tools.

## Overview

OpenAPI allows vendor-specific extensions using the `x-` prefix. Laravel Spectrum uses these extensions to:

- Enhance documentation viewer experience
- Store Laravel-specific metadata
- Support file upload validation details

| Extension | Origin | Purpose | Compatibility |
|-----------|--------|---------|---------------|
| `x-tagGroups` | Redoc/Stoplight | Group tags in documentation | Redoc, Stoplight |
| `x-middleware` | Spectrum Internal | Store middleware metadata | Mock Server only |
| `x-rate-limit` | Spectrum Internal | Rate limiting information | Mock Server only |
| `maxSize` | Spectrum Custom | File size limit | Spectrum only |
| `contentMediaType` | JSON Schema (non-standard placement) | File MIME types | Partial |

### Origin Categories

- **Redoc/Stoplight**: Industry-standard extension defined by documentation tools
- **Spectrum Internal**: Metadata added by Laravel Spectrum for internal use (mock server)
- **Spectrum Custom**: Custom property added by Laravel Spectrum (not a standard extension)
- **JSON Schema**: Standard concept used in a non-standard location within OpenAPI

## x-tagGroups

Groups related tags together in documentation viewers that support this extension.

:::info Origin
**Redoc/Stoplight** - This is an industry-standard extension originally defined by Redoc and also supported by Stoplight. Laravel Spectrum does not define this extension; it simply uses it.
:::

### Location

Root level of OpenAPI document.

### Schema

```yaml
x-tagGroups:
  - name: string      # Group display name
    tags: string[]    # Array of tag names
```

### Example

```yaml
x-tagGroups:
  - name: User Management
    tags:
      - User
      - Profile
      - Authentication
  - name: Content
    tags:
      - Post
      - Comment
```

### Configuration

Enable tag groups in `config/spectrum.php`:

```php
'tag_groups' => [
    'enabled' => true,
    'groups' => [
        'User Management' => ['User', 'Profile'],
        'Content' => ['Post', 'Comment'],
    ],
    'uncategorized_group_name' => 'Other',
],
```

### Compatibility

| Tool | Support |
|------|---------|
| Redoc | Full support |
| Swagger UI | Ignored (no effect) |
| Stoplight | Full support |
| Postman | Ignored |

### Standard Alternative

There is no standard OpenAPI equivalent for tag grouping. Tags themselves are standard, but grouping them is a vendor extension.

---

## x-middleware

Stores Laravel middleware information for each operation. Used internally by the mock server for authentication simulation.

:::info Origin
**Spectrum Internal** - This extension is defined and used by Laravel Spectrum for internal purposes. It stores Laravel route middleware metadata and is primarily consumed by the mock server. External tools will ignore this extension.
:::

### Location

Operation level (inside path item methods).

### Schema

```yaml
x-middleware:
  - string  # Middleware name or class
```

### Example

```yaml
paths:
  /api/users:
    get:
      x-middleware:
        - auth:sanctum
        - throttle:60,1
        - verified
```

### Usage

This extension is automatically populated from route middleware and is primarily used by:

- **Mock Server**: Simulates authentication requirements
- **Documentation**: Shows which endpoints require authentication

### Compatibility

| Tool | Support |
|------|---------|
| Redoc | Ignored |
| Swagger UI | Ignored |
| Mock Server | Full support |

### Standard Alternative

Use the `security` field for authentication requirements:

```yaml
paths:
  /api/users:
    get:
      security:
        - bearerAuth: []
```

Laravel Spectrum generates both the standard `security` field and `x-middleware` for maximum compatibility.

---

## x-rate-limit

Stores rate limiting configuration for operations. Used by the mock server to simulate throttling.

:::info Origin
**Spectrum Internal** - This extension is defined and used by Laravel Spectrum for internal purposes. It extracts rate limiting information from Laravel's `throttle` middleware and is consumed by the mock server. External tools will ignore this extension.
:::

### Location

Operation level (inside path item methods).

### Schema

```yaml
x-rate-limit:
  limit: integer    # Maximum requests
  period: string    # Time period (e.g., "1 minute")
```

### Example

```yaml
paths:
  /api/users:
    get:
      x-rate-limit:
        limit: 60
        period: 1 minute
```

### Compatibility

| Tool | Support |
|------|---------|
| Redoc | Ignored |
| Swagger UI | Ignored |
| Mock Server | Full support |

### Standard Alternative

There is no standard OpenAPI field for rate limiting. Some APIs document this in the `description` field or response headers.

---

## maxSize (File Upload)

Custom property indicating maximum file size for file upload fields.

:::info Origin
**Spectrum Custom** - This is a custom property added by Laravel Spectrum. It is not a standard OpenAPI or JSON Schema property. The value is also included in the `description` field for compatibility with tools that don't recognize this property.
:::

### Location

Schema property level (inside `properties` for file fields).

### Schema

```yaml
maxSize: integer  # Maximum size in bytes
```

### Example

```yaml
components:
  schemas:
    FileUploadRequest:
      type: object
      properties:
        document:
          type: string
          format: binary
          maxSize: 5242880  # 5MB in bytes
          description: "PDF document (max 5MB)"
```

### Source

This value is extracted from Laravel validation rules:

```php
$request->validate([
    'document' => 'required|file|max:5120',  // 5MB (5120 KB)
]);
```

### Compatibility

| Tool | Support |
|------|---------|
| Redoc | Ignored (shown in description) |
| Swagger UI | Ignored (shown in description) |
| Postman | Ignored |

### Standard Alternative

OpenAPI 3.1 supports `maxLength` for strings, but this doesn't apply to binary content. The recommended approach is to document size limits in the `description` field, which Laravel Spectrum does automatically:

```yaml
description: "PDF document (max 5MB)"
```

---

## contentMediaType (File Upload)

Indicates allowed MIME types for file upload fields. While `contentMediaType` is part of JSON Schema, Laravel Spectrum uses it in a non-standard location within OpenAPI schemas.

:::info Origin
**JSON Schema (non-standard placement)** - `contentMediaType` is a standard JSON Schema keyword, but in JSON Schema it's typically used differently. Laravel Spectrum places it directly on file upload properties for convenience. The standard OpenAPI approach uses the `encoding` object, which Laravel Spectrum also generates for compatibility.
:::

### Location

Schema property level (inside `properties` for file fields).

### Schema

```yaml
contentMediaType: string  # Comma-separated MIME types
```

### Example

```yaml
components:
  schemas:
    ImageUploadRequest:
      type: object
      properties:
        avatar:
          type: string
          format: binary
          contentMediaType: "image/jpeg, image/png, image/gif"
          description: "Profile image (JPEG, PNG, or GIF)"
```

### Source

This value is extracted from Laravel validation rules:

```php
$request->validate([
    'avatar' => 'required|image|mimes:jpeg,png,gif',
]);
```

### Compatibility

| Tool | Support |
|------|---------|
| Redoc | Partial (may display) |
| Swagger UI | Ignored |
| Postman | Ignored |

### Standard Alternative

In OpenAPI 3.0+, the standard approach is to use the `content` key with specific media types:

```yaml
requestBody:
  content:
    multipart/form-data:
      schema:
        type: object
        properties:
          avatar:
            type: string
            format: binary
      encoding:
        avatar:
          contentType: image/jpeg, image/png, image/gif
```

Laravel Spectrum includes both approaches for maximum compatibility.

---

## Disabling Extensions

If you need OpenAPI output without custom extensions, you can post-process the generated document:

```php
use LaravelSpectrum\Facades\Spectrum;

$openapi = Spectrum::generate();

// Remove custom extensions
unset($openapi['x-tagGroups']);

foreach ($openapi['paths'] as $path => $methods) {
    foreach ($methods as $method => $operation) {
        if (is_array($operation)) {
            unset($openapi['paths'][$path][$method]['x-middleware']);
            unset($openapi['paths'][$path][$method]['x-rate-limit']);
        }
    }
}

// Remove from schemas
if (isset($openapi['components']['schemas'])) {
    array_walk_recursive($openapi['components']['schemas'], function (&$value, $key) {
        if ($key === 'maxSize' || $key === 'contentMediaType') {
            $value = null;
        }
    });
}
```

## See Also

- [Configuration Reference](./config-reference.md) - Configure tag groups and other options
- [Mock Server](./mock-server.md) - How extensions are used by the mock server
- [Export Formats](./export.md) - Export to different formats
