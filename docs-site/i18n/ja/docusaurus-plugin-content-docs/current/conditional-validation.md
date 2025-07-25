# 条件付きバリデーション詳細ガイド

Laravel Spectrumは、複雑な条件付きバリデーションルールを自動的に検出し、適切なOpenAPI 3.0スキーマを生成します。このガイドでは、サポートされているパターンと生成されるドキュメントについて詳しく説明します。

## 🎯 概要

条件付きバリデーションとは、特定の条件に基づいて異なるバリデーションルールを適用する仕組みです。Laravel Spectrumは以下をサポートします：

- HTTPメソッドベースの条件
- フィールド値ベースの条件
- 認証状態ベースの条件
- カスタムロジックベースの条件

## 📋 基本的なパターン

### HTTPメソッドによる条件分岐

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
                // 作成時は全フィールド必須
                $rules['sku'] = 'required|string|unique:products,sku';
                $rules['price'] = 'required|numeric|min:0';
                $rules['stock'] = 'required|integer|min:0';
                $rules['category_id'] = 'required|exists:categories,id';
                break;
                
            case 'PUT':
                // 完全更新時
                $rules['sku'] = 'required|string|unique:products,sku,' . $this->route('product');
                $rules['price'] = 'required|numeric|min:0';
                $rules['stock'] = 'required|integer|min:0';
                $rules['category_id'] = 'required|exists:categories,id';
                break;
                
            case 'PATCH':
                // 部分更新時は全てオプション
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

生成されるOpenAPIスキーマ：

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
              # 同じ構造
              
          - title: "Partial Update (PATCH)"
            type: object
            required: ["name"]
            properties:
              # 全フィールドがオプション（nameを除く）
