<?php

declare(strict_types=1);

namespace LaravelSpectrum\Cache;

use LaravelSpectrum\Performance\DependencyGraph;

class IncrementalCache extends DocumentationCache
{
    private DependencyGraph $dependencyGraph;

    /**
     * @var array<int, array{file: string, type: string, timestamp: float}>
     */
    private array $changeLog = [];

    public function __construct(DependencyGraph $dependencyGraph)
    {
        parent::__construct();
        $this->dependencyGraph = $dependencyGraph;
    }

    /**
     * Track file changes
     */
    public function trackChange(string $file, string $type = 'modified'): void
    {
        $this->changeLog[] = [
            'file' => $file,
            'type' => $type,
            'timestamp' => microtime(true),
        ];
    }

    /**
     * Get items that need regeneration based on changes
     *
     * @return array<int, string>
     */
    public function getInvalidatedItems(): array
    {
        /** @var array<int, string> $invalidated */
        $invalidated = [];

        foreach ($this->changeLog as $change) {
            $nodeId = $this->fileToNodeId($change['file']);
            $affected = $this->dependencyGraph->getAffectedNodes([$nodeId]);
            $invalidated = array_merge($invalidated, $affected);
        }

        return array_values(array_unique($invalidated));
    }

    /**
     * Invalidate only affected cache entries
     */
    public function invalidateAffected(): int
    {
        $invalidated = $this->getInvalidatedItems();
        $count = 0;

        foreach ($invalidated as $item) {
            if ($this->forget($item)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get cache entries that are still valid
     *
     * @return array<int, string>
     */
    public function getValidEntries(): array
    {
        $allKeys = $this->getAllCacheKeys();
        $invalidated = $this->getInvalidatedItems();

        return array_values(array_diff($allKeys, $invalidated));
    }

    private function fileToNodeId(string $file): string
    {
        // ファイルパスからノードIDを生成
        if (str_contains($file, 'Controllers')) {
            preg_match('/Controllers[\\/\\\\](.+)\.php$/', $file, $matches);

            return 'controller:App\\Http\\Controllers\\'.str_replace(['/', '\\'], '\\', $matches[1] ?? '');
        }

        if (str_contains($file, 'Requests')) {
            preg_match('/Requests[\\/\\\\](.+)\.php$/', $file, $matches);

            return 'request:App\\Http\\Requests\\'.str_replace(['/', '\\'], '\\', $matches[1] ?? '');
        }

        if (str_contains($file, 'Resources')) {
            preg_match('/Resources[\\/\\\\](.+)\.php$/', $file, $matches);

            return 'resource:App\\Http\\Resources\\'.str_replace(['/', '\\'], '\\', $matches[1] ?? '');
        }

        return 'file:'.$file;
    }
}
