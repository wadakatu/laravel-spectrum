<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\ControllerInfo;
use LaravelSpectrum\DTO\EnumParameterInfo;
use LaravelSpectrum\DTO\FractalInfo;
use LaravelSpectrum\DTO\InlineValidationInfo;
use LaravelSpectrum\DTO\PaginationInfo;
use LaravelSpectrum\DTO\QueryParameterInfo;
use LaravelSpectrum\DTO\ResponseInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ControllerInfoTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed_with_all_properties(): void
    {
        $fractal = new FractalInfo(
            transformer: 'App\\Transformers\\UserTransformer',
            isCollection: false,
            type: 'item',
        );
        $pagination = new PaginationInfo(type: 'paginate', perPage: 15);
        $queryParam = new QueryParameterInfo(name: 'search', type: 'string');
        $enumParam = new EnumParameterInfo(
            name: 'status',
            type: 'string',
            enum: ['active', 'inactive'],
        );

        $inlineValidation = new InlineValidationInfo(rules: ['name' => 'required']);

        $info = new ControllerInfo(
            formRequest: 'App\\Http\\Requests\\CreateUserRequest',
            inlineValidation: $inlineValidation,
            resource: 'App\\Http\\Resources\\UserResource',
            returnsCollection: false,
            fractal: $fractal,
            pagination: $pagination,
            queryParameters: [$queryParam],
            enumParameters: [$enumParam],
            response: ResponseInfo::fromArray(['type' => 'resource']),
        );

        $this->assertEquals('App\\Http\\Requests\\CreateUserRequest', $info->formRequest);
        $this->assertSame($inlineValidation, $info->inlineValidation);
        $this->assertEquals('App\\Http\\Resources\\UserResource', $info->resource);
        $this->assertFalse($info->returnsCollection);
        $this->assertSame($fractal, $info->fractal);
        $this->assertSame($pagination, $info->pagination);
        $this->assertCount(1, $info->queryParameters);
        $this->assertCount(1, $info->enumParameters);
        $this->assertInstanceOf(ResponseInfo::class, $info->response);
        $this->assertEquals('resource', $info->response->toArray()['type']);
    }

    #[Test]
    public function it_creates_empty_instance(): void
    {
        $info = ControllerInfo::empty();

        $this->assertNull($info->formRequest);
        $this->assertNull($info->inlineValidation);
        $this->assertNull($info->resource);
        $this->assertFalse($info->returnsCollection);
        $this->assertNull($info->fractal);
        $this->assertNull($info->pagination);
        $this->assertEquals([], $info->queryParameters);
        $this->assertEquals([], $info->enumParameters);
        $this->assertNull($info->response);
    }

    #[Test]
    public function it_detects_form_request(): void
    {
        $withFormRequest = new ControllerInfo(
            formRequest: 'App\\Http\\Requests\\CreateUserRequest',
        );
        $withoutFormRequest = ControllerInfo::empty();

        $this->assertTrue($withFormRequest->hasFormRequest());
        $this->assertFalse($withoutFormRequest->hasFormRequest());
    }

    #[Test]
    public function it_detects_resource(): void
    {
        $withResource = new ControllerInfo(
            resource: 'App\\Http\\Resources\\UserResource',
        );
        $withoutResource = ControllerInfo::empty();

        $this->assertTrue($withResource->hasResource());
        $this->assertFalse($withoutResource->hasResource());
    }

    #[Test]
    public function it_detects_pagination(): void
    {
        $withPagination = new ControllerInfo(
            pagination: new PaginationInfo(type: 'paginate'),
        );
        $withoutPagination = ControllerInfo::empty();

        $this->assertTrue($withPagination->hasPagination());
        $this->assertFalse($withoutPagination->hasPagination());
    }

    #[Test]
    public function it_detects_fractal(): void
    {
        $withFractal = new ControllerInfo(
            fractal: new FractalInfo(
                transformer: 'UserTransformer',
                isCollection: false,
                type: 'item',
            ),
        );
        $withoutFractal = ControllerInfo::empty();

        $this->assertTrue($withFractal->hasFractal());
        $this->assertFalse($withoutFractal->hasFractal());
    }

    #[Test]
    public function it_detects_inline_validation(): void
    {
        $withValidation = new ControllerInfo(
            inlineValidation: new InlineValidationInfo(rules: ['name' => 'required']),
        );
        $withoutValidation = ControllerInfo::empty();

        $this->assertTrue($withValidation->hasInlineValidation());
        $this->assertFalse($withoutValidation->hasInlineValidation());
    }

    #[Test]
    public function it_detects_query_parameters(): void
    {
        $withParams = new ControllerInfo(
            queryParameters: [new QueryParameterInfo(name: 'search', type: 'string')],
        );
        $withoutParams = ControllerInfo::empty();

        $this->assertTrue($withParams->hasQueryParameters());
        $this->assertFalse($withoutParams->hasQueryParameters());
    }

    #[Test]
    public function it_detects_enum_parameters(): void
    {
        $withEnums = new ControllerInfo(
            enumParameters: [new EnumParameterInfo(name: 'status', type: 'string', enum: ['active'])],
        );
        $withoutEnums = ControllerInfo::empty();

        $this->assertTrue($withEnums->hasEnumParameters());
        $this->assertFalse($withoutEnums->hasEnumParameters());
    }

    #[Test]
    public function it_detects_response(): void
    {
        $withResponse = new ControllerInfo(
            response: ResponseInfo::fromArray(['type' => 'resource']),
        );
        $withoutResponse = ControllerInfo::empty();

        $this->assertTrue($withResponse->hasResponse());
        $this->assertFalse($withoutResponse->hasResponse());
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $info = new ControllerInfo(
            formRequest: 'CreateUserRequest',
            resource: 'UserResource',
            returnsCollection: true,
            enumParameters: [new EnumParameterInfo(name: 'status', type: 'string', enum: ['active'])],
        );

        $array = $info->toArray();

        $this->assertEquals('CreateUserRequest', $array['formRequest']);
        $this->assertNull($array['inlineValidation']);
        $this->assertEquals('UserResource', $array['resource']);
        $this->assertTrue($array['returnsCollection']);
        $this->assertNull($array['fractal']);
        $this->assertNull($array['pagination']);
        $this->assertEquals([], $array['queryParameters']);
        $this->assertCount(1, $array['enumParameters']);
        $this->assertEquals('status', $array['enumParameters'][0]['name']);
        $this->assertNull($array['response']);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $array = [
            'formRequest' => 'CreateUserRequest',
            'inlineValidation' => null,
            'resource' => 'UserResource',
            'returnsCollection' => true,
            'fractal' => null,
            'pagination' => ['type' => 'paginate'],
            'queryParameters' => [['name' => 'search', 'type' => 'string']],
            'enumParameters' => [],
            'response' => null,
        ];

        $info = ControllerInfo::fromArray($array);

        $this->assertEquals('CreateUserRequest', $info->formRequest);
        $this->assertEquals('UserResource', $info->resource);
        $this->assertTrue($info->returnsCollection);
        $this->assertInstanceOf(PaginationInfo::class, $info->pagination);
        $this->assertEquals('paginate', $info->pagination->type);
        $this->assertCount(1, $info->queryParameters);
        $this->assertInstanceOf(QueryParameterInfo::class, $info->queryParameters[0]);
    }

    #[Test]
    public function it_creates_from_array_with_missing_keys(): void
    {
        $array = [
            'formRequest' => 'CreateUserRequest',
        ];

        $info = ControllerInfo::fromArray($array);

        $this->assertEquals('CreateUserRequest', $info->formRequest);
        $this->assertNull($info->inlineValidation);
        $this->assertNull($info->resource);
        $this->assertFalse($info->returnsCollection);
        $this->assertEquals([], $info->enumParameters);
    }

    #[Test]
    public function it_checks_if_empty(): void
    {
        $empty = ControllerInfo::empty();
        $notEmpty = new ControllerInfo(formRequest: 'SomeRequest');

        $this->assertTrue($empty->isEmpty());
        $this->assertFalse($notEmpty->isEmpty());
    }

    #[Test]
    public function it_checks_if_has_validation(): void
    {
        $withFormRequest = new ControllerInfo(formRequest: 'SomeRequest');
        $withInline = new ControllerInfo(inlineValidation: new InlineValidationInfo(rules: []));
        $withBoth = new ControllerInfo(
            formRequest: 'SomeRequest',
            inlineValidation: new InlineValidationInfo(rules: []),
        );
        $withNeither = ControllerInfo::empty();

        $this->assertTrue($withFormRequest->hasValidation());
        $this->assertTrue($withInline->hasValidation());
        $this->assertTrue($withBoth->hasValidation());
        $this->assertFalse($withNeither->hasValidation());
    }

    #[Test]
    public function it_creates_from_array_with_fractal_data(): void
    {
        $array = [
            'fractal' => [
                'transformer' => 'UserTransformer',
                'collection' => true,
                'type' => 'collection',
                'hasIncludes' => true,
            ],
        ];

        $info = ControllerInfo::fromArray($array);

        $this->assertInstanceOf(FractalInfo::class, $info->fractal);
        $this->assertEquals('UserTransformer', $info->fractal->transformer);
        $this->assertTrue($info->fractal->isCollection);
        $this->assertEquals('collection', $info->fractal->type);
        $this->assertTrue($info->fractal->hasIncludes);
    }

    #[Test]
    public function it_creates_from_array_with_enum_parameters(): void
    {
        $array = [
            'enumParameters' => [
                [
                    'name' => 'status',
                    'type' => 'string',
                    'enum' => ['active', 'inactive'],
                    'required' => true,
                    'in' => 'path',
                ],
            ],
        ];

        $info = ControllerInfo::fromArray($array);

        $this->assertCount(1, $info->enumParameters);
        $this->assertInstanceOf(EnumParameterInfo::class, $info->enumParameters[0]);
        $this->assertEquals('status', $info->enumParameters[0]->name);
        $this->assertEquals(['active', 'inactive'], $info->enumParameters[0]->enum);
    }
}
