# Laravel Spectrum デモ動画作成タスク

Laravel Spectrumのデモ動画撮影のための環境構築とスクリプトを作成してください。

## 1. デモプロジェクトのセットアップ

以下の構造でデモ用のLaravelプロジェクトを準備してください：

### 必要なファイル作成

1. **コントローラー作成**
```bash
php artisan make:controller Api/UserController --api
php artisan make:controller Api/PostController --api
php artisan make:controller Api/AuthController
```

2. **FormRequest作成**
```bash
php artisan make:request StoreUserRequest
php artisan make:request UpdateUserRequest
php artisan make:request LoginRequest
```

3. **Resource作成**
```bash
php artisan make:resource UserResource
php artisan make:resource PostResource
php artisan make:resource UserCollection
```

### routes/api.php の内容
```php
use App\Http\Controllers\Api\{UserController, PostController, AuthController};

// Public routes
Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/register', [AuthController::class, 'register']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('users', UserController::class);
    Route::apiResource('posts', PostController::class);
    Route::post('users/search', [UserController::class, 'search']);
    Route::get('profile', [UserController::class, 'profile']);
});
```

### StoreUserRequest の内容
```php
public function rules(): array
{
    return [
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users',
        'password' => 'required|string|min:8|confirmed',
        'role' => 'required|in:admin,editor,user',
        'profile' => 'array',
        'profile.bio' => 'nullable|string|max:500',
        'profile.avatar' => 'nullable|url',
        'profile.website' => 'nullable|url',
        'tags' => 'array',
        'tags.*' => 'exists:tags,id'
    ];
}

public function messages(): array
{
    return [
        'email.unique' => 'This email address is already registered.',
        'password.confirmed' => 'Password confirmation does not match.',
        'role.in' => 'Please select a valid role.',
    ];
}
```

### UserResource の内容
```php
public function toArray($request): array
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'email' => $this->email,
        'role' => $this->role,
        'verified' => $this->hasVerifiedEmail(),
        'profile' => [
            'bio' => $this->profile?->bio,
            'avatar' => $this->profile?->avatar_url,
            'website' => $this->profile?->website,
        ],
        'posts_count' => $this->whenCounted('posts'),
        'posts' => PostResource::collection($this->whenLoaded('posts')),
        'created_at' => $this->created_at->toDateTimeString(),
        'updated_at' => $this->updated_at->toDateTimeString(),
    ];
}
```

### UserController の search メソッド
```php
public function search(Request $request)
{
    $this->validate($request, [
        'query' => 'required|string|min:3|max:100',
        'per_page' => 'integer|between:10,100',
        'sort_by' => 'in:name,email,created_at',
        'sort_order' => 'in:asc,desc'
    ]);
    
    $users = User::where('name', 'like', "%{$request->query}%")
        ->orWhere('email', 'like', "%{$request->query}%")
        ->orderBy($request->sort_by ?? 'name', $request->sort_order ?? 'asc')
        ->paginate($request->per_page ?? 15);
    
    return UserResource::collection($users);
}
```

## 2. デモ撮影用スクリプト作成

`demo-script.sh` を作成：

