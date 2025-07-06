<?php

namespace LaravelPrism\Tests\Feature;

use Illuminate\Support\Facades\Route;
use LaravelPrism\Tests\TestCase;

class AuthenticationIntegrationTest extends TestCase
{
    /** @test */
    public function it_includes_authentication_in_generated_openapi_spec()
    {
        // テスト用のルートを作成
        Route::middleware(['auth:sanctum'])->group(function () {
            Route::get('api/profile', '\\LaravelPrism\\Tests\\Fixtures\\Controllers\\ProfileController@show')->name('profile.show');
            Route::put('api/profile', '\\LaravelPrism\\Tests\\Fixtures\\Controllers\\ProfileController@update')->name('profile.update');
        });

        Route::get('api/public/posts', '\\LaravelPrism\\Tests\\Fixtures\\Controllers\\PostController@index')->name('posts.index');

        Route::middleware(['auth:api'])->group(function () {
            Route::get('api/admin/users', '\\LaravelPrism\\Tests\\Fixtures\\Controllers\\AdminController@users')
                ->middleware('role:admin')
                ->name('admin.users');
        });

        // OpenAPI仕様を生成
        $openapi = $this->generateOpenApi();

        // securitySchemesが生成されている
        $this->assertArrayHasKey('components', $openapi);
        $this->assertArrayHasKey('securitySchemes', $openapi['components']);
        $this->assertArrayHasKey('sanctumAuth', $openapi['components']['securitySchemes']);
        $this->assertArrayHasKey('apiAuth', $openapi['components']['securitySchemes']);

        // 認証が必要なエンドポイントにsecurityが設定されている
        $profileGet = $openapi['paths']['/api/profile']['get'];
        $this->assertArrayHasKey('security', $profileGet);
        $this->assertEquals([['sanctumAuth' => []]], $profileGet['security']);

        // 公開エンドポイントにはsecurityがない
        $publicPosts = $openapi['paths']['/api/public/posts']['get'];
        $this->assertArrayNotHasKey('security', $publicPosts);

        // 異なる認証方式も正しく設定されている
        $adminUsers = $openapi['paths']['/api/admin/users']['get'];
        $this->assertArrayHasKey('security', $adminUsers);
        $this->assertEquals([['apiAuth' => []]], $adminUsers['security']);
    }

    /** @test */
    public function it_generates_oauth2_authentication_for_passport()
    {
        // Passport認証のルート
        Route::middleware(['passport'])->group(function () {
            Route::get('api/oauth/test', '\\LaravelPrism\\Tests\\Fixtures\\Controllers\\OAuthController@test');
        });

        // OAuth2設定
        config(['prism.authentication.oauth2' => [
            'authorization_url' => 'https://example.com/oauth/authorize',
            'token_url' => 'https://example.com/oauth/token',
            'scopes' => [
                'read' => 'Read access',
                'write' => 'Write access',
            ],
        ]]);

        // OpenAPI仕様を生成
        $openapi = $this->generateOpenApi();

        // OAuth2スキームが生成されている
        $oauth2Scheme = $openapi['components']['securitySchemes']['passportAuth'];
        $this->assertEquals('oauth2', $oauth2Scheme['type']);
        $this->assertArrayHasKey('flows', $oauth2Scheme);
    }
}
