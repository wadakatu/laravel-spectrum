# Mock Server Feature Guide

Laravel Spectrum's mock server feature allows you to automatically start a mock API server from generated OpenAPI documentation. This enables frontend development and API integration testing without an actual backend.

## 🎭 Overview

The mock server provides the following features:

- **Automatic Response Generation** - Generates realistic responses based on OpenAPI schema
- **Authentication Simulation** - Simulates Bearer Token, API Key, and Basic authentication
- **Validation** - Validates request parameters
- **Scenario-based Responses** - Supports multiple scenarios like success/error
- **Response Delay** - Simulates network latency

## 🚀 Basic Usage

### Starting the Mock Server

```bash
# Start with default settings
php artisan spectrum:mock

# Start with custom port
php artisan spectrum:mock --port=3000

# Custom host and port
php artisan spectrum:mock --host=0.0.0.0 --port=8080
```

### Startup Output Example

```
🚀 Starting Laravel Spectrum Mock Server...
📄 Loading spec from: storage/app/spectrum/openapi.json

🎭 Mock Server Configuration:
+------------------+-------------------------+
| Setting          | Value                   |
+------------------+-------------------------+
| API Title        | Laravel API             |
| API Version      | 1.0.0                   |
| Server URL       | http://127.0.0.1:8081   |
| Total Endpoints  | 24                      |
| Default Scenario | success                 |
+------------------+-------------------------+

📋 Available Endpoints:
+--------+------------------------+--------------------------------+
| Method | Path                   | Description                    |
+--------+------------------------+--------------------------------+
| GET    | /api/users             | List all users                 |
| POST   | /api/users             | Create a new user              |
| GET    | /api/users/{id}        | Get user by ID                 |
| PUT    | /api/users/{id}        | Update user                    |
| DELETE | /api/users/{id}        | Delete user                    |
+--------+------------------------+--------------------------------+

🎯 Mock server running at http://127.0.0.1:8081
Press Ctrl+C to stop
```

## 🔧 Command Options

### Available Options

```bash
php artisan spectrum:mock [options]
```

| Option | Default | Description |
|--------|---------|-------------|
| `--host` | 127.0.0.1 | Host address to bind |
| `--port` | 8081 | Port number to listen on |
| `--spec` | storage/app/spectrum/openapi.json | Path to OpenAPI specification file |
| `--delay` | None | Response delay (milliseconds) |
| `--scenario` | success | Default response scenario |

### Usage Examples

```bash
# Use custom OpenAPI file
php artisan spectrum:mock --spec=docs/api-spec.json

# Add 500ms delay
php artisan spectrum:mock --delay=500

# Set error scenario as default
php artisan spectrum:mock --scenario=error
```

## 🎯 Response Scenarios

### Specifying Scenarios

You can dynamically switch response scenarios using the `_scenario` query parameter:

```bash
# Success response
curl http://localhost:8081/api/users?_scenario=success

# Error response
curl http://localhost:8081/api/users?_scenario=error

# Empty response
curl http://localhost:8081/api/users?_scenario=empty
```

### Available Scenarios

- **success** - Normal response (default)
- **error** - Error response (usually 500 error)
- **empty** - Empty data response
- **unauthorized** - Authentication error (401)
- **forbidden** - Permission error (403)
- **not_found** - Resource not found (404)
- **validation_error** - Validation error (422)

## 🔐 Authentication Simulation

### Bearer Token Authentication

```bash
# Request with valid token
curl -H "Authorization: Bearer mock-token-123" \
     http://localhost:8081/api/protected/resource

# Request with invalid token (401 error)
curl -H "Authorization: Bearer invalid-token" \
     http://localhost:8081/api/protected/resource
```

### API Key Authentication

```bash
# Send API Key in header
curl -H "X-API-Key: mock-api-key-123" \
     http://localhost:8081/api/protected/resource

# Send API Key in query parameter
curl http://localhost:8081/api/protected/resource?api_key=mock-api-key-123
```

### Basic Authentication

```bash
# Request with Basic authentication
curl -u username:password \
     http://localhost:8081/api/protected/resource
```

### Mock Authentication Tokens

The mock server recognizes the following tokens as valid:
- Bearer: Pattern `mock-token-*`
- API Key: Pattern `mock-api-key-*`
- Basic: Any username/password

## 📝 Validation Simulation

The mock server validates requests based on the OpenAPI schema:

### Required Field Validation

