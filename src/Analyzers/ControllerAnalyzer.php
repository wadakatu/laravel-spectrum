<?php

namespace LaravelPrism\Analyzers;

use Illuminate\Foundation\Http\FormRequest;
use ReflectionClass;
use ReflectionMethod;

class ControllerAnalyzer
{
    /**
     * コントローラーメソッドを解析してFormRequestとResourceを抽出
     */
    public function analyze(string $controller, string $method): array
    {
        if (! class_exists($controller)) {
            return [];
        }

        $reflection = new ReflectionClass($controller);

        if (! $reflection->hasMethod($method)) {
            return [];
        }

        $methodReflection = $reflection->getMethod($method);

        $result = [
            'formRequest' => null,
            'resource' => null,
            'returnsCollection' => false,
            'fractal' => null,
        ];

        // パラメータからFormRequestを検出
        foreach ($methodReflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type && ! $type->isBuiltin()) {
                $className = $type->getName();
                if (class_exists($className) && is_subclass_of($className, FormRequest::class)) {
                    $result['formRequest'] = $className;
                    break;
                }
            }
        }

        // メソッドのソースコードからResourceを検出（簡易版）
        $source = $this->getMethodSource($methodReflection);

        // Resourceクラスの使用を検出
        if (preg_match('/(\w+Resource)::collection/', $source, $matches)) {
            $resourceClass = $this->resolveClassName($matches[1], $reflection);
            if ($resourceClass && class_exists($resourceClass)) {
                $result['resource'] = $resourceClass;
                $result['returnsCollection'] = true;
            }
        } elseif (preg_match('/new\s+(\w+Resource)/', $source, $matches)) {
            $resourceClass = $this->resolveClassName($matches[1], $reflection);
            if ($resourceClass && class_exists($resourceClass)) {
                $result['resource'] = $resourceClass;
            }
        }

        // Fractal使用を検出
        $this->detectFractalUsage($source, $result, $reflection);

        return $result;
    }

    /**
     * メソッドのソースコードを取得
     */
    protected function getMethodSource(ReflectionMethod $method): string
    {
        $filename = $method->getFileName();
        $startLine = $method->getStartLine() - 1;
        $endLine = $method->getEndLine();
        $length = $endLine - $startLine;

        $source = file($filename);

        return implode('', array_slice($source, $startLine, $length));
    }

    /**
     * クラス名を解決（use文を考慮）
     */
    protected function resolveClassName(string $shortName, ReflectionClass $reflection): ?string
    {
        // 完全修飾名の場合
        if (strpos($shortName, '\\') !== false) {
            // 先頭の\を除去
            $shortName = ltrim($shortName, '\\');
            if (class_exists($shortName)) {
                return $shortName;
            }
        }

        // クラス名のみを取得
        $className = basename(str_replace('\\', '/', $shortName));

        // 同じ名前空間のクラスを試す
        $namespace = $reflection->getNamespaceName();
        $fullName = $namespace.'\\'.$className;
        if (class_exists($fullName)) {
            return $fullName;
        }

        // ファイルのuse文をチェック（簡易版）
        $filename = $reflection->getFileName();
        $content = file_get_contents($filename);

        if (preg_match('/use\s+([\w\\\\]+\\\\'.$className.');/', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Fractal使用を検出
     */
    protected function detectFractalUsage(string $source, array &$result, ReflectionClass $reflection): void
    {
        // fractal()->item() パターン
        if (preg_match('/fractal\(\)->item\([^,]+,\s*new\s+([\\\\]?\w+(?:\\\\\\w+)*)\s*(?:\(|\))/', $source, $matches)) {
            $transformerClass = $this->resolveClassName($matches[1], $reflection);
            if ($transformerClass && class_exists($transformerClass)) {
                $result['fractal'] = [
                    'transformer' => $transformerClass,
                    'collection' => false,
                    'type' => 'item',
                    'hasIncludes' => strpos($source, 'parseIncludes') !== false,
                ];
            }
        }
        // fractal()->collection() パターン
        elseif (preg_match('/fractal\(\)->collection\([^,]+,\s*new\s+([\\\\]?\w+(?:\\\\\\w+)*)\s*(?:\(|\))/', $source, $matches)) {
            $transformerClass = $this->resolveClassName($matches[1], $reflection);
            if ($transformerClass && class_exists($transformerClass)) {
                $result['fractal'] = [
                    'transformer' => $transformerClass,
                    'collection' => true,
                    'type' => 'collection',
                    'hasIncludes' => strpos($source, 'parseIncludes') !== false,
                ];
            }
        }
        // fractal()をチェーン呼び出しするパターン
        elseif (preg_match('/fractal\(\)\s*->\s*(item|collection)\([^,]+,\s*new\s+([\\\\]?\w+(?:\\\\\\w+)*)\s*(?:\(|\))/', $source, $matches)) {
            $type = $matches[1];
            $transformerClass = $this->resolveClassName($matches[2], $reflection);
            if ($transformerClass && class_exists($transformerClass)) {
                $result['fractal'] = [
                    'transformer' => $transformerClass,
                    'collection' => $type === 'collection',
                    'type' => $type,
                    'hasIncludes' => strpos($source, 'parseIncludes') !== false,
                ];
            }
        }
    }
}
