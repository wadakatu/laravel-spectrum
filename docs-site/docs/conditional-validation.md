# Conditional Validation Detailed Guide

Laravel Spectrum automatically detects complex conditional validation rules and generates appropriate OpenAPI 3.0 schemas. This guide explains in detail the supported patterns and generated documentation.

## 🎯 Overview

Conditional validation refers to applying different validation rules based on specific conditions. Laravel Spectrum supports:

- HTTP method-based conditions
- Field value-based conditions
- Authentication state-based conditions
- Custom logic-based conditions

## 📋 Basic Patterns

### HTTP Method Based Conditions

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
    public function rules()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ];

        switch ($this->method()) {
            case 'POST':
                // All fields required on creation
                $rules['sku'] = 'required|string|unique:products,sku';
                $rules['price'] = 'required|numeric|min:0';
                $rules['stock'] = 'required|integer|min:0';
                $rules['category_id'] = 'required|exists:categories,id';
                break;
                
            case 'PUT':
                // Full update
                $rules['sku'] = 'required|string|unique:products,sku,' . $this->route('product');
                $rules['price'] = 'required|numeric|min:0';
                $rules['stock'] = 'required|integer|min:0';
                $rules['category_id'] = 'required|exists:categories,id';
                break;
                
            case 'PATCH':
                // Partial update - all optional
                $rules['sku'] = 'sometimes|string|unique:products,sku,' . $this->route('product');
                $rules['price'] = 'sometimes|numeric|min:0';
                $rules['stock'] = 'sometimes|integer|min:0';
                $rules['category_id'] = 'sometimes|exists:categories,id';
                break;
        }

        return $rules;
    }
}
```

Generated OpenAPI Schema:

```yaml
requestBody:
  content:
    application/json:
      schema:
        oneOf:
          - title: "Create Product (POST)"
            type: object
            required: ["name", "sku", "price", "stock", "category_id"]
            properties:
              name:
                type: string
                maxLength: 255
              description:
                type: string
                maxLength: 1000
                nullable: true
              sku:
                type: string
              price:
                type: number
                minimum: 0
              stock:
                type: integer
                minimum: 0
              category_id:
                type: integer
                
          - title: "Update Product (PUT)"
            type: object
            required: ["name", "sku", "price", "stock", "category_id"]
            properties:
              # Same structure
              
          - title: "Partial Update (PATCH)"
            type: object
            required: ["name"]
            properties:
              # All fields optional (except name)
```

### Field Value Based Conditions

```php
public function rules()
{
    return [
        'user_type' => 'required|in:individual,company,government',
        
        // For individual users
        'first_name' => 'required_if:user_type,individual|string|max:100',
        'last_name' => 'required_if:user_type,individual|string|max:100',
        'date_of_birth' => 'required_if:user_type,individual|date|before:-18 years',
        'ssn' => 'required_if:user_type,individual|regex:/^\d{3}-\d{2}-\d{4}$/',
        
        // For companies
        'company_name' => 'required_if:user_type,company,government|string|max:255',
        'registration_number' => 'required_if:user_type,company|string',
        'tax_id' => 'required_if:user_type,company|string',
        'incorporation_date' => 'required_if:user_type,company|date|before:today',
        
        // For government agencies
        'agency_name' => 'required_if:user_type,government|string',
        'department' => 'required_if:user_type,government|string',
        'government_id' => 'required_if:user_type,government|string',
        
        // Common optional fields
        'phone' => 'nullable|string|regex:/^\+?[1-9]\d{1,14}$/',
        'website' => 'nullable|url',
        'address' => 'required|array',
        'address.street' => 'required|string',
        'address.city' => 'required|string',
        'address.postal_code' => 'required|string',
        'address.country' => 'required|string|size:2',
    ];
}
```

## 🔄 Complex Conditional Patterns

### Using Rule::when

```php
use Illuminate\Validation\Rule;

