# エラーハンドリングガイド

Laravel Spectrumは、APIのエラーレスポンスを自動的に検出し、OpenAPIドキュメントに含めます。

## 🎯 基本的なエラーレスポンス

### 標準的なHTTPエラー

Laravel Spectrumは以下の標準的なHTTPエラーを自動的にドキュメント化します：

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

## 🔍 バリデーションエラー

### FormRequestのバリデーションエラー

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
            'name.required' => 'ユーザー名は必須です。',
            'email.required' => 'メールアドレスは必須です。',
            'email.email' => '有効なメールアドレスを入力してください。',
            'email.unique' => 'このメールアドレスは既に使用されています。',
            'password.required' => 'パスワードは必須です。',
            'password.min' => 'パスワードは8文字以上である必要があります。',
            'password.confirmed' => 'パスワードが一致しません。',
        ];
    }
    
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'バリデーションエラー',
            'errors' => $validator->errors(),
            'status' => 422
        ], 422));
    }
}
```

生成されるOpenAPIスキーマ：

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
              "example": "バリデーションエラー"
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

## 🎨 カスタムエラーハンドリング

### Exception Handlerのカスタマイズ

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
            // モデルが見つからない場合
            if ($exception instanceof ModelNotFoundException) {
                return response()->json([
                    'error' => [
                        'code' => 'RESOURCE_NOT_FOUND',
                        'message' => 'リソースが見つかりません。',
                        'resource' => class_basename($exception->getModel()),
                        'id' => $exception->getIds()
                    ]
                ], 404);
            }
            
            // ルートが見つからない場合
            if ($exception instanceof NotFoundHttpException) {
                return response()->json([
                    'error' => [
                        'code' => 'ENDPOINT_NOT_FOUND',
                        'message' => 'エンドポイントが見つかりません。',
                        'path' => $request->path()
                    ]
                ], 404);
            }
            
            // バリデーションエラー
            if ($exception instanceof ValidationException) {
                return response()->json([
                    'error' => [
                        'code' => 'VALIDATION_FAILED',
                        'message' => 'バリデーションに失敗しました。',
                        'details' => $exception->errors()
                    ]
                ], 422);
            }
        }
        
        return parent::render($request, $exception);
    }
}
```

### カスタム例外クラス

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

// 使用例
throw new BusinessLogicException(
    '在庫が不足しています',
    'INSUFFICIENT_STOCK',
    400,
    [
        'product_id' => $productId,
        'requested_quantity' => $quantity,
        'available_quantity' => $availableStock
    ]
);
```

## 🔄 APIエラーレスポンスの標準化

### 統一されたエラー構造

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
            'バリデーションエラーが発生しました',
            'VALIDATION_ERROR',
            $errors,
            422
        );
    }
    
    public static function unauthorized(string $message = null)
    {
        return self::make(
            $message ?? '認証が必要です',
            'UNAUTHORIZED',
            [],
            401
        );
    }
    
    public static function forbidden(string $message = null)
    {
        return self::make(
            $message ?? 'アクセスが拒否されました',
            'FORBIDDEN',
            [],
            403
        );
    }
    
    public static function notFound(string $resource = null)
    {
        return self::make(
            $resource ? "{$resource}が見つかりません" : 'リソースが見つかりません',
            'NOT_FOUND',
            [],
            404
        );
    }
    
    public static function serverError(string $message = null)
    {
        return self::make(
            $message ?? 'サーバーエラーが発生しました',
            'SERVER_ERROR',
            [],
            500
        );
    }
}

// 使用例
return ApiErrorResponse::validation($validator->errors()->toArray());
return ApiErrorResponse::notFound('ユーザー');
return ApiErrorResponse::forbidden('この操作を実行する権限がありません');
```

## 🚨 エラーコードの管理

### エラーコード定数

```php
namespace App\Constants;

class ErrorCodes
{
    // 認証関連
    const AUTH_FAILED = 'AUTH_FAILED';
    const TOKEN_EXPIRED = 'TOKEN_EXPIRED';
    const TOKEN_INVALID = 'TOKEN_INVALID';
    const ACCOUNT_LOCKED = 'ACCOUNT_LOCKED';
    
    // バリデーション関連
    const VALIDATION_FAILED = 'VALIDATION_FAILED';
    const INVALID_INPUT = 'INVALID_INPUT';
    const MISSING_FIELD = 'MISSING_FIELD';
    
    // リソース関連
    const RESOURCE_NOT_FOUND = 'RESOURCE_NOT_FOUND';
    const RESOURCE_ALREADY_EXISTS = 'RESOURCE_ALREADY_EXISTS';
    const RESOURCE_LOCKED = 'RESOURCE_LOCKED';
    
    // ビジネスロジック
    const INSUFFICIENT_BALANCE = 'INSUFFICIENT_BALANCE';
    const QUOTA_EXCEEDED = 'QUOTA_EXCEEDED';
    const OPERATION_NOT_ALLOWED = 'OPERATION_NOT_ALLOWED';
    
    // システム関連
    const SYSTEM_ERROR = 'SYSTEM_ERROR';
    const SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';
    const RATE_LIMIT_EXCEEDED = 'RATE_LIMIT_EXCEEDED';
}
```

### エラーメッセージの多言語対応

```php
// resources/lang/ja/errors.php
return [
    ErrorCodes::AUTH_FAILED => '認証に失敗しました',
    ErrorCodes::TOKEN_EXPIRED => 'トークンの有効期限が切れています',
    ErrorCodes::RESOURCE_NOT_FOUND => ':resourceが見つかりません',
    ErrorCodes::INSUFFICIENT_BALANCE => '残高が不足しています',
    ErrorCodes::QUOTA_EXCEEDED => '利用上限を超えました',
];

// 使用例
$message = trans('errors.' . ErrorCodes::RESOURCE_NOT_FOUND, ['resource' => 'ユーザー']);
```

## 📊 エラーレスポンスの型定義

### TypeScriptの型定義生成

Laravel Spectrumは自動的にエラーレスポンスの型定義を生成します：

```typescript
// 生成される型定義
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

## 🔧 ミドルウェアでのエラーハンドリング

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
                'APIバージョンが指定されていません',
                'MISSING_API_VERSION',
                ['header' => 'X-API-Version header is required'],
                400
            );
        }
        
        if (!in_array($version, config('api.supported_versions'))) {
            return ApiErrorResponse::make(
                'サポートされていないAPIバージョンです',
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

## 💡 ベストプラクティス

### 1. 一貫性のあるエラー構造
- すべてのAPIエンドポイントで同じエラー構造を使用
- エラーコードを定数として管理
- 詳細なエラー情報を提供

### 2. 適切なHTTPステータスコード
- 400: クライアントエラー（不正なリクエスト）
- 401: 認証エラー
- 403: 認可エラー
- 404: リソースが見つからない
- 422: バリデーションエラー
- 500: サーバーエラー

### 3. エラーログの記録
```php
\Log::error('API Error', [
    'code' => $errorCode,
    'message' => $message,
    'user_id' => auth()->id(),
    'request' => $request->all(),
    'trace' => $exception->getTraceAsString()
]);
```

## 📚 関連ドキュメント

- [バリデーション検出](./validation-detection.md) - バリデーションルールの検出
- [レスポンス解析](./response-analysis.md) - レスポンス構造の解析
- [トラブルシューティング](./troubleshooting.md) - 一般的な問題の解決