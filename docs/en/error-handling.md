# Error Handling Guide

Laravel Spectrum automatically detects API error responses and includes them in the OpenAPI documentation.

## 🎯 Basic Error Responses

### Standard HTTP Errors

Laravel Spectrum automatically documents the following standard HTTP errors:

```php
// 400 Bad Request
return response()->json([
    'message' => 'The given data was invalid.',
    'errors' => [
        'email' => ['The email field is required.'],
        'password' => ['The password field is required.']
    ]
], 400);

// 401 Unauthorized
return response()->json([
    'message' => 'Unauthenticated.'
], 401);

// 403 Forbidden
return response()->json([
    'message' => 'This action is unauthorized.'
], 403);

// 404 Not Found
return response()->json([
    'message' => 'Resource not found.'
], 404);

// 422 Unprocessable Entity
return response()->json([
    'message' => 'The given data was invalid.',
    'errors' => $validator->errors()
], 422);

// 500 Internal Server Error
return response()->json([
    'message' => 'Server Error'
], 500);
```

## 🔍 Validation Errors

### FormRequest Validation Errors

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateUserRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
        ];
    }
    
    public function messages()
    {
        return [
            'name.required' => 'The name field is required.',
            'email.required' => 'The email field is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email address is already in use.',
            'password.required' => 'The password field is required.',
            'password.min' => 'The password must be at least 8 characters.',
            'password.confirmed' => 'The password confirmation does not match.',
        ];
    }
    
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validation Error',
            'errors' => $validator->errors(),
            'status' => 422
        ], 422));
    }
}
```

Generated OpenAPI Schema:

```json
{
  "422": {
    "description": "Validation Error",
    "content": {
      "application/json": {
        "schema": {
          "type": "object",
          "properties": {
            "message": {
              "type": "string",
              "example": "Validation Error"
            },
            "errors": {
              "type": "object",
              "properties": {
                "name": {
                  "type": "array",
                  "items": {
                    "type": "string"
                  }
                },
                "email": {
                  "type": "array",
                  "items": {
                    "type": "string"
                  }
                },
                "password": {
                  "type": "array",
                  "items": {
                    "type": "string"
                  }
                }
              }
            },
            "status": {
              "type": "integer",
              "example": 422
            }
          }
        }
      }
    }
  }
}
```

## 🎨 Custom Error Handling

### Customizing Exception Handler

```php
namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    public function render($request, Throwable $exception)
    {
        if ($request->expectsJson()) {
            // Model not found
            if ($exception instanceof ModelNotFoundException) {
                return response()->json([
                    'error' => [
                        'code' => 'RESOURCE_NOT_FOUND',
                        'message' => 'Resource not found.',
                        'resource' => class_basename($exception->getModel()),
                        'id' => $exception->getIds()
                    ]
                ], 404);
            }
            
            // Route not found
            if ($exception instanceof NotFoundHttpException) {
                return response()->json([
                    'error' => [
                        'code' => 'ENDPOINT_NOT_FOUND',
                        'message' => 'Endpoint not found.',
                        'path' => $request->path()
                    ]
                ], 404);
            }
            
            // Validation error
            if ($exception instanceof ValidationException) {
                return response()->json([
                    'error' => [
                        'code' => 'VALIDATION_FAILED',
                        'message' => 'Validation failed.',
                        'details' => $exception->errors()
                    ]
                ], 422);
            }
        }
        
        return parent::render($request, $exception);
    }
}
```

### Custom Exception Classes

```php
namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class BusinessLogicException extends Exception
{
    protected $errorCode;
    protected $statusCode;
    protected $details;
    
    public function __construct(
        string $message, 
        string $errorCode, 
        int $statusCode = 400, 
        array $details = []
    ) {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->statusCode = $statusCode;
        $this->details = $details;
    }
    
    public function render($request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'details' => $this->details,
                'timestamp' => now()->toISOString(),
                'path' => $request->path()
            ]
        ], $this->statusCode);
    }
}

// Usage example
throw new BusinessLogicException(
    'Insufficient stock',
    'INSUFFICIENT_STOCK',
    400,
    [
        'product_id' => $productId,
        'requested_quantity' => $quantity,
        'available_quantity' => $availableStock
    ]
);
```

## 🔄 Standardizing API Error Responses

### Unified Error Structure

```php
namespace App\Http\Responses;

class ApiErrorResponse
{
    public static function make(
        string $message,
        string $code = null,
        array $errors = [],
        int $status = 400
    ) {
        $response = [
            'success' => false,
            'message' => $message,
            'code' => $code ?? 'ERROR',
            'timestamp' => now()->toISOString(),
        ];
        
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        
        return response()->json($response, $status);
    }
    
    public static function validation(array $errors)
    {
        return self::make(
            'Validation error occurred',
            'VALIDATION_ERROR',
            $errors,
            422
        );
    }
    
    public static function unauthorized(string $message = null)
    {
        return self::make(
            $message ?? 'Authentication required',
            'UNAUTHORIZED',
            [],
            401
        );
    }
    
