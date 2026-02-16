<?php

declare(strict_types=1);

namespace LaravelSpectrum\Services;

use Symfony\Component\Finder\Finder;
use Workerman\Timer;

/**
 * @phpstan-type FileHashes array<string, string>
 * @phpstan-type WatchPaths array<int, string>
 */
class FileWatcher
{
    /** @var FileHashes */
    private array $fileHashes = [];

    private float $pollInterval;

    public function __construct(float $pollInterval = 0.5)
    {
        $this->pollInterval = $pollInterval;
    }

    /**
     * @param  WatchPaths  $paths
     * @param  callable(string, string):void  $callback
     */
    public function watch(array $paths, callable $callback): void
    {
        // Initialize file hashes
        $this->initializeFileHashes($paths);

        // Set up polling timer using Workerman
        Timer::add($this->pollInterval, function () use ($paths, $callback) {
            $this->checkForChanges($paths, $callback);
        });
    }

    /**
     * @param  WatchPaths  $paths
     * @param  callable(string, string):void  $callback
     */
    private function checkForChanges(array $paths, callable $callback): void
    {
        $currentHashes = $this->getCurrentFileHashes($paths);

        // Check for new files
        foreach ($currentHashes as $file => $hash) {
            if (! isset($this->fileHashes[$file])) {
                $callback($file, 'created');
                $this->fileHashes[$file] = $hash;
            }
        }

        // Check for modified files
        foreach ($this->fileHashes as $file => $oldHash) {
            if (isset($currentHashes[$file]) && $currentHashes[$file] !== $oldHash) {
                $callback($file, 'modified');
                $this->fileHashes[$file] = $currentHashes[$file];
            }
        }

        // Check for deleted files
        foreach ($this->fileHashes as $file => $hash) {
            if (! isset($currentHashes[$file])) {
                $callback($file, 'deleted');
                unset($this->fileHashes[$file]);
            }
        }
    }

    /**
     * @param  WatchPaths  $paths
     * @return FileHashes
     */
    private function getCurrentFileHashes(array $paths): array
    {
        $hashes = [];

        foreach ($paths as $path) {
            if (is_file($path)) {
                $realPath = realpath($path);
                if ($realPath !== false) {
                    $hashes[$realPath] = $this->hashFile($path);
                }
            } elseif (is_dir($path)) {
                $finder = new Finder;
                $finder->files()
                    ->in($path)
                    ->name('*.php')
                    ->notPath('vendor')
                    ->notPath('node_modules');

                foreach ($finder as $file) {
                    $realPath = $file->getRealPath();
                    if ($realPath !== false) {
                        $hashes[$realPath] = $this->hashFile($realPath);
                    }
                }
            }
        }

        return $hashes;
    }

    private function hashFile(string $path): string
    {
        if (! file_exists($path)) {
            return '';
        }

        return md5_file($path).':'.filemtime($path);
    }

    /**
     * @param  WatchPaths  $paths
     */
    private function initializeFileHashes(array $paths): void
    {
        $this->fileHashes = $this->getCurrentFileHashes($paths);
    }
}
