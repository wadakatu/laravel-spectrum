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
            ->twice()
            ->andReturn(['tracked:item']);

        $this->cache->trackChange('/path/to/file.php', 'modified');
        $this->cache->trackChange('/path/to/another.php', 'created');

        $invalidated = $this->cache->getInvalidatedItems();

        $this->assertIsArray($invalidated);
        $this->assertNotEmpty($invalidated);
        $this->assertContains('tracked:item', $invalidated);
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
        $cacheKey = 'controller:App\\Http\\Controllers\\UserController';
        $unchangedKey = 'route:GET:/api/users';

        // まずキャッシュにデータを設定
        $this->cache->remember($cacheKey, fn () => ['data' => 'test']);
        $this->cache->remember($unchangedKey, fn () => ['route' => 'data']);

        // キャッシュにデータがあることを確認
        $allKeys = $this->cache->getAllCacheKeys();
        $this->assertContains($cacheKey, $allKeys);
        $this->assertContains($unchangedKey, $allKeys);

        $this->dependencyGraph->shouldReceive('getAffectedNodes')
            ->andReturn([$cacheKey]);

        $this->cache->trackChange('/app/Http/Controllers/UserController.php');

        $count = $this->cache->invalidateAffected();

        // 1件が無効化されたことを確認
        $this->assertEquals(1, $count);

        // 無効化されたエントリがキャッシュから削除されていることを確認
        $remainingKeys = $this->cache->getAllCacheKeys();
        $this->assertNotContains($cacheKey, $remainingKeys);

        // 無効化されなかったエントリは残っていることを確認
        $this->assertContains($unchangedKey, $remainingKeys);
    }

    #[Test]
    public function it_converts_controller_file_to_node_id(): void
    {
        $this->dependencyGraph->shouldReceive('getAffectedNodes')
            ->with(['controller:App\\Http\\Controllers\\UserController'])
            ->once()
            ->andReturn(['controller:App\\Http\\Controllers\\UserController']);

        $this->cache->trackChange('/app/Http/Controllers/UserController.php');
        $invalidated = $this->cache->getInvalidatedItems();

        $this->assertContains('controller:App\\Http\\Controllers\\UserController', $invalidated);
    }

    #[Test]
    public function it_converts_request_file_to_node_id(): void
    {
        $this->dependencyGraph->shouldReceive('getAffectedNodes')
            ->with(['request:App\\Http\\Requests\\StoreUserRequest'])
            ->once()
            ->andReturn(['request:App\\Http\\Requests\\StoreUserRequest']);

        $this->cache->trackChange('/app/Http/Requests/StoreUserRequest.php');
        $invalidated = $this->cache->getInvalidatedItems();

        $this->assertContains('request:App\\Http\\Requests\\StoreUserRequest', $invalidated);
    }

    #[Test]
    public function it_converts_resource_file_to_node_id(): void
    {
        $this->dependencyGraph->shouldReceive('getAffectedNodes')
            ->with(['resource:App\\Http\\Resources\\UserResource'])
            ->once()
            ->andReturn(['resource:App\\Http\\Resources\\UserResource']);

        $this->cache->trackChange('/app/Http/Resources/UserResource.php');
        $invalidated = $this->cache->getInvalidatedItems();

        $this->assertContains('resource:App\\Http\\Resources\\UserResource', $invalidated);
    }

    #[Test]
    public function it_converts_other_files_to_file_node_id(): void
    {
        $this->dependencyGraph->shouldReceive('getAffectedNodes')
            ->with(['file:/app/Models/User.php'])
            ->once()
            ->andReturn(['file:/app/Models/User.php']);

        $this->cache->trackChange('/app/Models/User.php');
        $invalidated = $this->cache->getInvalidatedItems();

        $this->assertContains('file:/app/Models/User.php', $invalidated);
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
            ->twice()
            ->andReturn(['affected:item']);

        $this->cache->trackChange('/path/to/file.php', 'created');
        $this->cache->trackChange('/path/to/another.php', 'deleted');

        $invalidated = $this->cache->getInvalidatedItems();

        // 変更タイプに関わらず、変更が追跡されていることを確認
        $this->assertIsArray($invalidated);
        $this->assertNotEmpty($invalidated);
        $this->assertContains('affected:item', $invalidated);
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

        // 無効化されたエントリは含まれないことを確認
        $this->assertNotContains('entry1', $validEntries);

        // 無効化されなかったエントリは含まれていることを確認
        $this->assertContains('entry2', $validEntries);
    }

    #[Test]
    public function it_handles_nested_controller_paths(): void
    {
        $expectedNodeId = 'controller:App\\Http\\Controllers\\Api\\V1\\UserController';

        $this->dependencyGraph->shouldReceive('getAffectedNodes')
            ->with([$expectedNodeId])
            ->once()
            ->andReturn([$expectedNodeId]);

        $this->cache->trackChange('/app/Http/Controllers/Api/V1/UserController.php');
        $invalidated = $this->cache->getInvalidatedItems();

        $this->assertContains($expectedNodeId, $invalidated);
    }
}
