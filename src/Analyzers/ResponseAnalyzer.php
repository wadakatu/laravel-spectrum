<?php

declare(strict_types=1);

namespace LaravelSpectrum\Analyzers;

use LaravelSpectrum\Analyzers\AST\Visitors\ReturnStatementVisitor;
use LaravelSpectrum\Contracts\Analyzers\MethodAnalyzer;
use LaravelSpectrum\Support\AstTypeInferenceEngine;
use LaravelSpectrum\Support\CollectionAnalyzer;
use LaravelSpectrum\Support\MethodSourceExtractor;
use LaravelSpectrum\Support\ModelSchemaExtractor;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\Parser;

class ResponseAnalyzer implements MethodAnalyzer
{
    private Parser $parser;

    private ModelSchemaExtractor $modelExtractor;

    private CollectionAnalyzer $collectionAnalyzer;

    private MethodSourceExtractor $methodSourceExtractor;

    private AstTypeInferenceEngine $typeInferenceEngine;

    public function __construct(
        Parser $parser,
        ModelSchemaExtractor $modelExtractor,
        CollectionAnalyzer $collectionAnalyzer,
        ?MethodSourceExtractor $methodSourceExtractor = null,
        ?AstTypeInferenceEngine $typeInferenceEngine = null
    ) {
        $this->parser = $parser;
        $this->modelExtractor = $modelExtractor;
        $this->collectionAnalyzer = $collectionAnalyzer;
        $this->methodSourceExtractor = $methodSourceExtractor ?? new MethodSourceExtractor;
        $this->typeInferenceEngine = $typeInferenceEngine ?? new AstTypeInferenceEngine;
    }

    public function analyze(string $controllerClass, string $method): array
    {
        try {
            $reflection = new \ReflectionClass($controllerClass);
            $methodReflection = $reflection->getMethod($method);

            // メソッドのソースコードを取得
            $source = $this->methodSourceExtractor->extractBody($methodReflection);
            $ast = $this->parser->parse('<?php '.$source);

            // return文を検出
            $returnVisitor = new ReturnStatementVisitor;
            $traverser = new NodeTraverser;
            $traverser->addVisitor($returnVisitor);
            $traverser->traverse($ast);

            $returnStatements = $returnVisitor->getReturnStatements();

            if (empty($returnStatements)) {
                return ['type' => 'void'];
            }

            // 各return文を解析
            $responses = [];
            foreach ($returnStatements as $returnStmt) {
                $structure = $this->analyzeReturnStatement($returnStmt, $controllerClass);
                if ($structure) {
                    $responses[] = $structure;
                }
            }

            // 最も可能性の高いレスポンス構造を返す
            return $this->mergeResponses($responses);

        } catch (\Exception $e) {
            return ['type' => 'unknown', 'error' => $e->getMessage()];
        }
    }

    private function analyzeReturnStatement($returnStmt, string $controllerClass): array
    {
        $expr = $returnStmt->expr;

        if (! $expr) {
            return ['type' => 'void'];
        }

        // パターン1: response()->json([...])
        if ($this->isResponseJson($expr)) {
            return $this->analyzeResponseJson($expr);
        }

        // パターン2: 配列の直接返却
        if ($this->isArrayReturn($expr)) {
            return $this->analyzeArrayReturn($expr);
        }

        // パターン3: Eloquentモデル
        if ($this->isEloquentModel($expr, $controllerClass)) {
            return $this->analyzeEloquentModel($expr, $controllerClass);
        }

        // パターン4: コレクション
        if ($this->isCollection($expr)) {
            return $this->analyzeCollection($expr, $controllerClass);
        }

        // パターン5: リソース/トランスフォーマー（既存の機能を活用）
        if ($this->isResource($expr)) {
            return ['type' => 'resource', 'class' => $this->extractResourceClass($expr)];
        }

        return ['type' => 'unknown'];
    }

    private function isResponseJson($expr): bool
    {
        return $expr instanceof Node\Expr\MethodCall
            && $expr->var instanceof Node\Expr\FuncCall
            && $expr->var->name instanceof Node\Name
            && $expr->var->name->toString() === 'response'
            && $expr->name instanceof Node\Identifier
            && $expr->name->toString() === 'json';
    }

