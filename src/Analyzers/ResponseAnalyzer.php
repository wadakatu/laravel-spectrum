<?php

declare(strict_types=1);

namespace LaravelSpectrum\Analyzers;

use LaravelSpectrum\Analyzers\AST\Visitors\ReturnStatementVisitor;
use LaravelSpectrum\DTO\ResponseInfo;
use LaravelSpectrum\DTO\ResponseType;
use LaravelSpectrum\Support\AstTypeInferenceEngine;
use LaravelSpectrum\Support\CollectionAnalyzer;
use LaravelSpectrum\Support\MethodSourceExtractor;
use LaravelSpectrum\Support\ModelSchemaExtractor;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\Parser;

/**
 * Analyzes controller methods to determine their response structure.
 */
class ResponseAnalyzer
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

    public function analyze(string $controllerClass, string $method): ResponseInfo
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
                return ResponseInfo::void();
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
            return ResponseInfo::fromArray($this->mergeResponses($responses));

        } catch (\Exception $e) {
            return ResponseInfo::unknownWithError($e->getMessage());
        }
    }

    private function analyzeReturnStatement(Node\Stmt\Return_ $returnStmt, string $controllerClass): array
    {
        $expr = $returnStmt->expr;

        if (! $expr) {
            return ['type' => 'void'];
        }

        // パターン1: response()->json([...])
        if ($this->isResponseJson($expr)) {
            return $this->analyzeResponseJson($expr);
        }

        // パターン2: response()->download() / response()->file()
        if ($this->isResponseDownloadOrFile($expr)) {
            return $this->analyzeResponseDownloadOrFile($expr);
        }

        // パターン3: response()->stream() / response()->streamDownload()
        if ($this->isResponseStream($expr)) {
            return $this->analyzeResponseStream($expr);
        }

        // パターン4: response() with custom Content-Type header
        if ($this->isResponseWithCustomContentType($expr)) {
            return $this->analyzeResponseWithCustomContentType($expr);
        }

        // パターン5: 配列の直接返却
        if ($this->isArrayReturn($expr)) {
            return $this->analyzeArrayReturn($expr);
        }

        // パターン6: Eloquentモデル
        if ($this->isEloquentModel($expr, $controllerClass)) {
            return $this->analyzeEloquentModel($expr, $controllerClass);
        }

        // パターン7: コレクション
        if ($this->isCollection($expr)) {
            return $this->analyzeCollection($expr, $controllerClass);
        }

        // パターン8: リソース/トランスフォーマー（既存の機能を活用）
        if ($this->isResource($expr)) {
            return ['type' => 'resource', 'class' => $this->extractResourceClass($expr)];
        }

        return ['type' => 'unknown'];
    }

    private function isResponseJson(Node\Expr $expr): bool
    {
        return $expr instanceof Node\Expr\MethodCall
            && $expr->var instanceof Node\Expr\FuncCall
            && $expr->var->name instanceof Node\Name
            && $expr->var->name->toString() === 'response'
            && $expr->name instanceof Node\Identifier
            && $expr->name->toString() === 'json';
    }

    private function analyzeResponseJson(Node\Expr $expr): array
    {
        // response()->json()の引数を解析
        if (! $expr instanceof Node\Expr\MethodCall) {
            return ['type' => 'object', 'properties' => []];
        }

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

    private function isArrayReturn(Node\Expr $expr): bool
    {
        return $expr instanceof Node\Expr\Array_;
    }

    private function analyzeArrayReturn(Node\Expr $expr): array
    {
        return [
            'type' => 'object',
            'properties' => $this->extractArrayStructure($expr),
        ];
    }

    private function extractArrayStructure(Node $node): array
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

    private function getNodeValue(Node $node): ?string
    {
        if ($node instanceof Node\Scalar\String_) {
            return $node->value;
        }
        if ($node instanceof Node\Scalar\Int_) {
            return (string) $node->value;
        }

        return null;
    }

    private function isEloquentModel(Node\Expr $expr, string $controllerClass): bool
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

    private function analyzeEloquentModel(Node\Expr $expr, string $controllerClass): array
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

    private function isCollection(Node\Expr $expr): bool
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

    private function analyzeCollection(Node\Expr $expr, string $controllerClass): array
    {
        return $this->collectionAnalyzer->analyzeCollectionChain($expr);
    }

    private function isResource(Node\Expr $expr): bool
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

    private function extractResourceClass(Node\Expr $expr): string
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

    /**
     * Check if expression is response()->download() or response()->file()
     */
    private function isResponseDownloadOrFile(Node\Expr $expr): bool
    {
        return $expr instanceof Node\Expr\MethodCall
            && $expr->var instanceof Node\Expr\FuncCall
            && $expr->var->name instanceof Node\Name
            && $expr->var->name->toString() === 'response'
            && $expr->name instanceof Node\Identifier
            && in_array($expr->name->toString(), ['download', 'file'], true);
    }

    /**
     * Analyze response()->download() or response()->file() pattern
     *
     * @return array<string, mixed>
     */
    private function analyzeResponseDownloadOrFile(Node\Expr $expr): array
    {
        if (! $expr instanceof Node\Expr\MethodCall) {
            return ['type' => ResponseType::BINARY_FILE->value, 'contentType' => 'application/octet-stream'];
        }

        $args = $expr->args;
        $filePath = $this->extractStringArgument($args[0] ?? null);
        $fileName = $this->extractStringArgument($args[1] ?? null);

        // Determine file extension from fileName (if provided) or filePath
        $extension = $fileName
            ? pathinfo($fileName, PATHINFO_EXTENSION)
            : ($filePath ? pathinfo($filePath, PATHINFO_EXTENSION) : '');

        $contentType = $this->getMimeTypeFromExtension($extension);

        $result = [
            'type' => ResponseType::BINARY_FILE->value,
            'contentType' => $contentType,
        ];

        if ($fileName) {
            $result['fileName'] = $fileName;
        }

        return $result;
    }

    /**
     * Check if expression is response()->stream() or response()->streamDownload()
     */
    private function isResponseStream(Node\Expr $expr): bool
    {
        return $expr instanceof Node\Expr\MethodCall
            && $expr->var instanceof Node\Expr\FuncCall
            && $expr->var->name instanceof Node\Name
            && $expr->var->name->toString() === 'response'
            && $expr->name instanceof Node\Identifier
            && in_array($expr->name->toString(), ['stream', 'streamDownload'], true);
    }

    /**
     * Analyze response()->stream() or response()->streamDownload() pattern
     *
     * @return array<string, mixed>
     */
    private function analyzeResponseStream(Node\Expr $expr): array
    {
        if (! $expr instanceof Node\Expr\MethodCall) {
            return ['type' => ResponseType::STREAMED->value, 'contentType' => 'application/octet-stream'];
        }

        $methodName = $expr->name instanceof Node\Identifier ? $expr->name->toString() : '';
        $args = $expr->args;

        $result = [
            'type' => ResponseType::STREAMED->value,
            'contentType' => 'application/octet-stream',
        ];

        if ($methodName === 'streamDownload') {
            // streamDownload($callback, $name, $headers)
            $fileName = $this->extractStringArgument($args[1] ?? null);
            if ($fileName) {
                $result['fileName'] = $fileName;
            }

            // Check for Content-Type in headers (3rd argument)
            $contentType = $this->extractContentTypeFromHeaders($args[2] ?? null);
            if ($contentType) {
                $result['contentType'] = $contentType;
            }
        } elseif ($methodName === 'stream') {
            // stream($callback, $status, $headers)
            $contentType = $this->extractContentTypeFromHeaders($args[2] ?? null);
            if ($contentType) {
                $result['contentType'] = $contentType;
            }
        }

        return $result;
    }

    /**
     * Check if expression is response() with custom Content-Type header
     */
    private function isResponseWithCustomContentType(Node\Expr $expr): bool
    {
        // response($content, $status, $headers) pattern
        if ($expr instanceof Node\Expr\FuncCall
            && $expr->name instanceof Node\Name
            && $expr->name->toString() === 'response'
            && count($expr->args) >= 3
        ) {
            // Check if 3rd argument contains Content-Type
            $contentType = $this->extractContentTypeFromHeaders($expr->args[2] ?? null);

            return $contentType !== null && $contentType !== 'application/json';
        }

        return false;
    }

    /**
     * Analyze response() with custom Content-Type header
     *
     * @return array<string, mixed>
     */
    private function analyzeResponseWithCustomContentType(Node\Expr $expr): array
    {
        if (! $expr instanceof Node\Expr\FuncCall) {
            return ['type' => ResponseType::CUSTOM->value, 'contentType' => 'application/octet-stream'];
        }

        $contentType = $this->extractContentTypeFromHeaders($expr->args[2] ?? null);

        return [
            'type' => ResponseType::CUSTOM->value,
            'contentType' => $contentType ?? 'application/octet-stream',
        ];
    }

    /**
     * Extract string value from an argument node
     */
    private function extractStringArgument(?Node\Arg $arg): ?string
    {
        if (! $arg) {
            return null;
        }

        $value = $arg->value;

        if ($value instanceof Node\Scalar\String_) {
            return $value->value;
        }

        // Handle function calls like storage_path('...')
        if ($value instanceof Node\Expr\FuncCall && ! empty($value->args)) {
            $firstArg = $value->args[0] ?? null;
            if ($firstArg && $firstArg->value instanceof Node\Scalar\String_) {
                return $firstArg->value->value;
            }
        }

        return null;
    }

    /**
     * Extract Content-Type from headers array argument
     */
    private function extractContentTypeFromHeaders(?Node\Arg $arg): ?string
    {
        if (! $arg) {
            return null;
        }

        $value = $arg->value;

        if (! $value instanceof Node\Expr\Array_) {
            return null;
        }

        foreach ($value->items as $item) {
            if (! $item || ! $item->key) {
                continue;
            }

            $key = $this->getNodeValue($item->key);
            // HTTP headers are case-insensitive per RFC 7230
            if ($key !== null && strtolower($key) === 'content-type' && $item->value instanceof Node\Scalar\String_) {
                return $item->value->value;
            }
        }

        return null;
    }

    /**
     * Get MIME type from file extension
     *
     * @return string The MIME type or 'application/octet-stream' for unknown extensions
     */
    private function getMimeTypeFromExtension(string $extension): string
    {
        $mimeTypes = [
            // Documents
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',

            // Text
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'xml' => 'application/xml',
            'json' => 'application/json',
            'html' => 'text/html',
            'htm' => 'text/html',

            // Images
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',

            // Audio
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',

            // Video
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'avi' => 'video/x-msvideo',

            // Archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip',
            '7z' => 'application/x-7z-compressed',
        ];

        $extension = strtolower($extension);

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
}
