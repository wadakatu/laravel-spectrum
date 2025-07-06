<?php

namespace LaravelPrism\Services;

use React\EventLoop\Loop;
use Symfony\Component\Finder\Finder;

class FileWatcher
{
    private array $fileHashes = [];

    private float $pollInterval;

    public function __construct(float $pollInterval = 0.5)
    {
        $this->pollInterval = $pollInterval;
    }

    public function watch(array $paths, callable $callback): void
    {
        // Initialize file hashes
        $this->initializeFileHashes($paths);

        // Set up polling timer
        Loop::addPeriodicTimer($this->pollInterval, function () use ($paths, $callback) {
            $this->checkForChanges($paths, $callback);
        });
    }

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

    private function getCurrentFileHashes(array $paths): array
    {
        $hashes = [];

        foreach ($paths as $path) {
            if (is_file($path)) {
                $hashes[realpath($path)] = $this->hashFile($path);
            } elseif (is_dir($path)) {
                $finder = new Finder;
                $finder->files()
                    ->in($path)
                    ->name('*.php')
                    ->notPath('vendor')
                    ->notPath('node_modules');

                foreach ($finder as $file) {
                    $hashes[$file->getRealPath()] = $this->hashFile($file->getRealPath());
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

    private function initializeFileHashes(array $paths): void
    {
        $this->fileHashes = $this->getCurrentFileHashes($paths);
    }
}