```bash
# When required fields are missing (422 error)
curl -X POST http://localhost:8081/api/users \
     -H "Content-Type: application/json" \
     -d '{"email": "test@example.com"}'

# Response
{
  "message": "The given data was invalid.",
  "errors": {
    "name": ["The name field is required."]
  }
}
```

### Type Validation

```bash
# When type is incorrect
curl -X POST http://localhost:8081/api/users \
     -H "Content-Type: application/json" \
     -d '{"name": "John", "age": "twenty"}'

# Response
{
  "message": "The given data was invalid.",
  "errors": {
    "age": ["The age must be an integer."]
  }
}
```

## 🎨 Response Customization

### Dynamic Response Generation

The mock server dynamically generates responses from the OpenAPI schema:

```yaml
# OpenAPI schema example
responses:
  200:
    content:
      application/json:
        schema:
          type: object
          properties:
            id:
              type: integer
              example: 123
            name:
              type: string
              example: "John Doe"
            email:
              type: string
              format: email
            created_at:
              type: string
              format: date-time
```

Generated response:

```json
{
  "id": 123,
  "name": "John Doe",
  "email": "john.doe@example.com",
  "created_at": "2024-01-15T10:30:00Z"
}
```

### Pagination Support

```bash
# Pagination parameters
curl "http://localhost:8081/api/users?page=2&per_page=10"

# Response
{
  "data": [...],
  "links": {
    "first": "http://localhost:8081/api/users?page=1",
    "last": "http://localhost:8081/api/users?page=5",
    "prev": "http://localhost:8081/api/users?page=1",
    "next": "http://localhost:8081/api/users?page=3"
  },
  "meta": {
    "current_page": 2,
    "from": 11,
    "to": 20,
    "total": 50,
    "per_page": 10,
    "last_page": 5
  }
}
```

## 🛠️ Advanced Usage

### CI/CD Usage

```yaml
# GitHub Actions example
- name: Start Mock Server
  run: |
    php artisan spectrum:generate
    php artisan spectrum:mock --host=0.0.0.0 --port=8080 &
    sleep 5

- name: Run Frontend Tests
  run: |
    npm test
  env:
    API_URL: http://localhost:8080
```

### Docker Usage

```dockerfile
# Dockerfile
FROM php:8.2-cli

# ... other configurations ...

EXPOSE 8081

CMD ["php", "artisan", "spectrum:mock", "--host=0.0.0.0"]
```

```yaml
# docker-compose.yml
services:
  mock-api:
    build: .
    ports:
      - "8081:8081"
    volumes:
      - ./storage/app/spectrum:/app/storage/app/spectrum
    command: php artisan spectrum:mock --host=0.0.0.0
```

### Multiple Version Mocks

```bash
# v1 API
php artisan spectrum:mock --spec=docs/v1/openapi.json --port=8081

# v2 API
php artisan spectrum:mock --spec=docs/v2/openapi.json --port=8082
```

## 💡 Best Practices

### 1. Development Environment Usage

```bash
# Add to package.json
{
  "scripts": {
    "mock-api": "php artisan spectrum:mock",
    "dev": "concurrently \"npm run mock-api\" \"npm run serve\""
  }
}
```

### 2. Test Environment Configuration

```javascript
// jest.config.js
module.exports = {
  setupFilesAfterEnv: ['./tests/setup.js'],
  testEnvironment: 'node',
  globals: {
    API_URL: 'http://localhost:8081'
  }
};
```

### 3. Using Environment Variables

```bash
# .env.testing
API_MOCK_HOST=0.0.0.0
API_MOCK_PORT=8081
API_MOCK_DELAY=100
```

```bash
# Start using environment variables
php artisan spectrum:mock \
  --host=$API_MOCK_HOST \
  --port=$API_MOCK_PORT \
  --delay=$API_MOCK_DELAY
```

## 🔍 Troubleshooting

### Port Already in Use

```bash
# Error: Address already in use
# Solution: Use a different port
php artisan spectrum:mock --port=8082
```

### OpenAPI File Not Found

```bash
# Error: OpenAPI specification file not found
# Solution: Generate documentation
php artisan spectrum:generate
php artisan spectrum:mock
```

### CORS Errors

The mock server allows CORS by default. If you encounter issues, check the network tab in your browser's developer tools.

## 📚 Related Documentation

- [Basic Usage](./basic-usage.md) - Documentation generation basics
- [Export Features](./export.md) - Export to Postman/Insomnia
- [CI/CD Integration](./ci-cd-integration.md) - Continuous integration