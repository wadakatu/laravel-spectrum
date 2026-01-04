# Advanced Features Guide

This guide explains the advanced features and usage of Laravel Spectrum.

## 🎯 Conditional Validation

### HTTP Method-Based Conditions

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
    public function rules()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
        ];

        // For POST requests (creation)
        if ($this->isMethod('POST')) {
            $rules['password'] = 'required|string|min:8|confirmed';
            $rules['email'] .= '|unique:users,email';
            $rules['terms_accepted'] = 'required|accepted';
        }
        
        // For PUT/PATCH requests (update)
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['password'] = 'sometimes|nullable|string|min:8|confirmed';
            $rules['email'] .= '|unique:users,email,' . $this->route('user');
            $rules['current_password'] = 'required_with:password|current_password';
        }

        return $rules;
    }
}
```

Generated OpenAPI Schema:

```json
{
  "oneOf": [
    {
      "title": "Create User (POST)",
      "type": "object",
      "required": ["name", "email", "password", "password_confirmation", "terms_accepted"],
      "properties": {
        "name": { "type": "string", "maxLength": 255 },
        "email": { "type": "string", "format": "email" },
        "password": { "type": "string", "minLength": 8 },
        "password_confirmation": { "type": "string", "minLength": 8 },
        "terms_accepted": { "type": "boolean" }
      }
    },
    {
      "title": "Update User (PUT/PATCH)",
      "type": "object",
      "required": ["name", "email"],
      "properties": {
        "name": { "type": "string", "maxLength": 255 },
        "email": { "type": "string", "format": "email" },
        "password": { "type": "string", "minLength": 8, "nullable": true },
        "password_confirmation": { "type": "string", "minLength": 8 },
        "current_password": { "type": "string" }
      }
    }
  ]
}
```

### Dynamic Conditional Rules

```php
public function rules()
{
    return [
        'account_type' => 'required|in:personal,business,enterprise',
        
        // Required fields for personal accounts
        'first_name' => 'required_if:account_type,personal|string|max:100',
        'last_name' => 'required_if:account_type,personal|string|max:100',
        'date_of_birth' => 'required_if:account_type,personal|date|before:today',
        
        // Required fields for business accounts
        'company_name' => 'required_if:account_type,business,enterprise|string|max:255',
        'tax_id' => 'required_if:account_type,business,enterprise|string|regex:/^[A-Z0-9\-]+$/',
        'business_type' => 'required_if:account_type,business|in:llc,corporation,partnership',
        
        // Additional fields for enterprise accounts
        'contract_type' => 'required_if:account_type,enterprise|in:annual,multi-year',
        'sla_level' => 'required_if:account_type,enterprise|in:standard,premium,custom',
        
        // Conditional nested data
        'billing' => 'required_unless:account_type,personal|array',
        'billing.address' => 'required_unless:account_type,personal|string',
        'billing.city' => 'required_unless:account_type,personal|string',
        'billing.postal_code' => 'required_unless:account_type,personal|string',
        
        // Complex conditions
        'payment_method' => Rule::requiredIf(function () {
            return in_array($this->account_type, ['business', 'enterprise']) 
                   && $this->billing_cycle === 'monthly';
        }),
    ];
}
```

## 🔄 Dynamic Response Structures

### Permission-Based Responses

```php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at->toISOString(),
        ];

        // For own profile
        if ($request->user()?->id === $this->id) {
            $data = array_merge($data, [
                'phone' => $this->phone,
                'address' => $this->address,
                'preferences' => $this->preferences,
                'two_factor_enabled' => $this->two_factor_enabled,
            ]);
        }

        // For administrators
        if ($request->user()?->isAdmin()) {
            $data = array_merge($data, [
                'internal_notes' => $this->internal_notes,
                'account_status' => $this->account_status,
                'last_login_at' => $this->last_login_at?->toISOString(),
                'login_count' => $this->login_count,
                'ip_addresses' => $this->ip_addresses,
            ]);
        }

        // Conditional loading of relationships
        if ($this->relationLoaded('posts') && $request->user()->can('view-posts', $this)) {
            $data['posts'] = PostResource::collection($this->posts);
            $data['posts_count'] = $this->posts->count();
        }

        return $data;
    }

    /**
     * Additional metadata
     */
    public function with($request)
    {
        $with = [];

        if ($request->user()?->isAdmin()) {
            $with['meta'] = [
                'permissions' => $this->getAllPermissions()->pluck('name'),
                'roles' => $this->roles->pluck('name'),
            ];
        }

        return $with;
    }
}
```

## 🎨 Custom Validation Rules

### Complex Validation Rules

```php
namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;