public function rules()
{
    return [
        'subscription_type' => 'required|in:free,basic,premium,enterprise',
        
        'payment_method' => Rule::when(
            $this->subscription_type !== 'free',
            ['required', 'in:credit_card,paypal,bank_transfer,invoice'],
            'nullable'
        ),
        
        'billing_cycle' => Rule::when(
            in_array($this->subscription_type, ['basic', 'premium']),
            ['required', 'in:monthly,yearly'],
            Rule::when(
                $this->subscription_type === 'enterprise',
                ['required', 'in:monthly,quarterly,yearly,custom'],
                'nullable'
            )
        ),
        
        'invoice_details' => Rule::when(
            $this->payment_method === 'invoice' && $this->subscription_type === 'enterprise',
            ['required', 'array'],
            'nullable'
        ),
        
        'invoice_details.company_name' => Rule::when(
            $this->payment_method === 'invoice',
            'required|string',
            'nullable'
        ),
        
        'invoice_details.tax_id' => Rule::when(
            $this->payment_method === 'invoice',
            'required|string',
            'nullable'
        ),
    ];
}
```

### Multiple Condition Combinations

```php
public function rules()
{
    $rules = [
        'event_type' => 'required|in:online,offline,hybrid',
        'start_date' => 'required|date|after:today',
        'end_date' => 'required|date|after:start_date',
        'max_attendees' => 'required|integer|min:1',
    ];

    // For offline or hybrid events
    if (in_array($this->event_type, ['offline', 'hybrid'])) {
        $rules['venue'] = 'required|array';
        $rules['venue.name'] = 'required|string';
        $rules['venue.address'] = 'required|string';
        $rules['venue.capacity'] = 'required|integer|gte:max_attendees';
        $rules['venue.facilities'] = 'array';
        $rules['venue.facilities.*'] = 'in:parking,wifi,catering,av_equipment';
        
        // COVID-19 measures (conditional)
        if ($this->requires_safety_measures) {
            $rules['safety_measures'] = 'required|array';
            $rules['safety_measures.mask_required'] = 'required|boolean';
            $rules['safety_measures.vaccination_required'] = 'required|boolean';
            $rules['safety_measures.capacity_limit'] = 'required|integer|lte:venue.capacity';
        }
    }

    // For online or hybrid events
    if (in_array($this->event_type, ['online', 'hybrid'])) {
        $rules['streaming'] = 'required|array';
        $rules['streaming.platform'] = 'required|in:zoom,teams,youtube,custom';
        $rules['streaming.url'] = 'required_if:streaming.platform,custom|url';
        $rules['streaming.password'] = 'nullable|string';
        $rules['streaming.recording_available'] = 'boolean';
        
        // Platform-specific settings
        if ($this->input('streaming.platform') === 'zoom') {
            $rules['streaming.meeting_id'] = 'required|string';
            $rules['streaming.passcode'] = 'nullable|string|size:6';
        }
    }

    return $rules;
}
```

## 🎨 Authentication-Based Conditions

```php
public function rules()
{
    $user = $this->user();
    $rules = [
        'title' => 'required|string|max:255',
        'content' => 'required|string',
        'status' => 'required|in:draft,pending,published',
    ];

    // Additional fields for administrators
    if ($user && $user->hasRole('admin')) {
        $rules['featured'] = 'boolean';
        $rules['priority'] = 'integer|between:1,10';
        $rules['published_at'] = 'nullable|date';
        $rules['author_id'] = 'nullable|exists:users,id';
        $rules['internal_notes'] = 'nullable|string';
    }

    // For editors
    if ($user && $user->hasRole('editor')) {
        $rules['tags'] = 'array|max:10';
        $rules['tags.*'] = 'string|exists:tags,name';
        $rules['category_id'] = 'required|exists:categories,id';
    }

    // Restrictions for regular users
    if ($user && $user->hasRole('user')) {
        $rules['status'] = 'required|in:draft,pending'; // Cannot select published
        $rules['visibility'] = 'required|in:private,friends'; // Cannot select public
    }

    return $rules;
}
```

## 🔧 Dynamic Validation

### Database State Based Conditions

```php
public function rules()
{
    $product = Product::find($this->route('product'));
    
    $rules = [
        'quantity' => [
            'required',
            'integer',
            'min:1',
            // Less than or equal to stock
            'max:' . ($product ? $product->stock : 0),
        ],
    ];

    // For pre-order products
    if ($product && $product->is_preorder) {
        $rules['delivery_date'] = 'required|date|after:' . $product->release_date;
        $rules['deposit_amount'] = 'required|numeric|min:' . ($product->price * 0.2);
    }

    // For limited products
    if ($product && $product->is_limited) {
        $existingOrders = $this->user()
            ->orders()
            ->whereHas('items', function ($q) use ($product) {
                $q->where('product_id', $product->id);
            })
            ->sum('quantity');
            
        $rules['quantity'] .= '|max:' . ($product->limit_per_customer - $existingOrders);
    }

    return $rules;
}
```

### External API or Service Based Conditions

```php
public function rules()
{
    $rules = [
        'shipping_address' => 'required|array',
        'shipping_address.country' => 'required|string|size:2',
        'shipping_address.postal_code' => 'required|string',
    ];

    // Check available shipping regions
    $shippingService = app(ShippingService::class);
    $availableServices = $shippingService->getAvailableServices(
        $this->input('shipping_address.country'),
        $this->input('shipping_address.postal_code')
    );

    if (!empty($availableServices)) {
        $rules['shipping_method'] = [
            'required',
            Rule::in(array_keys($availableServices))
        ];
        
        // Specific shipping methods require additional information
        if ($this->shipping_method === 'express') {
            $rules['delivery_instructions'] = 'required|string|max:500';
            $rules['contact_phone'] = 'required|string';
        }
    }

    return $rules;
}
```

## 📊 OpenAPI Generation Details

### oneOf Schema Generation

Laravel Spectrum generates OpenAPI 3.0 `oneOf` schemas when detecting conditional validation:

```yaml
components:
  schemas:
    UserRequest:
      oneOf:
        - $ref: '#/components/schemas/IndividualUserRequest'
        - $ref: '#/components/schemas/CompanyUserRequest'
        - $ref: '#/components/schemas/GovernmentUserRequest'
      discriminator:
        propertyName: user_type
        mapping:
          individual: '#/components/schemas/IndividualUserRequest'
          company: '#/components/schemas/CompanyUserRequest'
          government: '#/components/schemas/GovernmentUserRequest'
    
    IndividualUserRequest:
      type: object
      required: [user_type, first_name, last_name, date_of_birth, ssn]
      properties:
        user_type:
          type: string
          enum: [individual]
        first_name:
          type: string
          maxLength: 100
        last_name:
          type: string
          maxLength: 100
        date_of_birth:
          type: string
          format: date
        ssn:
          type: string
          pattern: '^\d{3}-\d{2}-\d{4}$'
