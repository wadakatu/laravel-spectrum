<?php

namespace LaravelSpectrum\Tests\Unit\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LaravelSpectrum\Support\ModelSchemaExtractor;
use LaravelSpectrum\Tests\TestCase;
use Mockery;

class ModelSchemaExtractorTest extends TestCase
{
    private ModelSchemaExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new ModelSchemaExtractor;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    public function test_returns_empty_schema_for_non_existent_class(): void
    {
        $result = $this->extractor->extractSchema('NonExistentClass');

        $this->assertEquals(['type' => 'object', 'properties' => []], $result);
    }

    public function test_returns_empty_schema_for_non_model_class(): void
    {
        $result = $this->extractor->extractSchema(\stdClass::class);

        $this->assertEquals(['type' => 'object', 'properties' => []], $result);
    }

    public function test_extracts_basic_schema_from_eloquent_model(): void
    {
        // Mock Schema facade
        Schema::shouldReceive('hasTable')
            ->with('test_models')
            ->once()
            ->andReturn(true);

        Schema::shouldReceive('getColumnListing')
            ->with('test_models')
            ->once()
            ->andReturn(['id', 'name', 'email', 'created_at', 'updated_at']);

        Schema::shouldReceive('getColumnType')
            ->with('test_models', 'id')
            ->once()
            ->andReturn('integer');

        Schema::shouldReceive('getColumnType')
            ->with('test_models', 'name')
            ->once()
            ->andReturn('string');

        Schema::shouldReceive('getColumnType')
            ->with('test_models', 'email')
            ->once()
            ->andReturn('string');

        Schema::shouldReceive('getColumnType')
            ->with('test_models', 'created_at')
            ->once()
            ->andReturn('datetime');

        Schema::shouldReceive('getColumnType')
            ->with('test_models', 'updated_at')
            ->once()
            ->andReturn('datetime');

        $result = $this->extractor->extractSchema(TestModel::class);

        $this->assertEquals('object', $result['type']);
        $this->assertStringContainsString('TestModel', $result['description']);
        $this->assertArrayHasKey('properties', $result);
        $this->assertArrayHasKey('id', $result['properties']);
        $this->assertArrayHasKey('name', $result['properties']);
        $this->assertArrayHasKey('email', $result['properties']);
        $this->assertEquals('integer', $result['properties']['id']['type']);
        $this->assertEquals('string', $result['properties']['name']['type']);
    }

    public function test_handles_fillable_attributes(): void
    {
        Schema::shouldReceive('hasTable')
            ->with('test_models_with_fillable')
            ->once()
            ->andReturn(true);

        Schema::shouldReceive('getColumnListing')
            ->with('test_models_with_fillable')
            ->once()
            ->andReturn(['id', 'name', 'email', 'password']);

        Schema::shouldReceive('getColumnType')
            ->andReturn('string');

        $result = $this->extractor->extractSchema(TestModelWithFillable::class);

        // id and password should be readOnly (not in fillable)
        $this->assertTrue($result['properties']['id']['readOnly']);
        $this->assertTrue($result['properties']['password']['readOnly']);

        // name and email should not be readOnly (in fillable)
        $this->assertFalse($result['properties']['name']['readOnly']);
        $this->assertFalse($result['properties']['email']['readOnly']);
    }

    public function test_handles_guarded_attributes(): void
    {
        Schema::shouldReceive('hasTable')
            ->with('test_models_with_guarded')
            ->once()
            ->andReturn(true);

        Schema::shouldReceive('getColumnListing')
            ->with('test_models_with_guarded')
            ->once()
            ->andReturn(['id', 'name', 'email', 'admin']);

        Schema::shouldReceive('getColumnType')
            ->andReturn('string');

        $result = $this->extractor->extractSchema(TestModelWithGuarded::class);

        // id and admin should be readOnly (in guarded)
        $this->assertTrue($result['properties']['id']['readOnly']);
        $this->assertTrue($result['properties']['admin']['readOnly']);

        // name and email should not be readOnly (not in guarded)
        $this->assertFalse($result['properties']['name']['readOnly']);
        $this->assertFalse($result['properties']['email']['readOnly']);
    }

