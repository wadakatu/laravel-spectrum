<?php

namespace LaravelPrism\Tests\Unit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\File;
use LaravelPrism\Cache\DocumentationCache;
use LaravelPrism\Tests\TestCase;

class DocumentationCacheTest extends TestCase
{
    private DocumentationCache $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = new DocumentationCache;
        $this->cache->clear();
    }

    protected function tearDown(): void
    {
        $this->cache->clear();
        parent::tearDown();
    }

    /** @test */
    public function it_caches_analysis_results()
    {
        $callCount = 0;

        $result = $this->cache->remember('test_key', function () use (&$callCount) {
            $callCount++;

            return ['data' => 'test'];
        });

        $this->assertEquals(['data' => 'test'], $result);
        $this->assertEquals(1, $callCount);

        // 2回目の呼び出しはキャッシュから取得
        $result2 = $this->cache->remember('test_key', function () use (&$callCount) {
            $callCount++;

            return ['data' => 'test'];
        });

        $this->assertEquals(['data' => 'test'], $result2);
        $this->assertEquals(1, $callCount); // コールバックは呼ばれない
    }

    /** @test */
    public function it_invalidates_cache_when_dependencies_change()
    {
        $tempFile = storage_path('test_dependency.php');
        File::put($tempFile, '<?php // version 1');

        $callCount = 0;

        // 初回実行
        $this->cache->remember('test_key', function () use (&$callCount) {
            $callCount++;

            return ['version' => 1];
        }, [$tempFile]);

        $this->assertEquals(1, $callCount);

        // ファイルを変更
        sleep(1); // タイムスタンプが確実に変わるように
        File::put($tempFile, '<?php // version 2');

        // キャッシュが無効化される
        $result = $this->cache->remember('test_key', function () use (&$callCount) {
            $callCount++;

            return ['version' => 2];
        }, [$tempFile]);

        $this->assertEquals(2, $callCount);
        $this->assertEquals(['version' => 2], $result);

        File::delete($tempFile);
    }

    /** @test */
    public function it_caches_form_request_analysis()
    {
        $testRequest = new class extends FormRequest
        {
            public function rules(): array
            {
                return ['name' => 'required|string'];
            }
        };

        $callCount = 0;

        $result = $this->cache->rememberFormRequest(get_class($testRequest), function () use (&$callCount) {
            $callCount++;

            return ['analyzed' => true];
        });

        $this->assertEquals(['analyzed' => true], $result);
        $this->assertEquals(1, $callCount);

        // 2回目はキャッシュから
        $result2 = $this->cache->rememberFormRequest(get_class($testRequest), function () use (&$callCount) {
            $callCount++;

            return ['analyzed' => true];
        });

        $this->assertEquals(1, $callCount);
    }

    /** @test */
    public function it_provides_cache_statistics()
    {
        // いくつかのアイテムをキャッシュ
        $this->cache->remember('key1', fn () => 'data1');
        $this->cache->remember('key2', fn () => 'data2');
        $this->cache->remember('key3', fn () => 'data3');

        $stats = $this->cache->getStats();

        $this->assertEquals(3, $stats['total_files']);
        $this->assertGreaterThan(0, $stats['total_size']);
        $this->assertNotNull($stats['oldest_file']);
        $this->assertNotNull($stats['newest_file']);
    }

    /** @test */
    public function it_handles_corrupted_cache_gracefully()
    {
        $cacheDir = config('prism.cache.directory', storage_path('app/prism/cache'));
        $cacheFile = $cacheDir.'/'.sha1('corrupt_key').'.cache';

        // 破損したキャッシュファイルを作成
        File::ensureDirectoryExists($cacheDir);
        File::put($cacheFile, 'corrupted data');

        $callCount = 0;

        // 破損したキャッシュは無視して再生成
        $result = $this->cache->remember('corrupt_key', function () use (&$callCount) {
            $callCount++;

            return ['fresh' => 'data'];
        });

        $this->assertEquals(['fresh' => 'data'], $result);
        $this->assertEquals(1, $callCount);
    }

    /** @test */
    public function it_respects_cache_enabled_setting()
    {
        // キャッシュを無効化
        config(['prism.cache.enabled' => false]);
        $cache = new DocumentationCache;

        $callCount = 0;

        // キャッシュが無効の場合、毎回コールバックが実行される
        $cache->remember('test_key', function () use (&$callCount) {
            $callCount++;

            return ['data' => 'test'];
        });

        $cache->remember('test_key', function () use (&$callCount) {
            $callCount++;

            return ['data' => 'test'];
        });

        $this->assertEquals(2, $callCount);
    }

    /** @test */
    public function it_detects_resource_dependencies()
    {
        $resourceFile = storage_path('TestResource.php');
        $dependencyFile = storage_path('DependencyResource.php');

        // テスト用のResourceファイルを作成
        File::put($resourceFile, '<?php
namespace App\Http\Resources;

class TestResource {
    public function toArray($request) {
        return [
            "id" => $this->id,
            "dependency" => new DependencyResource($this->dependency)
        ];
    }
}');

        File::put($dependencyFile, '<?php
namespace App\Http\Resources;

class DependencyResource {
    public function toArray($request) {
        return ["id" => $this->id];
    }
}');

        $callCount = 0;

        // 初回実行
        $this->cache->rememberResource('App\\Http\\Resources\\TestResource', function () use (&$callCount) {
            $callCount++;

            return ['analyzed' => true];
        });

        $this->assertEquals(1, $callCount);

        // 依存するResourceを変更
        sleep(1);
        File::put($dependencyFile, '<?php
namespace App\Http\Resources;

class DependencyResource {
    public function toArray($request) {
        return ["id" => $this->id, "name" => $this->name];
    }
}');

        // キャッシュが無効化される
        $this->cache->rememberResource('App\\Http\\Resources\\TestResource', function () use (&$callCount) {
            $callCount++;

            return ['analyzed' => true, 'version' => 2];
        });

        $this->assertEquals(2, $callCount);

        File::delete($resourceFile);
        File::delete($dependencyFile);
    }

    /** @test */
    public function it_caches_route_analysis()
    {
        $callCount = 0;

        $result = $this->cache->rememberRoutes(function () use (&$callCount) {
            $callCount++;

            return ['routes' => ['GET /api/test']];
        });

        $this->assertEquals(['routes' => ['GET /api/test']], $result);
        $this->assertEquals(1, $callCount);

        // 2回目はキャッシュから
        $result2 = $this->cache->rememberRoutes(function () use (&$callCount) {
            $callCount++;

            return ['routes' => ['GET /api/test']];
        });

        $this->assertEquals(1, $callCount);
    }
}