```

### Condition Description Generation

```yaml
properties:
  payment_method:
    type: string
    enum: [credit_card, paypal, bank_transfer, invoice]
    description: "Required when subscription_type is not 'free'"
    x-condition: "subscription_type !== 'free'"
    
  billing_cycle:
    type: string
    enum: [monthly, quarterly, yearly, custom]
    description: "Required for paid subscriptions. Enterprise allows custom billing."
    x-conditions:
      - when: "subscription_type in ['basic', 'premium']"
        enum: [monthly, yearly]
      - when: "subscription_type === 'enterprise'"
        enum: [monthly, quarterly, yearly, custom]
```

## 💡 Best Practices

### 1. Clear Condition Descriptions

```php
// ✅ Good example: Clear conditions
public function rules()
{
    $isPaidPlan = in_array($this->plan, ['basic', 'pro', 'enterprise']);
    
    return [
        'plan' => 'required|in:free,basic,pro,enterprise',
        'payment_method' => $isPaidPlan ? 'required|string' : 'nullable',
        'billing_address' => $isPaidPlan ? 'required|array' : 'nullable',
    ];
}

// ❌ Bad example: Complex and hard to understand conditions
public function rules()
{
    return [
        'payment_method' => ($this->plan !== 'free' && !$this->is_trial && $this->user()->subscription_expired) ? 'required' : 'nullable',
    ];
}
```

### 2. Custom Validation Messages

```php
public function messages()
{
    return [
        'company_name.required_if' => 'Company name is required for business accounts.',
        'ssn.required_if' => 'SSN is required for individual accounts.',
        'payment_method.required' => 'Payment method is required for paid subscriptions.',
    ];
}
```

### 3. Grouping Conditions

```php
protected function personalInfoRules(): array
{
    return [
        'first_name' => 'required|string',
        'last_name' => 'required|string',
        'date_of_birth' => 'required|date',
    ];
}

protected function businessInfoRules(): array
{
    return [
        'company_name' => 'required|string',
        'tax_id' => 'required|string',
    ];
}

public function rules()
{
    $rules = ['user_type' => 'required|in:personal,business'];
    
    if ($this->user_type === 'personal') {
        $rules = array_merge($rules, $this->personalInfoRules());
    } else {
        $rules = array_merge($rules, $this->businessInfoRules());
    }
    
    return $rules;
}
```

## 🔍 Troubleshooting

### When Conditions Are Not Detected

1. **Check that methods are public**
   ```php
   public function rules() // Must be public
   ```

2. **Check that conditions are statically analyzable**
   ```php
   // ✅ Detectable
   if ($this->isMethod('POST')) { }
   
   // ❌ Difficult to detect
   if ($this->someComplexMethod()) { }
   ```

3. **Clear cache**
   ```bash
   php artisan spectrum:cache:clear
   ```

## 📚 Related Documentation

- [Validation Detection](./validation-detection.md) - Basic validation
- [Advanced Features](./advanced-features.md) - Other advanced features
- [API Reference](./api-reference.md) - Programmatic usage