class ValidBusinessHours implements Rule, DataAwareRule
{
    protected array $data = [];
    
    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }
    
    public function passes($attribute, $value)
    {
        if (!is_array($value)) {
            return false;
        }
        
        // Check business hours format
        foreach ($value as $day => $hours) {
            if (!in_array($day, ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])) {
                return false;
            }
            
            if (!isset($hours['open']) || !isset($hours['close'])) {
                continue; // Closed day
            }
            
            // Validate time format
            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $hours['open']) ||
                !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $hours['close'])) {
                return false;
            }
            
            // Check if opening time is before closing time
            if (strtotime($hours['open']) >= strtotime($hours['close'])) {
                return false;
            }
        }
        
        return true;
    }
    
    public function message()
    {
        return 'The :attribute must contain valid business hours.';
    }
}

// Usage example
public function rules()
{
    return [
        'business_hours' => ['required', new ValidBusinessHours],
        'timezone' => 'required|timezone',
    ];
}
```

### Database-Dependent Validation

```php
namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Models\Product;

class SufficientStock implements Rule
{
    protected $productId;
    protected $availableStock;
    
    public function __construct($productId)
    {
        $this->productId = $productId;
    }
    
    public function passes($attribute, $value)
    {
        $product = Product::find($this->productId);
        
        if (!$product) {
            return false;
        }
        
        $this->availableStock = $product->stock;
        
        return $value <= $this->availableStock;
    }
    
    public function message()
    {
        return "The requested quantity exceeds available stock. Only {$this->availableStock} items available.";
    }
}
```

## 🔍 Advanced Query Parameters

### Complex Filtering

```php
namespace App\Http\Controllers;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query();
        
        // Price range filter
        if ($request->has('price_min') || $request->has('price_max')) {
            $query->whereBetween('price', [
                $request->input('price_min', 0),
                $request->input('price_max', PHP_INT_MAX)
            ]);
        }
        
        // Multiple category filter
        if ($request->has('categories')) {
            $categories = is_array($request->categories) 
                ? $request->categories 
                : explode(',', $request->categories);
            
            $query->whereHas('categories', function ($q) use ($categories) {
                $q->whereIn('slug', $categories);
            });
        }
        
        // Attribute filter (dynamic)
        if ($request->has('attributes')) {
            foreach ($request->attributes as $key => $value) {
                $query->whereHas('attributes', function ($q) use ($key, $value) {
                    $q->where('key', $key)->where('value', $value);
                });
            }
        }
        
        // Sorting (multiple fields support)
        if ($request->has('sort')) {
            $sortFields = explode(',', $request->sort);
            foreach ($sortFields as $field) {
                $direction = 'asc';
                if (str_starts_with($field, '-')) {
                    $direction = 'desc';
                    $field = substr($field, 1);
                }
                
                if (in_array($field, ['name', 'price', 'created_at', 'popularity'])) {
                    $query->orderBy($field, $direction);
                }
            }
        }
        
        // Search (multiple fields)
        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%")
                  ->orWhereHas('tags', function ($q) use ($search) {
                      $q->where('name', 'LIKE', "%{$search}%");
                  });
            });
        }
        
        // Include (related data)
        if ($includes = $request->input('include')) {
            $allowedIncludes = ['category', 'brand', 'reviews', 'variants'];
            $includes = array_intersect(explode(',', $includes), $allowedIncludes);
            $query->with($includes);
        }
        
        return ProductResource::collection(
            $query->paginate($request->input('per_page', 20))
        );
    }
}
```

## 📦 Batch Processing and Bulk Operations

### Bulk Create/Update

```php
namespace App\Http\Controllers\Api;

use App\Http\Requests\BulkUserRequest;
use App\Jobs\ProcessBulkUsers;

