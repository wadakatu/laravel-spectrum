<?php

namespace LaravelPrism\Analyzers;

use ReflectionClass;
use ReflectionMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Resources\Json\JsonResource;

class ControllerAnalyzer
{
    /**
     * コントローラーメソッドを解析してFormRequestとResourceを抽出
     */
    public function analyze(string $controller, string $method): array
    {
        if (!class_exists($controller)) {
            return [];
        }
        
        $reflection = new ReflectionClass($controller);
        
        if (!$reflection->hasMethod($method)) {
            return [];
        }
        
        $methodReflection = $reflection->getMethod($method);
        
        $result = [
            'formRequest' => null,
            'resource' => null,
            'returnsCollection' => false,
        ];
        
        // パラメータからFormRequestを検出
        foreach ($methodReflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type && !$type->isBuiltin()) {
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
        // 同じ名前空間のクラスを試す
        $namespace = $reflection->getNamespaceName();
        $fullName = $namespace . '\\' . $shortName;
        if (class_exists($fullName)) {
            return $fullName;
        }
        
        // ファイルのuse文をチェック（簡易版）
        $filename = $reflection->getFileName();
        $content = file_get_contents($filename);
        
        if (preg_match('/use\s+([\w\\\\]+\\\\' . $shortName . ');/', $content, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
}