<?php

namespace LaravelPrism\Console;

use Illuminate\Console\Command;
use LaravelPrism\Cache\DocumentationCache;

class CacheCommand extends Command
{
    protected $signature = 'prism:cache
                            {action : Action to perform (clear|stats|warm)}';

    protected $description = 'Manage Laravel Prism cache';

    private DocumentationCache $cache;

    public function __construct(DocumentationCache $cache)
    {
        parent::__construct();
        $this->cache = $cache;
    }

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'clear' => $this->clear(),
            'stats' => $this->stats(),
            'warm' => $this->warm(),
            default => $this->showInvalidActionError(),
        };
    }

    private function showInvalidActionError(): int
    {
        $this->error('Invalid action. Use: clear, stats, or warm');

        return 1;
    }

    private function clear(): int
    {
        $this->info('🧹 Clearing cache...');
        $this->cache->clear();
        $this->info('✅ Cache cleared successfully');

        return 0;
    }

    private function stats(): int
    {
        $stats = $this->cache->getStats();

        $this->info('📊 Cache Statistics');
        $this->info('==================');
        $this->info('Status: '.($stats['enabled'] ? 'Enabled' : 'Disabled'));
        $this->info("Files: {$stats['total_files']}");
        $this->info("Size: {$stats['total_size_human']}");

        if ($stats['oldest_file']) {
            $this->info("Oldest: {$stats['oldest_file']}");
            $this->info("Newest: {$stats['newest_file']}");
        }

        return 0;
    }

    private function warm(): int
    {
        $this->info('🔥 Warming cache...');

        // キャッシュをクリアしてから再生成
        $this->cache->clear();

        $this->call('prism:generate', [
            '--quiet' => true,
        ]);

        $stats = $this->cache->getStats();
        $this->info("✅ Cache warmed: {$stats['total_files']} files cached");

        return 0;
    }
}