```

### フィールド値による条件

```php
public function rules()
{
    return [
        'user_type' => 'required|in:individual,company,government',
        
        // 個人ユーザーの場合
        'first_name' => 'required_if:user_type,individual|string|max:100',
        'last_name' => 'required_if:user_type,individual|string|max:100',
        'date_of_birth' => 'required_if:user_type,individual|date|before:-18 years',
        'ssn' => 'required_if:user_type,individual|regex:/^\d{3}-\d{2}-\d{4}$/',
        
        // 法人の場合
        'company_name' => 'required_if:user_type,company,government|string|max:255',
        'registration_number' => 'required_if:user_type,company|string',
        'tax_id' => 'required_if:user_type,company|string',
        'incorporation_date' => 'required_if:user_type,company|date|before:today',
        
        // 政府機関の場合
        'agency_name' => 'required_if:user_type,government|string',
        'department' => 'required_if:user_type,government|string',
        'government_id' => 'required_if:user_type,government|string',
        
        // 共通オプションフィールド
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

## 🔄 複雑な条件パターン

### Rule::whenを使用した条件

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

### 複数条件の組み合わせ

```php
public function rules()
{
    $rules = [
        'event_type' => 'required|in:online,offline,hybrid',
        'start_date' => 'required|date|after:today',
        'end_date' => 'required|date|after:start_date',
        'max_attendees' => 'required|integer|min:1',
    ];

    // オフラインまたはハイブリッドイベントの場合
    if (in_array($this->event_type, ['offline', 'hybrid'])) {
        $rules['venue'] = 'required|array';
        $rules['venue.name'] = 'required|string';
        $rules['venue.address'] = 'required|string';
        $rules['venue.capacity'] = 'required|integer|gte:max_attendees';
        $rules['venue.facilities'] = 'array';
        $rules['venue.facilities.*'] = 'in:parking,wifi,catering,av_equipment';
        
        // COVID-19対策（条件付き）
        if ($this->requires_safety_measures) {
            $rules['safety_measures'] = 'required|array';
            $rules['safety_measures.mask_required'] = 'required|boolean';
            $rules['safety_measures.vaccination_required'] = 'required|boolean';
            $rules['safety_measures.capacity_limit'] = 'required|integer|lte:venue.capacity';
        }
    }

    // オンラインまたはハイブリッドイベントの場合
    if (in_array($this->event_type, ['online', 'hybrid'])) {
        $rules['streaming'] = 'required|array';
        $rules['streaming.platform'] = 'required|in:zoom,teams,youtube,custom';
        $rules['streaming.url'] = 'required_if:streaming.platform,custom|url';
        $rules['streaming.password'] = 'nullable|string';
        $rules['streaming.recording_available'] = 'boolean';
        
        // プラットフォーム固有の設定
        if ($this->input('streaming.platform') === 'zoom') {
            $rules['streaming.meeting_id'] = 'required|string';
            $rules['streaming.passcode'] = 'nullable|string|size:6';
        }
    }

    return $rules;
}
```

## 🎨 認証ベースの条件

```php
public function rules()
{
    $user = $this->user();
    $rules = [
        'title' => 'required|string|max:255',
        'content' => 'required|string',
        'status' => 'required|in:draft,pending,published',
    ];

    // 管理者の場合の追加フィールド
    if ($user && $user->hasRole('admin')) {
        $rules['featured'] = 'boolean';
        $rules['priority'] = 'integer|between:1,10';
        $rules['published_at'] = 'nullable|date';
        $rules['author_id'] = 'nullable|exists:users,id';
        $rules['internal_notes'] = 'nullable|string';
    }

    // 編集者の場合
    if ($user && $user->hasRole('editor')) {
        $rules['tags'] = 'array|max:10';
        $rules['tags.*'] = 'string|exists:tags,name';
        $rules['category_id'] = 'required|exists:categories,id';
    }

    // 一般ユーザーの場合の制限
    if ($user && $user->hasRole('user')) {
        $rules['status'] = 'required|in:draft,pending'; // publishedは選択不可
        $rules['visibility'] = 'required|in:private,friends'; // publicは選択不可
    }

    return $rules;
}
```

## 🔧 動的バリデーション

### データベースの状態に基づく条件

```php
public function rules()
{
    $product = Product::find($this->route('product'));
    
    $rules = [
        'quantity' => [
            'required',
            'integer',
            'min:1',
            // 在庫数以下
            'max:' . ($product ? $product->stock : 0),
        ],
    ];

    // 予約商品の場合
    if ($product && $product->is_preorder) {
        $rules['delivery_date'] = 'required|date|after:' . $product->release_date;
        $rules['deposit_amount'] = 'required|numeric|min:' . ($product->price * 0.2);
    }

    // 限定商品の場合
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

### 外部APIやサービスに基づく条件

```php
public function rules()
{
    $rules = [
        'shipping_address' => 'required|array',
        'shipping_address.country' => 'required|string|size:2',
        'shipping_address.postal_code' => 'required|string',
    ];

    // 配送可能地域をチェック
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
        
        // 特定の配送方法には追加情報が必要
        if ($this->shipping_method === 'express') {
            $rules['delivery_instructions'] = 'required|string|max:500';
            $rules['contact_phone'] = 'required|string';
        }
    }

    return $rules;
}
```

## 📊 OpenAPI生成の詳細

### oneOfスキーマの生成

Laravel Spectrumは条件付きバリデーションを検出すると、OpenAPI 3.0の`oneOf`スキーマを生成します：

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

### 条件の説明生成

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

## 💡 ベストプラクティス

### 1. 明確な条件の記述

```php
// ✅ 良い例：条件が明確
public function rules()
{
    $isPaidPlan = in_array($this->plan, ['basic', 'pro', 'enterprise']);
    
    return [
        'plan' => 'required|in:free,basic,pro,enterprise',
        'payment_method' => $isPaidPlan ? 'required|string' : 'nullable',
        'billing_address' => $isPaidPlan ? 'required|array' : 'nullable',
    ];
}

// ❌ 悪い例：条件が複雑で理解しにくい
public function rules()
{
    return [
        'payment_method' => ($this->plan !== 'free' && !$this->is_trial && $this->user()->subscription_expired) ? 'required' : 'nullable',
    ];
}
```

### 2. バリデーションメッセージのカスタマイズ

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

### 3. 条件のグループ化

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

## 🔍 トラブルシューティング

### 条件が検出されない場合

1. **メソッドが公開されているか確認**
   ```php
   public function rules() // publicである必要があります
   ```

2. **条件が静的に解析可能か確認**
   ```php
   // ✅ 検出可能
   if ($this->isMethod('POST')) { }
   
   // ❌ 検出困難
   if ($this->someComplexMethod()) { }
   ```

3. **キャッシュのクリア**
   ```bash
   php artisan spectrum:cache:clear
   ```

## 📚 関連ドキュメント

- [バリデーション検出](./validation-detection.md) - 基本的なバリデーション
- [高度な機能](./advanced-features.md) - その他の高度な機能
- [APIリファレンス](./api-reference.md) - プログラム的な使用方法