<?php

namespace LaravelSpectrum\Performance;

use Generator;

class ChunkProcessor
{
    private int $chunkSize;

    private MemoryManager $memoryManager;

    public function __construct(int $chunkSize = 100, ?MemoryManager $memoryManager = null)
    {
        $this->chunkSize = $chunkSize;
        $this->memoryManager = $memoryManager ?? new MemoryManager;
    }

    /**
     * Process routes in chunks to minimize memory usage
     */
    public function processInChunks(array $routes, callable $processor): Generator
    {
        $chunks = array_chunk($routes, $this->chunkSize);
        $totalChunks = count($chunks);
        $processedChunks = 0;

        foreach ($chunks as $chunk) {
            // メモリ使用量をチェック
            $this->memoryManager->checkMemoryUsage();

            // チャンクを処理
            $result = $processor($chunk);

            $processedChunks++;

            yield [
                'result' => $result,
                'progress' => [
                    'current' => $processedChunks,
                    'total' => $totalChunks,
                    'percentage' => round(($processedChunks / $totalChunks) * 100, 2),
                ],
            ];

            // ガベージコレクションを強制実行
            if ($processedChunks % 10 === 0) {
                $this->memoryManager->runGarbageCollection();
            }
        }
    }

    /**
     * Stream process results directly to file
     */
    public function streamToFile(string $filePath, Generator $generator): void
    {
        $handle = fopen($filePath, 'w');
        fwrite($handle, "{\n");

        $first = true;
        foreach ($generator as $data) {
            if (! $first) {
                fwrite($handle, ",\n");
            }

            fwrite($handle, json_encode($data['result'], JSON_PRETTY_PRINT));
            $first = false;
        }

        fwrite($handle, "\n}");
        fclose($handle);
    }

    /**
     * Calculate optimal chunk size based on available memory
     */
    public function calculateOptimalChunkSize(int $totalItems): int
    {
        $availableMemory = $this->memoryManager->getAvailableMemory();
        $estimatedMemoryPerItem = 50 * 1024; // 50KB per item (estimated)

        $maxItemsInMemory = floor($availableMemory / $estimatedMemoryPerItem);

        // チャンクサイズを10から1000の間に制限
        return max(10, min(1000, (int) ($maxItemsInMemory * 0.5)));
    }
}
