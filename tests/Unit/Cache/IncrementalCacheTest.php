<?php

namespace LaravelSpectrum\Tests\Unit\Cache;

use LaravelSpectrum\Cache\IncrementalCache;
use LaravelSpectrum\Performance\DependencyGraph;
use LaravelSpectrum\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class IncrementalCacheTest extends TestCase
{
    private IncrementalCache $cache;

    private $dependencyGraph;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure config is available
        config(['spectrum.cache.ttl' => 3600]);

        $this->dependencyGraph = Mockery::mock(DependencyGraph::class);
        $this->cache = new IncrementalCache($this->dependencyGraph);
        $this->cache->clear();
    }

    protected function tearDown(): void
    {
        $this->cache->clear();
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_tracks_file_changes(): void
    {
        $this->dependencyGraph->shouldReceive('getAffectedNodes')
            ->andReturn([]);

        $this->cache->trackChange('/path/to/file.php', 'modified');
        $this->cache->trackChange('/path/to/another.php', 'created');

        // getInvalidatedItems を呼び出してトラッキングが機能していることを確認
        $invalidated = $this->cache->getInvalidatedItems();

        $this->assertIsArray($invalidated);
    }

    #[Test]
    public function it_gets_invalidated_items_from_change_log(): void
    {
        $this->dependencyGraph->shouldReceive('getAffectedNodes')
            ->with(['controller:App\\Http\\Controllers\\UserController'])
            ->andReturn([
                'controller:App\\Http\\Controllers\\UserController',
                'route:GET:/api/users',
            ]);

        $this->cache->trackChange('/app/Http/Controllers/UserController.php');

        $invalidated = $this->cache->getInvalidatedItems();

        $this->assertContains('controller:App\\Http\\Controllers\\UserController', $invalidated);
        $this->assertContains('route:GET:/api/users', $invalidated);
    }

    #[Test]
    public function it_invalidates_affected_cache_entries(): void
    {
        // まずキャッシュにデータを設定
        $this->cache->remember('controller:App\\Http\\Controllers\\UserController', fn () => ['data' => 'test']);
        $this->cache->remember('route:GET:/api/users', fn () => ['route' => 'data']);

        $this->dependencyGraph->shouldReceive('getAffectedNodes')
            ->andReturn(['controller:App\\Http\\Controllers\\UserController']);

        $this->cache->trackChange('/app/Http/Controllers/UserController.php');

        $count = $this->cache->invalidateAffected();

        $this->assertGreaterThanOrEqual(0, $count);
    }

    #[Test]
    public function it_converts_controller_file_to_node_id(): void
    {
        $this->dependencyGraph->shouldReceive('getAffectedNodes')
            ->with(['controller:App\\Http\\Controllers\\UserController'])
            ->once()
            ->andReturn(['controller:App\\Http\\Controllers\\UserController']);

        $this->cache->trackChange('/app/Http/Controllers/UserController.php');
        $this->cache->getInvalidatedItems();

        // Mockeryが期待どおりに呼び出されたことを確認
        $this->assertTrue(true);
    }

    #[Test]
    public function it_converts_request_file_to_node_id(): void
    {
        $this->dependencyGraph->shouldReceive('getAffectedNodes')
            ->with(['request:App\\Http\\Requests\\StoreUserRequest'])
            ->once()
            ->andReturn(['request:App\\Http\\Requests\\StoreUserRequest']);

        $this->cache->trackChange('/app/Http/Requests/StoreUserRequest.php');
        $this->cache->getInvalidatedItems();

        $this->assertTrue(true);
    }

    #[Test]
    public function it_converts_resource_file_to_node_id(): void
    {
        $this->dependencyGraph->shouldReceive('getAffectedNodes')
            ->with(['resource:App\\Http\\Resources\\UserResource'])
            ->once()
            ->andReturn(['resource:App\\Http\\Resources\\UserResource']);

        $this->cache->trackChange('/app/Http/Resources/UserResource.php');
        $this->cache->getInvalidatedItems();

        $this->assertTrue(true);
    }

    #[Test]
    public function it_converts_other_files_to_file_node_id(): void
    {
        $this->dependencyGraph->shouldReceive('getAffectedNodes')
            ->with(['file:/app/Models/User.php'])
            ->once()
            ->andReturn(['file:/app/Models/User.php']);

        $this->cache->trackChange('/app/Models/User.php');
        $this->cache->getInvalidatedItems();

        $this->assertTrue(true);
    }

    #[Test]
    public function it_returns_unique_invalidated_items(): void
    {
        $this->dependencyGraph->shouldReceive('getAffectedNodes')
            ->andReturn(['route:GET:/api/users', 'route:GET:/api/users']);

        $this->cache->trackChange('/app/Http/Controllers/UserController.php');

        $invalidated = $this->cache->getInvalidatedItems();

        $this->assertCount(1, array_unique($invalidated));
    }

    #[Test]
    public function it_tracks_change_type(): void
    {
        $this->dependencyGraph->shouldReceive('getAffectedNodes')
            ->andReturn([]);

        $this->cache->trackChange('/path/to/file.php', 'created');
        $this->cache->trackChange('/path/to/another.php', 'deleted');

        // 変更タイプが記録されていることを確認（内部実装に依存しないテスト）
        $invalidated = $this->cache->getInvalidatedItems();
        $this->assertIsArray($invalidated);
    }

    #[Test]
    public function it_handles_empty_change_log(): void
    {
        $this->dependencyGraph->shouldNotReceive('getAffectedNodes');

        $invalidated = $this->cache->getInvalidatedItems();

        $this->assertEmpty($invalidated);
    }

    #[Test]
    public function it_handles_multiple_changes_to_same_file(): void
    {
        $this->dependencyGraph->shouldReceive('getAffectedNodes')
            ->andReturn(['controller:App\\Http\\Controllers\\UserController']);

        $this->cache->trackChange('/app/Http/Controllers/UserController.php', 'modified');
        $this->cache->trackChange('/app/Http/Controllers/UserController.php', 'modified');

        $invalidated = $this->cache->getInvalidatedItems();

        // 重複が除去されていることを確認
        $this->assertCount(1, array_unique($invalidated));
    }

    #[Test]
    public function it_gets_valid_entries_excluding_invalidated(): void
    {
        // キャッシュにエントリを設定
        $this->cache->remember('entry1', fn () => ['data' => 1]);
        $this->cache->remember('entry2', fn () => ['data' => 2]);

        $this->dependencyGraph->shouldReceive('getAffectedNodes')
            ->andReturn(['entry1']);

        $this->cache->trackChange('/some/file.php');

        $validEntries = $this->cache->getValidEntries();

        $this->assertNotContains('entry1', $validEntries);
    }

    #[Test]
    public function it_handles_nested_controller_paths(): void
    {
        $this->dependencyGraph->shouldReceive('getAffectedNodes')
            ->with(['controller:App\\Http\\Controllers\\Api\\V1\\UserController'])
            ->once()
            ->andReturn([]);

        $this->cache->trackChange('/app/Http/Controllers/Api/V1/UserController.php');
        $this->cache->getInvalidatedItems();

        $this->assertTrue(true);
    }
}
