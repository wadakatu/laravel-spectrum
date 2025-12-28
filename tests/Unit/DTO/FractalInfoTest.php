<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\FractalInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FractalInfoTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed(): void
    {
        $info = new FractalInfo(
            transformer: 'App\\Transformers\\UserTransformer',
            isCollection: false,
            type: 'item',
            hasIncludes: true,
        );

        $this->assertEquals('App\\Transformers\\UserTransformer', $info->transformer);
        $this->assertFalse($info->isCollection);
        $this->assertEquals('item', $info->type);
        $this->assertTrue($info->hasIncludes);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $array = [
            'transformer' => 'UserTransformer',
            'collection' => true,
            'type' => 'collection',
            'hasIncludes' => false,
        ];

        $info = FractalInfo::fromArray($array);

        $this->assertEquals('UserTransformer', $info->transformer);
        $this->assertTrue($info->isCollection);
        $this->assertEquals('collection', $info->type);
        $this->assertFalse($info->hasIncludes);
    }

    #[Test]
    public function it_creates_from_array_with_is_collection_key(): void
    {
        $array = [
            'transformer' => 'UserTransformer',
            'isCollection' => true,
            'type' => 'collection',
        ];

        $info = FractalInfo::fromArray($array);

        $this->assertTrue($info->isCollection);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $info = new FractalInfo(
            transformer: 'UserTransformer',
            isCollection: true,
            type: 'collection',
            hasIncludes: true,
        );

        $array = $info->toArray();

        $this->assertEquals('UserTransformer', $array['transformer']);
        $this->assertTrue($array['collection']);
        $this->assertEquals('collection', $array['type']);
        $this->assertTrue($array['hasIncludes']);
    }

    #[Test]
    public function it_checks_if_item(): void
    {
        $item = new FractalInfo(transformer: 'T', isCollection: false, type: 'item');
        $collection = new FractalInfo(transformer: 'T', isCollection: true, type: 'collection');

        $this->assertTrue($item->isItem());
        $this->assertFalse($collection->isItem());
    }
}