    public static function forbidden(string $message = null)
    {
        return self::make(
            $message ?? 'Access denied',
            'FORBIDDEN',
            [],
            403
        );
    }
    
    public static function notFound(string $resource = null)
    {
        return self::make(
            $resource ? "{$resource} not found" : 'Resource not found',
            'NOT_FOUND',
            [],
            404
        );
    }
    
    public static function serverError(string $message = null)
    {
        return self::make(
            $message ?? 'Server error occurred',
            'SERVER_ERROR',
            [],
            500
        );
    }
}

// Usage examples
return ApiErrorResponse::validation($validator->errors()->toArray());
return ApiErrorResponse::notFound('User');
return ApiErrorResponse::forbidden('You do not have permission to perform this action');
```

## 🚨 Managing Error Codes

### Error Code Constants

```php
namespace App\Constants;

class ErrorCodes
{
    // Authentication related
    const AUTH_FAILED = 'AUTH_FAILED';
    const TOKEN_EXPIRED = 'TOKEN_EXPIRED';
    const TOKEN_INVALID = 'TOKEN_INVALID';
    const ACCOUNT_LOCKED = 'ACCOUNT_LOCKED';
    
    // Validation related
    const VALIDATION_FAILED = 'VALIDATION_FAILED';
    const INVALID_INPUT = 'INVALID_INPUT';
    const MISSING_FIELD = 'MISSING_FIELD';
    
    // Resource related
    const RESOURCE_NOT_FOUND = 'RESOURCE_NOT_FOUND';
    const RESOURCE_ALREADY_EXISTS = 'RESOURCE_ALREADY_EXISTS';
    const RESOURCE_LOCKED = 'RESOURCE_LOCKED';
    
    // Business logic
    const INSUFFICIENT_BALANCE = 'INSUFFICIENT_BALANCE';
    const QUOTA_EXCEEDED = 'QUOTA_EXCEEDED';
    const OPERATION_NOT_ALLOWED = 'OPERATION_NOT_ALLOWED';
    
    // System related
    const SYSTEM_ERROR = 'SYSTEM_ERROR';
    const SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';
    const RATE_LIMIT_EXCEEDED = 'RATE_LIMIT_EXCEEDED';
}
```

### Multi-language Error Messages

```php
// resources/lang/en/errors.php
return [
    ErrorCodes::AUTH_FAILED => 'Authentication failed',
    ErrorCodes::TOKEN_EXPIRED => 'Token has expired',
    ErrorCodes::RESOURCE_NOT_FOUND => ':resource not found',
    ErrorCodes::INSUFFICIENT_BALANCE => 'Insufficient balance',
    ErrorCodes::QUOTA_EXCEEDED => 'Usage limit exceeded',
];

// Usage example
$message = trans('errors.' . ErrorCodes::RESOURCE_NOT_FOUND, ['resource' => 'User']);
```

## 📊 Error Response Type Definitions

### TypeScript Type Definition Generation

Laravel Spectrum automatically generates type definitions for error responses:

```typescript
// Generated type definitions
interface ValidationError {
  message: string;
  errors: {
    [field: string]: string[];
  };
}

interface ApiError {
  success: false;
  message: string;
  code: string;
  timestamp: string;
  errors?: any;
}

interface UnauthorizedError {
  message: string;
  code: 'UNAUTHORIZED';
}

interface NotFoundError {
  message: string;
  code: 'NOT_FOUND';
  resource?: string;
}
```

## 🔧 Error Handling in Middleware

```php
namespace App\Http\Middleware;

use Closure;
use App\Http\Responses\ApiErrorResponse;

class CheckApiVersion
{
    public function handle($request, Closure $next)
    {
        $version = $request->header('X-API-Version');
        
        if (!$version) {
            return ApiErrorResponse::make(
                'API version not specified',
                'MISSING_API_VERSION',
                ['header' => 'X-API-Version header is required'],
                400
            );
        }
        
        if (!in_array($version, config('api.supported_versions'))) {
            return ApiErrorResponse::make(
                'Unsupported API version',
                'UNSUPPORTED_API_VERSION',
                [
                    'provided' => $version,
                    'supported' => config('api.supported_versions')
                ],
                400
            );
        }
        
        return $next($request);
    }
}
```

## 💡 Best Practices

### 1. Consistent Error Structure
- Use the same error structure across all API endpoints
- Manage error codes as constants
- Provide detailed error information

### 2. Appropriate HTTP Status Codes
- 400: Client error (bad request)
- 401: Authentication error
- 403: Authorization error
- 404: Resource not found
- 422: Validation error
- 500: Server error

### 3. Error Logging
```php
\Log::error('API Error', [
    'code' => $errorCode,
    'message' => $message,
    'user_id' => auth()->id(),
    'request' => $request->all(),
    'trace' => $exception->getTraceAsString()
]);
```

## 📚 Related Documentation

- [Validation Detection](./validation-detection.md) - Validation rule detection
- [Response Analysis](./response-analysis.md) - Response structure analysis
- [Troubleshooting](./troubleshooting.md) - Solving common problems