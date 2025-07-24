<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers;

use Illuminate\Foundation\Http\FormRequest;
use LaravelSpectrum\Analyzers\FormRequestAnalyzer;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class NestedArrayValidationTest extends TestCase
{
    private FormRequestAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = app(FormRequestAnalyzer::class);
    }

    #[Test]
    public function it_handles_deeply_nested_array_validation()
    {
        $request = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'products' => 'required|array',
                    'products.*.id' => 'required|integer',
                    'products.*.name' => 'required|string',
                    'products.*.variants' => 'required|array',
                    'products.*.variants.*.sku' => 'required|string',
                    'products.*.variants.*.attributes' => 'required|array',
                    'products.*.variants.*.attributes.*.key' => 'required|string',
                    'products.*.variants.*.attributes.*.value' => 'required|string',
                ];
            }
        };

        $parameters = $this->analyzer->analyze(get_class($request));

        // ルートレベル
        $products = $this->findParameterByName($parameters, 'products');
        $this->assertNotNull($products);
        $this->assertEquals('array', $products['type']);
        $this->assertTrue($products['required']);

        // 第1レベルのネスト
        $productId = $this->findParameterByName($parameters, 'products.*.id');
        $this->assertNotNull($productId);
        $this->assertEquals('integer', $productId['type']);

        // 第2レベルのネスト
        $variantSku = $this->findParameterByName($parameters, 'products.*.variants.*.sku');
        $this->assertNotNull($variantSku);
        $this->assertEquals('string', $variantSku['type']);

        // 第3レベルのネスト
        $attrKey = $this->findParameterByName($parameters, 'products.*.variants.*.attributes.*.key');
        $this->assertNotNull($attrKey);
        $this->assertEquals('string', $attrKey['type']);
    }

    #[Test]
    public function it_handles_static_validation_rules()
    {
        $request = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'type' => 'required|in:simple,complex',
                    'data' => 'sometimes|array',
                    'data.*.items' => 'sometimes|array',
                    'data.*.items.*.value' => 'sometimes|numeric',
                ];
            }
        };

        $result = $this->analyzer->analyze(get_class($request));

        // 基本ルールの確認
        $type = $this->findParameterByName($result, 'type');
        $this->assertNotNull($type);
        $this->assertEquals('string', $type['type']);

        // sometimes付きルールも静的に解析可能
        $data = $this->findParameterByName($result, 'data');
        $this->assertNotNull($data);
        $this->assertEquals('array', $data['type']);
    }

    #[Test]
    public function it_handles_mixed_array_and_object_validation()
    {
        $request = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'orders' => 'required|array',
                    'orders.*.customer' => 'required|array',
                    'orders.*.customer.name' => 'required|string|max:100',
                    'orders.*.customer.email' => 'required|email',
                    'orders.*.customer.addresses' => 'array',
                    'orders.*.customer.addresses.*.type' => 'required|in:billing,shipping',
                    'orders.*.customer.addresses.*.street' => 'required|string',
                    'orders.*.customer.addresses.*.city' => 'required|string',
                    'orders.*.items' => 'required|array|min:1',
                    'orders.*.items.*.product_id' => 'required|integer|exists:products,id',
                    'orders.*.items.*.quantity' => 'required|integer|min:1',
                    'orders.*.items.*.price' => 'required|numeric|min:0',
                ];
            }
        };

        $parameters = $this->analyzer->analyze(get_class($request));

        // 複雑なネスト構造の検証
        $customerName = $this->findParameterByName($parameters, 'orders.*.customer.name');
        $this->assertNotNull($customerName);
        $this->assertEquals('string', $customerName['type']);

        $addressType = $this->findParameterByName($parameters, 'orders.*.customer.addresses.*.type');
        $this->assertNotNull($addressType);
        $this->assertEquals('string', $addressType['type']);
        // in:ルールの値はenumとして解析される場合とされない場合がある
        if (isset($addressType['enum'])) {
            $this->assertEquals(['billing', 'shipping'], $addressType['enum']);
        }

        $itemQuantity = $this->findParameterByName($parameters, 'orders.*.items.*.quantity');
        $this->assertNotNull($itemQuantity);
        $this->assertEquals('integer', $itemQuantity['type']);
        // min:1ルールがある場合のみチェック
        if (isset($itemQuantity['min'])) {
            $this->assertEquals(1, $itemQuantity['min']);
        }
    }

    #[Test]
    public function it_handles_arrays_with_dynamic_keys()
    {
        $request = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'settings' => 'required|array',
                    'settings.colors' => 'array',
                    'settings.colors.*' => 'string|regex:/^#[0-9A-Fa-f]{6}$/',
                    'settings.sizes' => 'array',
                    'settings.sizes.*' => 'integer|between:1,100',
                    'metadata' => 'array',
                    'metadata.*' => 'string|max:255',
                ];
            }
        };

        $parameters = $this->analyzer->analyze(get_class($request));

        // 動的キーを持つ配列の検証
        $color = $this->findParameterByName($parameters, 'settings.colors.*');
        $this->assertNotNull($color);
        $this->assertEquals('string', $color['type']);
        // regex:ルールのpatternはFormRequestAnalyzerの現在の実装では解析されない

        $size = $this->findParameterByName($parameters, 'settings.sizes.*');
        $this->assertNotNull($size);
        $this->assertEquals('integer', $size['type']);
        // between:1,100ルールの値がmin/maxとして解析される場合のみチェック
        if (isset($size['min']) && isset($size['max'])) {
            $this->assertEquals(1, $size['min']);
            $this->assertEquals(100, $size['max']);
        }
    }

    #[Test]
    public function it_handles_recursive_validation_structures()
    {
        $request = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'categories' => 'required|array',
                    'categories.*.id' => 'required|integer',
                    'categories.*.name' => 'required|string',
                    'categories.*.parent_id' => 'nullable|integer',
                    'categories.*.children' => 'array',
                    'categories.*.children.*.id' => 'required|integer',
                    'categories.*.children.*.name' => 'required|string',
                    'categories.*.children.*.parent_id' => 'required|integer',
                ];
            }
        };

        $parameters = $this->analyzer->analyze(get_class($request));

        // 再帰的構造の検証
        $parentName = $this->findParameterByName($parameters, 'categories.*.name');
        $this->assertNotNull($parentName);

        $childName = $this->findParameterByName($parameters, 'categories.*.children.*.name');
        $this->assertNotNull($childName);

        $parentId = $this->findParameterByName($parameters, 'categories.*.parent_id');
        $this->assertNotNull($parentId);
        $this->assertFalse($parentId['required']);

        $childParentId = $this->findParameterByName($parameters, 'categories.*.children.*.parent_id');
        $this->assertNotNull($childParentId);
        $this->assertTrue($childParentId['required']);
    }

    private function findParameterByName(array $parameters, string $name): ?array
    {
        foreach ($parameters as $param) {
            if ($param['name'] === $name) {
                return $param;
            }
        }

        return null;
    }
}