    public function test_handles_hidden_attributes(): void
    {
        Schema::shouldReceive('hasTable')
            ->with('test_models_with_hidden')
            ->once()
            ->andReturn(true);

        Schema::shouldReceive('getColumnListing')
            ->with('test_models_with_hidden')
            ->once()
            ->andReturn(['id', 'name', 'email', 'password', 'secret']);

        Schema::shouldReceive('getColumnType')
            ->andReturn('string');

        $result = $this->extractor->extractSchema(TestModelWithHidden::class);

        // Hidden attributes should not be in properties
        $this->assertArrayNotHasKey('password', $result['properties']);
        $this->assertArrayNotHasKey('secret', $result['properties']);

        // Non-hidden attributes should be present
        $this->assertArrayHasKey('id', $result['properties']);
        $this->assertArrayHasKey('name', $result['properties']);
        $this->assertArrayHasKey('email', $result['properties']);
    }

    public function test_handles_casts(): void
    {
        Schema::shouldReceive('hasTable')
            ->with('test_models_with_casts')
            ->once()
            ->andReturn(true);

        Schema::shouldReceive('getColumnListing')
            ->with('test_models_with_casts')
            ->once()
            ->andReturn(['id', 'is_active', 'metadata', 'price', 'published_at']);

        Schema::shouldReceive('getColumnType')
            ->andReturn('string');

        $result = $this->extractor->extractSchema(TestModelWithCasts::class);

        // Check cast types are properly mapped
        $this->assertEquals('integer', $result['properties']['id']['type']);
        $this->assertEquals('boolean', $result['properties']['is_active']['type']);
        $this->assertEquals('object', $result['properties']['metadata']['type']);
        $this->assertEquals('number', $result['properties']['price']['type']);
        $this->assertEquals('string', $result['properties']['published_at']['type']);
        $this->assertEquals('date-time', $result['properties']['published_at']['format']);
    }

    public function test_handles_appends_attributes(): void
    {
        Schema::shouldReceive('hasTable')
            ->with('test_models_with_appends')
            ->once()
            ->andReturn(true);

        Schema::shouldReceive('getColumnListing')
            ->with('test_models_with_appends')
            ->once()
            ->andReturn(['id', 'first_name', 'last_name']);

        Schema::shouldReceive('getColumnType')
            ->andReturn('string');

        $result = $this->extractor->extractSchema(TestModelWithAppends::class);

        // Check that appended attribute is included
        $this->assertArrayHasKey('full_name', $result['properties']);
        $this->assertEquals('string', $result['properties']['full_name']['type']);
        $this->assertEquals('Computed attribute', $result['properties']['full_name']['description']);
    }

    public function test_handles_database_errors_gracefully(): void
    {
        Schema::shouldReceive('hasTable')
            ->with('test_models')
            ->once()
            ->andThrow(new \Exception('Database connection error'));

        $result = $this->extractor->extractSchema(TestModel::class);

        $this->assertEquals(['type' => 'object', 'properties' => []], $result);
    }

    public function test_maps_various_database_types_to_openapi(): void
    {
        Schema::shouldReceive('hasTable')
            ->with('test_models_various_types')
            ->once()
            ->andReturn(true);

        Schema::shouldReceive('getColumnListing')
            ->with('test_models_various_types')
            ->once()
            ->andReturn([
                'int_col', 'bigint_col', 'decimal_col', 'float_col',
                'varchar_col', 'text_col', 'json_col', 'bool_col',
                'date_col', 'datetime_col', 'uuid_col',
            ]);

        Schema::shouldReceive('getColumnType')
            ->with('test_models_various_types', 'int_col')
            ->andReturn('integer');
        Schema::shouldReceive('getColumnType')
            ->with('test_models_various_types', 'bigint_col')
            ->andReturn('bigint');
        Schema::shouldReceive('getColumnType')
            ->with('test_models_various_types', 'decimal_col')
            ->andReturn('decimal');
        Schema::shouldReceive('getColumnType')
            ->with('test_models_various_types', 'float_col')
            ->andReturn('float');
        Schema::shouldReceive('getColumnType')
            ->with('test_models_various_types', 'varchar_col')
            ->andReturn('varchar');
        Schema::shouldReceive('getColumnType')
            ->with('test_models_various_types', 'text_col')
            ->andReturn('text');
        Schema::shouldReceive('getColumnType')
            ->with('test_models_various_types', 'json_col')
            ->andReturn('json');
        Schema::shouldReceive('getColumnType')
            ->with('test_models_various_types', 'bool_col')
            ->andReturn('boolean');
        Schema::shouldReceive('getColumnType')
            ->with('test_models_various_types', 'date_col')
            ->andReturn('date');
        Schema::shouldReceive('getColumnType')
            ->with('test_models_various_types', 'datetime_col')
            ->andReturn('datetime');
        Schema::shouldReceive('getColumnType')
            ->with('test_models_various_types', 'uuid_col')
            ->andReturn('uuid');

        $result = $this->extractor->extractSchema(TestModelVariousTypes::class);

        $this->assertEquals('integer', $result['properties']['int_col']['type']);
        $this->assertEquals('integer', $result['properties']['bigint_col']['type']);
        $this->assertEquals('number', $result['properties']['decimal_col']['type']);
        $this->assertEquals('number', $result['properties']['float_col']['type']);
        $this->assertEquals('string', $result['properties']['varchar_col']['type']);
        $this->assertEquals('string', $result['properties']['text_col']['type']);
        $this->assertEquals('object', $result['properties']['json_col']['type']);
        $this->assertEquals('boolean', $result['properties']['bool_col']['type']);
        $this->assertEquals('string', $result['properties']['date_col']['type']);
        $this->assertEquals('string', $result['properties']['datetime_col']['type']);
        $this->assertEquals('string', $result['properties']['uuid_col']['type']);
    }

