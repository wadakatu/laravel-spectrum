<?php

namespace LaravelSpectrum\Tests\Support;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;

trait TestHelpers
{
    /**
     * FormRequestのモックを作成
     */
    protected function createFormRequest(array $rules, array $messages = [], array $attributes = []): string
    {
        $class = new class($rules, $messages, $attributes) extends FormRequest
        {
            private array $_rules;

            private array $_messages;

            private array $_attributes;

            public function __construct($rules, $messages, $attributes)
            {
                $this->_rules = $rules;
                $this->_messages = $messages;
                $this->_attributes = $attributes;
                parent::__construct();
            }

            public function rules(): array
            {
                return $this->_rules;
            }

            public function messages(): array
            {
                return $this->_messages;
            }

            public function attributes(): array
            {
                return $this->_attributes;
            }
        };

        return get_class($class);
    }

    /**
     * コントローラーのモックを作成
     */
    protected function createController(array $methods): object
    {
        $controller = new class
        {
            private array $methods = [];

            public function __call($name, $arguments)
            {
                if (isset($this->methods[$name])) {
                    return call_user_func_array($this->methods[$name], $arguments);
                }
                throw new \BadMethodCallException("Method {$name} not found");
            }

            public function defineMethod(string $name, callable $callback): void
            {
                $this->methods[$name] = $callback;
            }
        };

        foreach ($methods as $name => $callback) {
            $controller->defineMethod($name, $callback);
        }

        return $controller;
    }

    /**
     * OpenAPIスキーマのアサーションヘルパー
     */
    protected function assertOpenApiHasPath(array $openapi, string $path): void
    {
        $this->assertArrayHasKey('paths', $openapi);
        $this->assertArrayHasKey($path, $openapi['paths'],
            "Path {$path} not found in OpenAPI spec. Available paths: ".
            implode(', ', array_keys($openapi['paths'] ?? []))
        );
    }

    protected function assertParameterExists(array $parameters, string $name): array
    {
        foreach ($parameters as $param) {
            if ($param['name'] === $name) {
                return $param;
            }
        }

        $this->fail("Parameter {$name} not found. Available parameters: ".
            implode(', ', array_column($parameters, 'name'))
        );
    }

    /**
     * ルートの完全なクリーンアップ
     */
    protected function cleanupRoutes(): void
    {
        $router = app('router');
        $routes = $router->getRoutes();

        // 新しいRouteCollectionで置き換え
        $newCollection = new \Illuminate\Routing\RouteCollection;

        $reflection = new \ReflectionProperty($router, 'routes');
        $reflection->setAccessible(true);
        $reflection->setValue($router, $newCollection);
    }

    /**
     * API Resourceのモックを作成
     */
    protected function createResource(array $data): string
    {
        $class = new class($data) extends JsonResource
        {
            private array $_data;

            public function __construct($data)
            {
                $this->_data = $data;
                parent::__construct(null);
            }

            public function toArray($request)
            {
                return $this->_data;
            }
        };

        return get_class($class);
    }

    /**
     * OpenAPIのレスポンススキーマをアサート
     */
    protected function assertResponseSchema(array $openapi, string $path, string $method, int $statusCode = 200): array
    {
        $this->assertOpenApiHasPath($openapi, $path);
        $this->assertArrayHasKey($method, $openapi['paths'][$path]);
        $this->assertArrayHasKey('responses', $openapi['paths'][$path][$method]);
        $this->assertArrayHasKey((string) $statusCode, $openapi['paths'][$path][$method]['responses']);

        return $openapi['paths'][$path][$method]['responses'][(string) $statusCode];
    }

    /**
     * パラメータの型をアサート
     */
    protected function assertParameterType(array $parameter, string $expectedType): void
    {
        if (isset($parameter['schema'])) {
            $this->assertEquals($expectedType, $parameter['schema']['type']);
        } else {
            $this->assertEquals($expectedType, $parameter['type']);
        }
    }

    /**
     * テスト用のルートを登録
     */
    protected function registerTestRoute(string $method, string $uri, $action, array $middleware = []): void
    {
        $route = Route::$method($uri, $action);

        if (! empty($middleware)) {
            $route->middleware($middleware);
        }
    }

    /**
     * OpenAPIのセキュリティスキーマをアサート
     */
    protected function assertSecurityScheme(array $openapi, string $schemeName): void
    {
        $this->assertArrayHasKey('components', $openapi);
        $this->assertArrayHasKey('securitySchemes', $openapi['components']);
        $this->assertArrayHasKey($schemeName, $openapi['components']['securitySchemes']);
    }

    /**
     * パラメータから特定の名前のものを見つける
     */
    protected function findParameterByName(array $parameters, string $name): ?array
    {
        foreach ($parameters as $param) {
            if ($param['name'] === $name) {
                return $param;
            }
        }

        return null;
    }

    /**
     * スキーマのプロパティをアサート
     */
    protected function assertSchemaProperty(array $schema, string $property, string $type): void
    {
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey($property, $schema['properties']);
        $this->assertEquals($type, $schema['properties'][$property]['type']);
    }

    /**
     * 必須プロパティをアサート
     */
    protected function assertRequiredProperties(array $schema, array $expectedRequired): void
    {
        $this->assertArrayHasKey('required', $schema);
        $this->assertEquals(sort($expectedRequired), sort($schema['required']));
    }
}