class BulkUserController extends Controller
{
    public function store(BulkUserRequest $request)
    {
        $validated = $request->validated();
        
        // For asynchronous processing
        if ($request->input('async', false)) {
            $job = ProcessBulkUsers::dispatch($validated['users'])
                ->onQueue('bulk-operations');
                
            return response()->json([
                'message' => 'Bulk operation queued',
                'job_id' => $job->getJobId(),
                'status_url' => route('bulk.status', $job->getJobId()),
            ], 202);
        }
        
        // Synchronous processing
        $results = [];
        DB::beginTransaction();
        
        try {
            foreach ($validated['users'] as $index => $userData) {
                try {
                    $user = User::create($userData);
                    $results[] = [
                        'index' => $index,
                        'status' => 'success',
                        'data' => new UserResource($user),
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'index' => $index,
                        'status' => 'error',
                        'error' => $e->getMessage(),
                    ];
                }
            }
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
        
        return response()->json([
            'total' => count($validated['users']),
            'successful' => collect($results)->where('status', 'success')->count(),
            'failed' => collect($results)->where('status', 'error')->count(),
            'results' => $results,
        ]);
    }
}

// BulkUserRequest
public function rules()
{
    return [
        'users' => 'required|array|min:1|max:100',
        'users.*.name' => 'required|string|max:255',
        'users.*.email' => 'required|email|distinct|unique:users,email',
        'users.*.role' => 'required|in:admin,user,guest',
        'async' => 'boolean',
        'validate_only' => 'boolean',
    ];
}
```

## 🔐 Advanced Authentication Patterns

### Multi-Tenant Authentication

```php
namespace App\Http\Middleware;

use Closure;
use App\Models\Tenant;

class TenantAuthentication
{
    public function handle($request, Closure $next)
    {
        // Get tenant ID from header
        $tenantId = $request->header('X-Tenant-ID');
        
        if (!$tenantId) {
            // Try to get from subdomain
            $host = $request->getHost();
            $subdomain = explode('.', $host)[0];
            
            $tenant = Tenant::where('subdomain', $subdomain)->first();
        } else {
            $tenant = Tenant::find($tenantId);
        }
        
        if (!$tenant || !$tenant->is_active) {
            return response()->json([
                'error' => 'Invalid or inactive tenant'
            ], 401);
        }
        
        // Set tenant context
        app()->instance('tenant', $tenant);
        
        // Switch database connection
        config(['database.default' => 'tenant']);
        config(['database.connections.tenant.database' => $tenant->database]);
        
        // Set cache and session prefixes
        config(['cache.prefix' => $tenant->id]);
        config(['session.cookie' => 'session_' . $tenant->id]);
        
        return $next($request);
    }
}
```

## 🎯 Webhooks and Events

### Webhook Dispatch System

```php
namespace App\Services;

use App\Models\Webhook;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Http;

class WebhookService
{
    public function dispatch(string $event, array $payload)
    {
        $webhooks = Webhook::active()
            ->whereJsonContains('events', $event)
            ->get();
            
        foreach ($webhooks as $webhook) {
            $this->sendWebhook($webhook, $event, $payload);
        }
    }
    
    protected function sendWebhook(Webhook $webhook, string $event, array $payload)
    {
        $webhookEvent = WebhookEvent::create([
            'webhook_id' => $webhook->id,
            'event' => $event,
            'payload' => $payload,
            'status' => 'pending',
        ]);
        
        try {
            $signature = $this->generateSignature($webhook->secret, $payload);
            
            $response = Http::timeout(30)
                ->withHeaders([
                    'X-Webhook-Event' => $event,
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Timestamp' => now()->timestamp,
                ])
                ->retry(3, 1000)
                ->post($webhook->url, $payload);
                
            $webhookEvent->update([
                'status' => 'delivered',
                'response_code' => $response->status(),
                'response_body' => $response->body(),
                'delivered_at' => now(),
            ]);
            
        } catch (\Exception $e) {
            $webhookEvent->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'failed_at' => now(),
            ]);
            
            // Retry logic
            if ($webhookEvent->attempts < 5) {
                RetryWebhook::dispatch($webhookEvent)
                    ->delay(now()->addMinutes(pow(2, $webhookEvent->attempts)));
            }
        }
    }
    
    protected function generateSignature(string $secret, array $payload): string
    {
        return hash_hmac('sha256', json_encode($payload), $secret);
    }
}
```

## 🔄 Real-time API

### Server-Sent Events (SSE)

```php
namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\StreamedResponse;

class RealtimeController extends Controller
{
    public function stream(Request $request)
    {
        return new StreamedResponse(function () use ($request) {
            // Header settings
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no');
            
            $lastEventId = $request->header('Last-Event-ID', 0);
            
            while (true) {
                // Get new events
                $events = $this->getNewEvents($lastEventId);
                
                foreach ($events as $event) {
                    echo "id: {$event->id}\n";
                    echo "event: {$event->type}\n";
                    echo "data: " . json_encode($event->data) . "\n\n";
                    
                    $lastEventId = $event->id;
                }
                
                ob_flush();
                flush();
                
                // Check if connection was aborted
                if (connection_aborted()) {
                    break;
                }
                
                sleep(1);
            }
        });
    }
}
```

## 📚 Related Documentation

- [Conditional Validation](./conditional-validation.md) - Detailed validation patterns
- [API Reference](./api-reference.md) - Advanced API usage
- [Customization](./customization.md) - How to extend features