```bash
#!/bin/bash

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# Clear screen
clear

# Opening
echo -e "${PURPLE}✨ Laravel Spectrum Demo${NC}"
echo -e "${BLUE}🎯 Zero-annotation API Documentation Generator${NC}"
echo ""
sleep 3

# Step 1: Installation
echo -e "${YELLOW}📦 Installing Laravel Spectrum...${NC}"
echo "$ composer require wadakatu/laravel-spectrum"
sleep 2
echo -e "${GREEN}✓ Package installed successfully${NC}"
echo ""
sleep 2

# Step 2: Generate documentation
echo -e "${YELLOW}📝 Generating API documentation...${NC}"
echo "$ php artisan prism:generate"
sleep 1
echo -e "${BLUE}🔍 Analyzing routes...${NC}"
sleep 1
echo "Found 12 API routes"
echo -e "${BLUE}📋 Detecting authentication schemes...${NC}"
echo "  ✓ Sanctum Bearer Token"
sleep 1
echo -e "${BLUE}🔍 Analyzing FormRequests...${NC}"
echo "  ✓ StoreUserRequest"
echo "  ✓ UpdateUserRequest"
echo "  ✓ LoginRequest"
sleep 1
echo -e "${BLUE}📦 Analyzing Resources...${NC}"
echo "  ✓ UserResource"
echo "  ✓ PostResource"
sleep 1
echo -e "${GREEN}✅ Documentation generated: storage/app/prism/openapi.json${NC}"
echo -e "⏱️  Generation completed in 1.3 seconds"
echo ""
sleep 3

# Step 3: Show generated features
echo -e "${YELLOW}🎉 Auto-detected features:${NC}"
echo "  • FormRequest validation rules with types"
echo "  • Custom error messages"
echo "  • Resource response structures"
echo "  • Authentication requirements"
echo "  • 422 validation error responses"
echo ""
sleep 3

# Step 4: Start watch mode
echo -e "${YELLOW}🔥 Starting real-time preview...${NC}"
echo "$ php artisan prism:watch"
sleep 1
echo -e "${GREEN}🚀 Starting Laravel Spectrum preview server...${NC}"
echo -e "${BLUE}📡 Preview server running at http://127.0.0.1:8080${NC}"
echo -e "${BLUE}👀 Watching for file changes...${NC}"
echo "Press Ctrl+C to stop"
```

## 3. デモ撮影手順

### 録画ソフトの設定
- **解像度**: 1280x720 または 1920x1080
- **FPS**: 15fps（GIF用）または 30fps（MP4用）
- **録画範囲**: ターミナルとブラウザが両方見える構成

### ターミナル設定
```bash
# フォントサイズを18ptに設定
# 背景色: #1e1e1e (VSCode Dark)
# 文字色: #d4d4d4
# ウィンドウサイズ: 120x35
```

### 撮影フロー

1. **準備** (録画前)
    - ターミナルをクリア
    - ブラウザでlocalhost:8080を開いておく（まだ何も表示されない状態）
    - VSCodeでUserController.phpを開いておく

2. **録画開始**
    - demo-script.shを実行
    - スクリプト実行中、適切なタイミングでブラウザに切り替え
    - php artisan prism:watch実行後、ブラウザをリロード
    - Swagger UIが表示されたら、以下を見せる：
        - エンドポイント一覧
        - POST /api/usersをクリックして展開
        - Request bodyのスキーマ（FormRequestから自動生成）
        - 422エラーレスポンスの詳細

3. **ライブリロードデモ**
    - VSCodeに切り替え
    - UserControllerにメソッドを追加して保存
    - ブラウザが自動更新される様子を撮影

4. **録画終了**
    - 全体で40-50秒に収める

## 4. GIF変換コマンド

```bash
# MP4からGIFへ変換（高品質）
ffmpeg -i demo.mp4 -vf "fps=15,scale=1000:-1:flags=lanczos,split[s0][s1];[s0]palettegen[p];[s1][p]paletteuse" -loop 0 demo.gif

# ファイルサイズが大きい場合は幅を調整
ffmpeg -i demo.mp4 -vf "fps=12,scale=800:-1:flags=lanczos,split[s0][s1];[s0]palettegen[p];[s1][p]paletteuse" -loop 0 demo-small.gif
```

## 5. README.mdへの埋め込み

```markdown
![Laravel Spectrum Demo](./assets/demo.gif)
```

または

```markdown
<p align="center">
  <img src="./assets/demo.gif" alt="Laravel Spectrum Demo" width="100%">
</p>
```

## 6. チェックリスト

- [ ] デモプロジェクトの準備完了
- [ ] 全てのサンプルファイル作成
- [ ] demo-script.sh作成と実行権限付与
- [ ] ターミナルの見た目調整
- [ ] 録画ソフトの設定完了
- [ ] テスト録画で時間確認（40-50秒）
- [ ] GIFファイルサイズ確認（10MB以下）
- [ ] README.mdへの埋め込み確認