    public function test_handles_mysql_column_comments(): void
    {
        Schema::shouldReceive('hasTable')
            ->with('test_models')
            ->once()
            ->andReturn(true);

        Schema::shouldReceive('getColumnListing')
            ->with('test_models')
            ->once()
            ->andReturn(['id', 'name']);

        Schema::shouldReceive('getColumnType')
            ->andReturn('string');

        DB::shouldReceive('connection->getDatabaseName')
            ->andReturn('test_db');

        DB::shouldReceive('connection->getDriverName')
            ->andReturn('mysql');

        DB::shouldReceive('selectOne')
            ->with(
                Mockery::type('string'),
                ['test_db', 'test_models', 'id']
            )
            ->andReturn((object) ['comment' => 'Primary key']);

        DB::shouldReceive('selectOne')
            ->with(
                Mockery::type('string'),
                ['test_db', 'test_models', 'name']
            )
            ->andReturn((object) ['comment' => 'User name']);

        $result = $this->extractor->extractSchema(TestModel::class);

        $this->assertEquals('Primary key', $result['properties']['id']['description']);
        $this->assertEquals('User name', $result['properties']['name']['description']);
    }

    public function test_handles_enum_casts(): void
    {
        Schema::shouldReceive('hasTable')
            ->with('test_models_with_enum_cast')
            ->once()
            ->andReturn(true);

        Schema::shouldReceive('getColumnListing')
            ->with('test_models_with_enum_cast')
            ->once()
            ->andReturn(['id', 'status']);

        Schema::shouldReceive('getColumnType')
            ->andReturn('string');

        $result = $this->extractor->extractSchema(TestModelWithEnumCast::class);

        $this->assertEquals('string', $result['properties']['status']['type']);
    }

    public function test_handles_custom_cast_with_parameters(): void
    {
        Schema::shouldReceive('hasTable')
            ->with('test_models_with_custom_cast')
            ->once()
            ->andReturn(true);

        Schema::shouldReceive('getColumnListing')
            ->with('test_models_with_custom_cast')
            ->once()
            ->andReturn(['id', 'price']);

        Schema::shouldReceive('getColumnType')
            ->andReturn('string');

        $result = $this->extractor->extractSchema(TestModelWithCustomCast::class);

        // decimal:2 should be mapped to number
        $this->assertEquals('number', $result['properties']['price']['type']);
    }
}

// Test Model Classes
class TestModel extends Model
{
    protected $table = 'test_models';
}

class TestModelWithFillable extends Model
{
    protected $table = 'test_models_with_fillable';

    protected $fillable = ['name', 'email'];
}

class TestModelWithGuarded extends Model
{
    protected $table = 'test_models_with_guarded';

    protected $guarded = ['id', 'admin'];
}

class TestModelWithHidden extends Model
{
    protected $table = 'test_models_with_hidden';

    protected $hidden = ['password', 'secret'];
}

class TestModelWithCasts extends Model
{
    protected $table = 'test_models_with_casts';

    protected $casts = [
        'id' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'json',
        'price' => 'float',
        'published_at' => 'datetime',
    ];
}

class TestModelWithAppends extends Model
{
    protected $table = 'test_models_with_appends';

    protected $appends = ['full_name'];

    public function getFullNameAttribute()
    {
        return $this->first_name.' '.$this->last_name;
    }
}

class TestModelVariousTypes extends Model
{
    protected $table = 'test_models_various_types';
}

enum StatusEnum: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}

class TestModelWithEnumCast extends Model
{
    protected $table = 'test_models_with_enum_cast';

    protected $casts = [
        'status' => StatusEnum::class,
    ];
}

class TestModelWithCustomCast extends Model
{
    protected $table = 'test_models_with_custom_cast';

    protected $casts = [
        'price' => 'decimal:2',
    ];
}
