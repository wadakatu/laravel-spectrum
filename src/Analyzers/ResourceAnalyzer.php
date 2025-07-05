<?php

namespace LaravelPrism\Analyzers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

class ResourceAnalyzer
{
    /**
     * Resourceクラスを解析してレスポンス構造を抽出
     */
    public function analyze(string $resourceClass): array
    {
        if (! class_exists($resourceClass)) {
            return [];
        }

        $reflection = new ReflectionClass($resourceClass);

        // JsonResourceを継承していない場合はスキップ
        if (! $reflection->isSubclassOf(JsonResource::class)) {
            return [];
        }

        // toArray()メソッドのソースコードを解析
        if (! $reflection->hasMethod('toArray')) {
            return [];
        }

        $method = $reflection->getMethod('toArray');
        $source = $this->getMethodSource($method);

        // 簡易的なパース（MVP版）
        return $this->parseResourceStructure($source);
    }

    /**
     * メソッドのソースコードを取得
     */
    protected function getMethodSource(ReflectionMethod $method): string
    {
        $filename  = $method->getFileName();
        $startLine = $method->getStartLine() - 1;
        $endLine   = $method->getEndLine();
        $length    = $endLine - $startLine;

        $source = file($filename);

        return implode('', array_slice($source, $startLine, $length));
    }

    /**
     * リソース構造を解析（簡易版）
     */
    protected function parseResourceStructure(string $source): array
    {
        $properties = [];

        // 配列のキーを抽出する正規表現
        preg_match_all('/[\'"](\w+)[\'\"]\s*=>\s*(.+?)[,\]]/s', $source, $matches);

        foreach ($matches[1] as $index => $key) {
            $value = trim($matches[2][$index]);

            $properties[$key] = [
                'type'    => $this->inferTypeFromValue($value),
                'example' => $this->generateExampleFromValue($key, $value),
            ];
        }

        return $properties;
    }

    /**
     * 値から型を推論
     */
    protected function inferTypeFromValue(string $value): string
    {
        if (Str::contains($value, '->id')) {
            return 'integer';
        } elseif (Str::contains($value, ['->created_at', '->updated_at', 'Date', 'Time'])) {
            return 'string';
        } elseif (Str::contains($value, ['collection', 'Collection', '->pluck('])) {
            return 'array';
        } elseif (Str::contains($value, ['true', 'false', 'bool', '(bool)'])) {
            return 'boolean';
        } elseif (Str::contains($value, '->count()')) {
            return 'integer';
        } elseif (preg_match('/\[.*\]/', $value)) {
            return 'object';
        }

        return 'string';
    }

    /**
     * フィールド名から例を生成
     */
    protected function generateExampleFromValue(string $key, string $value): mixed
    {
        if (Str::contains($value, '->id')) {
            return 1;
        } elseif ($key === 'email') {
            return 'user@example.com';
        } elseif ($key === 'name') {
            return 'John Doe';
        } elseif (Str::contains($value, ['->created_at', '->updated_at'])) {
            if (Str::contains($value, 'toDateTimeString')) {
                return '2024-01-01 00:00:00';
            } elseif (Str::contains($value, 'format')) {
                return '2024-01-01';
            }

            return '2024-01-01T00:00:00Z';
        } elseif (Str::contains($value, '(bool)')) {
            return true;
        } elseif (Str::contains($value, '->count()')) {
            return 10;
        } elseif (Str::contains($value, 'collection') || Str::contains($value, 'Collection')) {
            return [];
        }

        return 'string';
    }
}
