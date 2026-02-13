<?php

namespace LaravelSpectrum\Tests\Unit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\File;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
    public function it_handles_corrupted_cache_gracefully()
    {
        $cacheDir = config('spectrum.cache.directory', storage_path('app/spectrum/cache'));
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

    #[Test]
    public function it_respects_cache_enabled_setting()
    {
        // キャッシュを無効化
        config(['spectrum.cache.enabled' => false]);
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

    #[Test]
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

    #[Test]
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

    #[Test]
    public function it_invalidates_route_cache_when_api_route_file_changes(): void
    {
        $routesDir = base_path('routes');
        $apiRouteFile = $routesDir.'/api.php';
        $webRouteFile = $routesDir.'/web.php';
        $routesDirExisted = File::isDirectory($routesDir);

        $originalApi = File::exists($apiRouteFile) ? File::get($apiRouteFile) : null;
        $originalWeb = File::exists($webRouteFile) ? File::get($webRouteFile) : null;

        File::ensureDirectoryExists($routesDir);
        File::put($apiRouteFile, '<?php // route cache api v1');
        File::put($webRouteFile, '<?php // route cache web v1');

        try {
            $callCount = 0;

            $this->cache->rememberRoutes(function () use (&$callCount) {
                $callCount++;

                return ['routes' => ['v1']];
            });

            $this->assertEquals(1, $callCount);

            sleep(1);
            File::put($apiRouteFile, '<?php // route cache api v2');

            $result = $this->cache->rememberRoutes(function () use (&$callCount) {
                $callCount++;

                return ['routes' => ['v2']];
            });

            $this->assertEquals(2, $callCount);
            $this->assertEquals(['routes' => ['v2']], $result);
        } finally {
            if ($originalApi === null) {
                File::delete($apiRouteFile);
            } else {
                File::put($apiRouteFile, $originalApi);
            }

            if ($originalWeb === null) {
                File::delete($webRouteFile);
            } else {
                File::put($webRouteFile, $originalWeb);
            }

            if (! $routesDirExisted && File::isDirectory($routesDir)) {
                File::deleteDirectory($routesDir);
            }
        }
    }

    #[Test]
    public function it_forgets_cache_entries()
    {
        // キャッシュにデータを保存
        $this->cache->remember('routes:all', fn () => ['route1', 'route2']);

        // キャッシュが存在することを確認
        $callCount = 0;
        $this->cache->remember('routes:all', function () use (&$callCount) {
            $callCount++;

            return ['route3'];
        });
        $this->assertEquals(0, $callCount); // キャッシュから取得

        // キャッシュを削除
        $result = $this->cache->forget('routes:all');
        $this->assertTrue($result);

        // キャッシュが削除されたことを確認
        $callCount = 0;
        $data = $this->cache->remember('routes:all', function () use (&$callCount) {
            $callCount++;

            return ['route3'];
        });
        $this->assertEquals(1, $callCount); // コールバックが実行される
        $this->assertEquals(['route3'], $data);
    }

    #[Test]
    public function it_returns_false_when_forgetting_non_existent_cache()
    {
        $result = $this->cache->forget('non_existent_key');
        $this->assertFalse($result);
    }

    #[Test]
    public function it_forgets_cache_by_pattern()
    {
        // 複数のリソースキャッシュを作成
        $this->cache->remember('resource:UserResource', fn () => ['user']);
        $this->cache->remember('resource:PostResource', fn () => ['post']);
        $this->cache->remember('resource:CommentResource', fn () => ['comment']);
        $this->cache->remember('form_request:UserRequest', fn () => ['request']);

        // パターンマッチでresource:*を削除
        $count = $this->cache->forgetByPattern('resource:');
        $this->assertEquals(3, $count);

        // リソースキャッシュが削除されたことを確認
        $callCount = 0;
        $this->cache->remember('resource:UserResource', function () use (&$callCount) {
            $callCount++;

            return ['new_user'];
        });
        $this->assertEquals(1, $callCount);

        // フォームリクエストキャッシュは残っていることを確認
        $callCount = 0;
        $this->cache->remember('form_request:UserRequest', function () use (&$callCount) {
            $callCount++;

            return ['new_request'];
        });
        $this->assertEquals(0, $callCount); // キャッシュから取得
    }

    #[Test]
    public function it_invalidates_cache_with_different_version()
    {
        $cacheDir = config('spectrum.cache.directory', storage_path('app/spectrum/cache'));
        $cacheFile = $cacheDir.'/'.sha1('version_test_key').'.cache';

        // 古いバージョンのキャッシュを手動で作成（バージョン0）
        File::ensureDirectoryExists($cacheDir);
        $oldCacheData = [
            'version' => 0, // 古いバージョン
            'metadata' => [
                'key' => 'version_test_key',
                'created_at' => now()->toIso8601String(),
                'dependencies' => [],
            ],
            'data' => ['old' => 'data'],
        ];
        File::put($cacheFile, serialize($oldCacheData));

        $callCount = 0;

        // 古いバージョンのキャッシュは無視され、コールバックが実行される
        $result = $this->cache->remember('version_test_key', function () use (&$callCount) {
            $callCount++;

            return ['new' => 'data'];
        });

        $this->assertEquals(['new' => 'data'], $result);
        $this->assertEquals(1, $callCount);

        // 新しいキャッシュには正しいバージョンが含まれることを確認
        $newCacheData = unserialize(File::get($cacheFile));
        $this->assertArrayHasKey('version', $newCacheData);
        $this->assertGreaterThanOrEqual(1, $newCacheData['version']);
    }

    #[Test]
    public function it_invalidates_cache_without_version()
    {
        $cacheDir = config('spectrum.cache.directory', storage_path('app/spectrum/cache'));
        $cacheFile = $cacheDir.'/'.sha1('no_version_key').'.cache';

        // バージョンなしのキャッシュを手動で作成（以前の形式）
        File::ensureDirectoryExists($cacheDir);
        $oldCacheData = [
            // 'version' なし（以前の形式）
            'metadata' => [
                'key' => 'no_version_key',
                'created_at' => now()->toIso8601String(),
                'dependencies' => [],
            ],
            'data' => ['legacy' => 'data'],
        ];
        File::put($cacheFile, serialize($oldCacheData));

        $callCount = 0;

        // バージョンなしのキャッシュは無視され、コールバックが実行される
        $result = $this->cache->remember('no_version_key', function () use (&$callCount) {
            $callCount++;

            return ['new' => 'data'];
        });

        $this->assertEquals(['new' => 'data'], $result);
        $this->assertEquals(1, $callCount);

        // 新しいキャッシュには正しいバージョンが含まれることを確認
        $newCacheData = unserialize(File::get($cacheFile));
        $this->assertArrayHasKey('version', $newCacheData);
        $this->assertGreaterThanOrEqual(1, $newCacheData['version']);
    }

    #[Test]
    public function it_includes_cache_version_in_stats()
    {
        $stats = $this->cache->getStats();

        $this->assertArrayHasKey('cache_version', $stats);
        $this->assertIsInt($stats['cache_version']);
        $this->assertGreaterThanOrEqual(1, $stats['cache_version']);
    }

    #[Test]
    public function it_preserves_cache_with_correct_version()
    {
        // キャッシュにデータを保存
        $this->cache->remember('version_correct_key', fn () => ['data' => 'original']);

        $callCount = 0;

        // 同じバージョンのキャッシュは有効
        $result = $this->cache->remember('version_correct_key', function () use (&$callCount) {
            $callCount++;

            return ['data' => 'new'];
        });

        $this->assertEquals(['data' => 'original'], $result);
        $this->assertEquals(0, $callCount); // コールバックは呼ばれない
    }

    #[Test]
    public function it_gets_all_cache_keys()
    {
        // 複数のキャッシュエントリを作成
        $this->cache->remember('key_one', fn () => 'data1');
        $this->cache->remember('key_two', fn () => 'data2');
        $this->cache->remember('key_three', fn () => 'data3');

        $keys = $this->cache->getAllCacheKeys();

        $this->assertCount(3, $keys);
        $this->assertContains('key_one', $keys);
        $this->assertContains('key_two', $keys);
        $this->assertContains('key_three', $keys);
    }

    #[Test]
    public function it_returns_empty_array_when_no_cache_keys_exist()
    {
        $this->cache->clear();

        $keys = $this->cache->getAllCacheKeys();

        $this->assertIsArray($keys);
        $this->assertEmpty($keys);
    }

    #[Test]
    public function it_can_check_if_cache_is_enabled()
    {
        $this->assertTrue($this->cache->isEnabled());
    }

    #[Test]
    public function it_can_disable_and_enable_cache()
    {
        // 初期状態は有効
        $this->assertTrue($this->cache->isEnabled());

        // 無効化
        $this->cache->disable();
        $this->assertFalse($this->cache->isEnabled());

        // 無効化中はキャッシュされない
        $callCount = 0;
        $this->cache->remember('disabled_test', function () use (&$callCount) {
            $callCount++;

            return 'data';
        });
        $this->cache->remember('disabled_test', function () use (&$callCount) {
            $callCount++;

            return 'data';
        });
        $this->assertEquals(2, $callCount); // 毎回コールバックが実行される

        // 有効化
        $this->cache->enable();
        $this->assertTrue($this->cache->isEnabled());
    }

    #[Test]
    public function it_returns_stats_with_empty_cache_directory()
    {
        // キャッシュディレクトリを削除
        $cacheDir = config('spectrum.cache.directory', storage_path('app/spectrum/cache'));
        if (File::isDirectory($cacheDir)) {
            File::deleteDirectory($cacheDir);
        }

        $stats = $this->cache->getStats();

        $this->assertTrue($stats['enabled']);
        $this->assertEquals(0, $stats['total_files']);
        $this->assertEquals(0, $stats['total_size']);
        $this->assertStringContainsString('B', $stats['total_size_human']);
        $this->assertNull($stats['oldest_file']);
        $this->assertNull($stats['newest_file']);
        $this->assertArrayHasKey('cache_version', $stats);
    }

    #[Test]
    public function it_returns_zero_when_forgetting_by_pattern_with_empty_directory()
    {
        // キャッシュディレクトリを削除
        $cacheDir = config('spectrum.cache.directory', storage_path('app/spectrum/cache'));
        if (File::isDirectory($cacheDir)) {
            File::deleteDirectory($cacheDir);
        }

        $count = $this->cache->forgetByPattern('resource:');

        $this->assertEquals(0, $count);
    }

    #[Test]
    public function it_returns_false_when_forget_is_called_while_disabled()
    {
        // キャッシュを作成
        $this->cache->remember('test_key', fn () => 'data');

        // 無効化
        $this->cache->disable();

        // 無効化中はforgetがfalseを返す
        $result = $this->cache->forget('test_key');
        $this->assertFalse($result);

        // 有効化して再度テスト
        $this->cache->enable();
        $result = $this->cache->forget('test_key');
        $this->assertTrue($result);
    }

    #[Test]
    public function it_handles_corrupted_cache_in_get_all_cache_keys()
    {
        $cacheDir = config('spectrum.cache.directory', storage_path('app/spectrum/cache'));
        File::ensureDirectoryExists($cacheDir);

        // 正常なキャッシュを作成
        $this->cache->remember('valid_key', fn () => 'valid_data');

        // 破損したキャッシュファイルを作成
        File::put($cacheDir.'/corrupted.cache', 'invalid serialized data');

        $keys = $this->cache->getAllCacheKeys();

        // 正常なキーのみ取得される
        $this->assertContains('valid_key', $keys);
    }

    #[Test]
    public function it_handles_resource_without_dependencies()
    {
        // 依存関係のないResourceファイルを作成
        $resourceFile = storage_path('SimpleResource.php');
        File::put($resourceFile, '<?php
namespace App\Http\Resources;

class SimpleResource {
    public function toArray($request) {
        return ["id" => $this->id];
    }
}');

        $callCount = 0;

        // 依存関係がなくてもキャッシュは正常に動作する
        $result = $this->cache->rememberResource('App\\Http\\Resources\\SimpleResource', function () use (&$callCount) {
            $callCount++;

            return ['analyzed' => true];
        });

        $this->assertEquals(['analyzed' => true], $result);
        $this->assertEquals(1, $callCount);

        File::delete($resourceFile);
    }

    #[Test]
    public function it_handles_non_existent_class_in_remember_form_request()
    {
        $callCount = 0;

        // 存在しないクラスの場合はコールバックを直接実行
        $result = $this->cache->rememberFormRequest('NonExistent\\FormRequest', function () use (&$callCount) {
            $callCount++;

            return ['fallback' => true];
        });

        $this->assertEquals(['fallback' => true], $result);
        $this->assertEquals(1, $callCount);
    }

    #[Test]
    public function it_handles_non_existent_class_in_remember_resource()
    {
        $callCount = 0;

        // 存在しないクラスの場合はコールバックを直接実行
        $result = $this->cache->rememberResource('NonExistent\\Resource', function () use (&$callCount) {
            $callCount++;

            return ['fallback' => true];
        });

        $this->assertEquals(['fallback' => true], $result);
        $this->assertEquals(1, $callCount);
    }

    #[Test]
    public function it_handles_deleted_dependency_file()
    {
        $tempFile = storage_path('temp_dependency.php');
        File::put($tempFile, '<?php // version 1');

        $callCount = 0;

        // 初回実行
        $this->cache->remember('dep_test_key', function () use (&$callCount) {
            $callCount++;

            return ['version' => 1];
        }, [$tempFile]);

        $this->assertEquals(1, $callCount);

        // ファイルを削除
        File::delete($tempFile);

        // 依存ファイルが削除されたらキャッシュは無効化される
        $result = $this->cache->remember('dep_test_key', function () use (&$callCount) {
            $callCount++;

            return ['version' => 2];
        }, [$tempFile]);

        $this->assertEquals(2, $callCount);
        $this->assertEquals(['version' => 2], $result);
    }

    #[Test]
    public function it_creates_cache_when_disabled_at_construction()
    {
        // キャッシュを無効化して新しいインスタンスを作成
        config(['spectrum.cache.enabled' => false]);
        $disabledCache = new DocumentationCache;

        $this->assertFalse($disabledCache->isEnabled());

        // キャッシュ無効時もコールバックは正常に実行される
        $result = $disabledCache->remember('test', fn () => 'data');
        $this->assertEquals('data', $result);
    }

    #[Test]
    public function it_handles_exception_in_get_method()
    {
        $cacheDir = config('spectrum.cache.directory', storage_path('app/spectrum/cache'));
        $cacheFile = $cacheDir.'/'.sha1('exception_test_key').'.cache';

        // 不正なシリアライズデータを持つキャッシュファイルを作成
        File::ensureDirectoryExists($cacheDir);
        File::put($cacheFile, 'not a valid serialized data');

        $callCount = 0;

        // 破損したキャッシュは無視されコールバックが実行される
        $result = $this->cache->remember('exception_test_key', function () use (&$callCount) {
            $callCount++;

            return ['fresh' => 'data'];
        });

        $this->assertEquals(['fresh' => 'data'], $result);
        $this->assertEquals(1, $callCount);
    }

    #[Test]
    public function it_handles_exception_in_get_metadata_method()
    {
        $cacheDir = config('spectrum.cache.directory', storage_path('app/spectrum/cache'));
        $tempFile = storage_path('metadata_test_dep.php');

        // 依存ファイルを作成
        File::put($tempFile, '<?php // test');

        // まず正常なキャッシュを作成
        $this->cache->remember('metadata_exception_key', fn () => 'data', [$tempFile]);

        // キャッシュファイルを破損させる
        $cacheFile = $cacheDir.'/'.sha1('metadata_exception_key').'.cache';
        File::put($cacheFile, 'corrupted serialized data');

        $callCount = 0;

        // 破損したキャッシュはスキップされコールバックが実行される
        $result = $this->cache->remember('metadata_exception_key', function () use (&$callCount) {
            $callCount++;

            return ['regenerated' => true];
        }, [$tempFile]);

        $this->assertEquals(['regenerated' => true], $result);
        $this->assertEquals(1, $callCount);

        File::delete($tempFile);
    }

    #[Test]
    public function it_handles_forget_by_pattern_with_corrupted_cache()
    {
        $cacheDir = config('spectrum.cache.directory', storage_path('app/spectrum/cache'));
        File::ensureDirectoryExists($cacheDir);

        // 正常なキャッシュを作成
        $this->cache->remember('pattern:valid', fn () => 'valid');

        // 破損したキャッシュファイルを作成
        File::put($cacheDir.'/corrupted_pattern.cache', 'invalid data');

        // パターンマッチで削除を実行（破損ファイルも削除される）
        $count = $this->cache->forgetByPattern('pattern:');

        $this->assertEquals(1, $count);
    }

    #[Test]
    public function it_puts_dependencies_with_existing_files()
    {
        $tempFile = storage_path('put_test_dep.php');
        File::put($tempFile, '<?php // dependency file');

        $callCount = 0;

        // 依存ファイルとともにキャッシュを保存
        $result = $this->cache->remember('put_test_key', function () use (&$callCount) {
            $callCount++;

            return ['data' => 'with_dependency'];
        }, [$tempFile]);

        $this->assertEquals(['data' => 'with_dependency'], $result);
        $this->assertEquals(1, $callCount);

        // キャッシュが正しく保存されていることを確認（2回目はキャッシュから）
        $result2 = $this->cache->remember('put_test_key', function () use (&$callCount) {
            $callCount++;

            return ['data' => 'should_not_run'];
        }, [$tempFile]);

        $this->assertEquals(['data' => 'with_dependency'], $result2);
        $this->assertEquals(1, $callCount); // コールバックは実行されない

        File::delete($tempFile);
    }

    #[Test]
    public function it_puts_dependencies_with_non_existing_files()
    {
        $callCount = 0;

        // 存在しないファイルを依存関係として指定
        $result = $this->cache->remember('put_nonexistent_key', function () use (&$callCount) {
            $callCount++;

            return ['data' => 'no_dependency'];
        }, ['/nonexistent/file.php']);

        $this->assertEquals(['data' => 'no_dependency'], $result);
        $this->assertEquals(1, $callCount);
    }

    #[Test]
    public function it_handles_human_filesize_with_various_sizes()
    {
        // キャッシュに大きなデータを保存してhuman filesizeをテスト
        $largeData = str_repeat('x', 10000); // 約10KB
        $this->cache->remember('large_data_key', fn () => $largeData);

        $stats = $this->cache->getStats();

        // サイズがKB単位で表示されることを確認
        $this->assertGreaterThan(0, $stats['total_size']);
        $this->assertStringContainsString('KB', $stats['total_size_human']);
    }

    #[Test]
    public function it_handles_cache_key_without_metadata_key()
    {
        $cacheDir = config('spectrum.cache.directory', storage_path('app/spectrum/cache'));
        File::ensureDirectoryExists($cacheDir);

        // metadataにkeyがないキャッシュファイルを作成
        $cacheData = [
            'version' => 1,
            'metadata' => [
                // 'key' is missing
                'created_at' => now()->toIso8601String(),
                'dependencies' => [],
            ],
            'data' => 'test',
        ];
        File::put($cacheDir.'/no_key.cache', serialize($cacheData));

        $keys = $this->cache->getAllCacheKeys();

        // keyがないエントリは含まれない
        $this->assertNotContains(null, $keys);
    }
}
