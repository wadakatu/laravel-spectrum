<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\PaginationInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PaginationInfoTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed(): void
    {
        $info = new PaginationInfo(
            type: 'paginate',
            model: 'App\\Models\\User',
            resource: 'App\\Http\\Resources\\UserResource',
            perPage: 15,
            hasCustomPerPage: true,
        );

        $this->assertEquals('paginate', $info->type);
        $this->assertEquals('App\\Models\\User', $info->model);
        $this->assertEquals('App\\Http\\Resources\\UserResource', $info->resource);
        $this->assertEquals(15, $info->perPage);
        $this->assertTrue($info->hasCustomPerPage);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $array = [
            'type' => 'simplePaginate',
            'model' => 'App\\Models\\Post',
            'resource' => null,
            'perPage' => 25,
            'hasCustomPerPage' => true,
        ];

        $info = PaginationInfo::fromArray($array);

        $this->assertEquals('simplePaginate', $info->type);
        $this->assertEquals('App\\Models\\Post', $info->model);
        $this->assertNull($info->resource);
        $this->assertEquals(25, $info->perPage);
        $this->assertTrue($info->hasCustomPerPage);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $array = [];

        $info = PaginationInfo::fromArray($array);

        $this->assertEquals('paginate', $info->type);
        $this->assertNull($info->model);
        $this->assertNull($info->resource);
        $this->assertNull($info->perPage);
        $this->assertFalse($info->hasCustomPerPage);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $info = new PaginationInfo(
            type: 'cursorPaginate',
            model: 'App\\Models\\Comment',
            resource: 'CommentResource',
            perPage: 50,
            hasCustomPerPage: true,
        );

        $array = $info->toArray();

        $this->assertEquals('cursorPaginate', $array['type']);
        $this->assertEquals('App\\Models\\Comment', $array['model']);
        $this->assertEquals('CommentResource', $array['resource']);
        $this->assertEquals(50, $array['perPage']);
        $this->assertTrue($array['hasCustomPerPage']);
    }

    #[Test]
    public function it_converts_to_array_without_optional_fields(): void
    {
        $info = new PaginationInfo(type: 'paginate');

        $array = $info->toArray();

        $this->assertEquals('paginate', $array['type']);
        $this->assertArrayNotHasKey('model', $array);
        $this->assertArrayNotHasKey('resource', $array);
        $this->assertArrayNotHasKey('perPage', $array);
        $this->assertArrayNotHasKey('hasCustomPerPage', $array);
    }

    #[Test]
    public function it_checks_pagination_type(): void
    {
        $paginate = new PaginationInfo(type: 'paginate');
        $simple = new PaginationInfo(type: 'simplePaginate');
        $cursor = new PaginationInfo(type: 'cursorPaginate');

        $this->assertTrue($paginate->isPaginated());
        $this->assertFalse($paginate->isSimplePaginated());
        $this->assertFalse($paginate->isCursorBased());

        $this->assertFalse($simple->isPaginated());
        $this->assertTrue($simple->isSimplePaginated());
        $this->assertFalse($simple->isCursorBased());

        $this->assertFalse($cursor->isPaginated());
        $this->assertFalse($cursor->isSimplePaginated());
        $this->assertTrue($cursor->isCursorBased());
    }

    #[Test]
    public function it_checks_model_availability(): void
    {
        $withModel = new PaginationInfo(type: 'paginate', model: 'User');
        $withoutModel = new PaginationInfo(type: 'paginate');

        $this->assertTrue($withModel->hasModel());
        $this->assertFalse($withoutModel->hasModel());
    }

    #[Test]
    public function it_checks_resource_availability(): void
    {
        $withResource = new PaginationInfo(type: 'paginate', resource: 'UserResource');
        $withoutResource = new PaginationInfo(type: 'paginate');

        $this->assertTrue($withResource->hasResource());
        $this->assertFalse($withoutResource->hasResource());
    }
}
