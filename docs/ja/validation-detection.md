---
id: validation-detection
title: バリデーション検出ガイド
sidebar_label: バリデーション検出ガイド
---

# バリデーション検出ガイド

Laravel Spectrumの強力なバリデーション検出機能について詳しく説明します。FormRequestクラス、インラインバリデーション、条件付きバリデーションなど、様々なバリデーションパターンを自動的に検出してドキュメント化します。

## 📋 対応するバリデーション方式

### 1. FormRequestクラス

最も推奨される方法です。Laravel Spectrumは`FormRequest`を完全に解析します。

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'age' => 'nullable|integer|min:0|max:150',
            'role' => 'required|in:admin,editor,viewer',
            'profile_image' => 'nullable|image|mimes:jpeg,png|max:2048',
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'ユーザー名',
            'email' => 'メールアドレス',
            'password' => 'パスワード',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'ユーザー名は必須です。',
            'email.unique' => 'このメールアドレスは既に使用されています。',
        ];
    }
}
```

### 2. インラインバリデーション

コントローラー内の`validate()`メソッドも検出されます。

```php
public function store(Request $request)
{
    $validated = $request->validate([
        'title' => 'required|string|max:255',
        'content' => 'required|string',
        'published_at' => 'nullable|date',
        'tags' => 'array',
        'tags.*' => 'string|max:50',
    ]);

    // ...
}
```

### 3. Validatorファサード

```php
public function update(Request $request, $id)
{
    $validator = Validator::make($request->all(), [
        'name' => 'sometimes|required|string|max:255',
        'status' => 'sometimes|required|in:active,inactive',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    // ...
}
```

## 🎯 高度なバリデーション機能

### 配列とネストされたデータ

```php
public function rules()
{
    return [
        'users' => 'required|array|min:1',
        'users.*.name' => 'required|string',
        'users.*.email' => 'required|email|distinct',
        'users.*.profile' => 'required|array',
        'users.*.profile.bio' => 'nullable|string|max:500',
        'users.*.profile.avatar' => 'nullable|url',
        
        'settings' => 'required|array',
        'settings.notifications' => 'required|array',
        'settings.notifications.email' => 'boolean',
        'settings.notifications.push' => 'boolean',
    ];
}
```

生成されるOpenAPIスキーマ：

```json
{
  "users": {
    "type": "array",
    "minItems": 1,
    "items": {
      "type": "object",
      "required": ["name", "email", "profile"],
      "properties": {
        "name": { "type": "string" },
        "email": { "type": "string", "format": "email" },
        "profile": {
          "type": "object",
          "properties": {
            "bio": { "type": "string", "maxLength": 500 },
            "avatar": { "type": "string", "format": "uri" }
          }
        }
      }
    }
  }
}
```

### ファイルアップロード

```php
public function rules()
{
    return [
        'avatar' => 'required|image|mimes:jpeg,png,jpg|max:2048|dimensions:min_width=100,min_height=100',
        'document' => 'required|file|mimes:pdf,doc,docx|max:10240',
        'gallery' => 'required|array|max:5',
        'gallery.*' => 'image|mimes:jpeg,png|max:1024',
    ];
}
```

適切な`multipart/form-data`エンコーディングでドキュメント化されます。

### カスタムバリデーションルール

```php
use App\Rules\PhoneNumber;
use Illuminate\Validation\Rule;

public function rules()
{
    return [
        'phone' => ['required', new PhoneNumber],
        'country' => ['required', Rule::in(['JP', 'US', 'UK'])],
        'username' => [
            'required',
            'string',
            'max:30',
            Rule::unique('users')->ignore($this->user),
        ],
    ];
}
```

### Enum型の検出

```php
use App\Enums\UserStatus;
use App\Enums\UserRole;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

public function rules()
{
    return [
        // Rule::enumの使用
        'status' => ['required', Rule::enum(UserStatus::class)],
        
        // Enumルールクラスの使用
        'role' => ['required', new Enum(UserRole::class)],
        
        // 文字列ベースのin検証もサポート
        'priority' => 'required|in:low,medium,high,urgent',
    ];
}
```

## ⚡ 条件付きバリデーション

### HTTPメソッドに基づく条件

```php
public function rules()
{
    $rules = [
        'name' => 'required|string|max:255',
        'email' => 'required|email',
    ];

    if ($this->isMethod('POST')) {
        $rules['password'] = 'required|string|min:8|confirmed';
        $rules['email'] .= '|unique:users';
    } elseif ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
        $rules['password'] = 'sometimes|nullable|string|min:8|confirmed';
        $rules['email'] .= '|unique:users,email,' . $this->route('user');
    }

    return $rules;
}
```

### 他のフィールドに基づく条件

```php
public function rules()
{
    return [
        'type' => 'required|in:personal,business',
        'first_name' => 'required_if:type,personal|string|max:255',
        'last_name' => 'required_if:type,personal|string|max:255',
        'company_name' => 'required_if:type,business|string|max:255',
        'tax_id' => 'required_if:type,business|string|regex:/^[A-Z0-9\-]+$/',
        
        'subscribe_newsletter' => 'boolean',
        'email_frequency' => 'required_if:subscribe_newsletter,true|in:daily,weekly,monthly',
    ];
}
```

### Rule::whenを使用した条件

```php
use Illuminate\Validation\Rule;

public function rules()
{
    return [
        'account_type' => 'required|in:free,premium,enterprise',
        
        'payment_method' => Rule::when(
            in_array($this->account_type, ['premium', 'enterprise']),
            'required|in:credit_card,paypal,bank_transfer',
            'nullable'
        ),
        
        'billing_address' => Rule::when(
            fn() => $this->account_type !== 'free' && $this->payment_method === 'credit_card',
            'required|array',
            'nullable'
        ),
    ];
}
```

### 複雑な条件付きバリデーション

```php
public function rules()
{
    $rules = [
        'event_type' => 'required|in:online,offline,hybrid',
        'location' => 'required_unless:event_type,online|string',
        'streaming_url' => 'required_if:event_type,online,hybrid|url',
        'max_attendees' => 'required|integer|min:1',
    ];

    // 条件に基づいて追加ルール
    if ($this->event_type === 'offline' || $this->event_type === 'hybrid') {
        $rules['venue_capacity'] = 'required|integer|gte:max_attendees';
        $rules['safety_measures'] = 'required|array';
    }

    return $rules;
}
```

## 🔧 型推論とスキーマ生成

### バリデーションルールから型を推論

| バリデーションルール | 推論される型 | OpenAPIスキーマ |
|-------------------|------------|---------------|
| `string` | string | `{ "type": "string" }` |
| `integer` | integer | `{ "type": "integer" }` |
| `numeric` | number | `{ "type": "number" }` |
| `boolean` | boolean | `{ "type": "boolean" }` |
| `array` | array | `{ "type": "array" }` |
| `date` | string | `{ "type": "string", "format": "date" }` |
| `email` | string | `{ "type": "string", "format": "email" }` |
| `url` | string | `{ "type": "string", "format": "uri" }` |
| `file`/`image` | file | `{ "type": "string", "format": "binary" }` |

### 制約の変換

| バリデーションルール | OpenAPI制約 |
|-------------------|------------|
| `min:3` | `{ "minimum": 3 }` (数値) / `{ "minLength": 3 }` (文字列) |
| `max:100` | `{ "maximum": 100 }` (数値) / `{ "maxLength": 100 }` (文字列) |
| `between:1,10` | `{ "minimum": 1, "maximum": 10 }` |
| `size:5` | `{ "minItems": 5, "maxItems": 5 }` (配列) |
| `in:a,b,c` | `{ "enum": ["a", "b", "c"] }` |
| `regex:/pattern/` | `{ "pattern": "pattern" }` |

## 💡 ベストプラクティス

### 1. FormRequestを使用する

```php
// ✅ 推奨
public function store(CreateUserRequest $request)
{
    $user = User::create($request->validated());
    return new UserResource($user);
}

// ❌ 非推奨（でも動作します）
public function store(Request $request)
{
    $validated = $request->validate([...]);
}
```

### 2. 明示的な型指定

```php
public function rules()
{
    return [
        // ✅ 型が明確
        'age' => 'required|integer|min:0|max:150',
        'price' => 'required|numeric|min:0',
        'is_active' => 'required|boolean',
        
        // ❌ 型が曖昧
        'value' => 'required',
    ];
}
```

### 3. 意味のあるバリデーションメッセージ

```php
public function messages()
{
    return [
        'email.unique' => 'このメールアドレスは既に登録されています。',
        'password.min' => 'パスワードは:min文字以上で入力してください。',
        'age.between' => '年齢は:minから:max歳の間で入力してください。',
    ];
}

public function attributes()
{
    return [
        'email' => 'メールアドレス',
        'password' => 'パスワード',
        'age' => '年齢',
    ];
}
```

### 4. バリデーションの再利用

```php
// 共通のバリデーションルールを定義
trait CommonValidationRules
{
    protected function passwordRules(): array
    {
        return ['required', 'string', 'min:8', 'regex:/[A-Z]/', 'regex:/[0-9]/'];
    }

    protected function emailRules(bool $unique = true): array
    {
        $rules = ['required', 'email', 'max:255'];
        
        if ($unique) {
            $rules[] = 'unique:users,email';
        }
        
        return $rules;
    }
}
```

## 🔍 トラブルシューティング

### バリデーションが検出されない場合

1. **FormRequestの`authorize()`メソッドを確認**
   ```php
   public function authorize()
   {
       return true; // falseだと解析されません
   }
   ```

2. **名前空間とuse文を確認**
   ```php
   use Illuminate\Foundation\Http\FormRequest; // 必須
   ```

3. **タイプヒントを確認**
   ```php
   // ✅ 正しい
   public function store(CreateUserRequest $request)
   
   // ❌ 検出されない
   public function store($request)
   ```

### 条件付きバリデーションが正しく表示されない

1. **キャッシュをクリア**
   ```bash
   php artisan spectrum:cache:clear
   ```

2. **条件付き解析を有効化**
   ```bash
   php artisan spectrum:generate --analyze-conditions
   ```

## 📚 関連ドキュメント

- [基本的な使い方](./basic-usage.md) - FormRequestの基本
- [高度な機能](./advanced-features.md) - 複雑なバリデーション
- [APIリファレンス](./api-reference.md) - バリデーション解析API