    private function analyzeResponseJson($expr): array
    {
        // response()->json()の引数を解析
        $args = $expr->args[0] ?? null;
        if (! $args) {
            return ['type' => 'object', 'properties' => []];
        }

        return [
            'type' => 'object',
            'properties' => $this->extractArrayStructure($args->value),
            'wrapped' => false,
        ];
    }

    private function isArrayReturn($expr): bool
    {
        return $expr instanceof Node\Expr\Array_;
    }

    private function analyzeArrayReturn($expr): array
    {
        return [
            'type' => 'object',
            'properties' => $this->extractArrayStructure($expr),
        ];
    }

    private function extractArrayStructure($node): array
    {
        $structure = [];

        if ($node instanceof Node\Expr\Array_) {
            foreach ($node->items as $item) {
                if ($item && $item->key) {
                    $key = $this->getNodeValue($item->key);
                    if ($key !== null) {
                        $structure[$key] = $this->typeInferenceEngine->inferFromNode($item->value);
                    }
                }
            }
        }

        return $structure;
    }

    private function getNodeValue($node)
    {
        if ($node instanceof Node\Scalar\String_) {
            return $node->value;
        }
        if ($node instanceof Node\Scalar\Int_) {
            return (string) $node->value;
        }

        return null;
    }

    private function isEloquentModel($expr, string $controllerClass): bool
    {
        // User::find(), User::findOrFail()などのパターン
        if ($expr instanceof Node\Expr\StaticCall
            && $expr->class instanceof Node\Name
            && $expr->name instanceof Node\Identifier
            && in_array($expr->name->toString(), ['find', 'findOrFail', 'first', 'firstOrFail', 'create'])
        ) {
            return true;
        }

        return false;
    }

    private function analyzeEloquentModel($expr, string $controllerClass): array
    {
        if ($expr instanceof Node\Expr\StaticCall && $expr->class instanceof Node\Name) {
            $modelClass = $expr->class->toString();

            // 名前空間の解決
            if (! str_contains($modelClass, '\\')) {
                // コントローラーの名前空間から推測
                $lastBackslash = strrpos($controllerClass, '\\');
                if ($lastBackslash !== false) {
                    $namespace = substr($controllerClass, 0, $lastBackslash);
                    $namespace = str_replace('\\Http\\Controllers', '', $namespace);
                    $modelClass = $namespace.'\\Models\\'.$modelClass;
                }
            }

            return $this->modelExtractor->extractSchema($modelClass);
        }

        return ['type' => 'object'];
    }

    private function isCollection($expr): bool
    {
        // User::all(), User::get()などのパターン
        if ($expr instanceof Node\Expr\StaticCall
            && $expr->name instanceof Node\Identifier
            && in_array($expr->name->toString(), ['all', 'get'])
        ) {
            return true;
        }

        // ->map(), ->filter()などのコレクション操作
        if ($expr instanceof Node\Expr\MethodCall
            && $expr->name instanceof Node\Identifier
            && in_array($expr->name->toString(), ['map', 'filter', 'pluck', 'only', 'except'])
        ) {
            return true;
        }

        return false;
    }

    private function analyzeCollection($expr, string $controllerClass): array
    {
        return $this->collectionAnalyzer->analyzeCollectionChain($expr);
    }

    private function isResource($expr): bool
    {
        // new UserResource(), UserResource::collection()などのパターン
        if ($expr instanceof Node\Expr\New_
            && $expr->class instanceof Node\Name
            && str_contains($expr->class->toString(), 'Resource')
        ) {
            return true;
        }

        if ($expr instanceof Node\Expr\StaticCall
            && $expr->class instanceof Node\Name
            && str_contains($expr->class->toString(), 'Resource')
            && $expr->name instanceof Node\Identifier
            && $expr->name->toString() === 'collection'
        ) {
            return true;
        }

        return false;
    }

    private function extractResourceClass($expr): string
    {
        if ($expr instanceof Node\Expr\New_ && $expr->class instanceof Node\Name) {
            return $expr->class->toString();
        }

        if ($expr instanceof Node\Expr\StaticCall && $expr->class instanceof Node\Name) {
            return $expr->class->toString();
        }

        return '';
    }

    private function mergeResponses(array $responses): array
    {
        // 単純な実装：最初の非unknownレスポンスを返す
        foreach ($responses as $response) {
            if ($response['type'] !== 'unknown') {
                return $response;
            }
        }

        return ['type' => 'unknown'];
    }
}
