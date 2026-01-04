<?php

declare(strict_types=1);

namespace LaravelSpectrum\Analyzers;

use Illuminate\Http\Resources\Json\JsonResource;
use LaravelSpectrum\Analyzers\Support\AstHelper;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Contracts\HasErrors;
use LaravelSpectrum\Contracts\HasExamples;
use LaravelSpectrum\DTO\ResourceInfo;
use LaravelSpectrum\Support\AnalyzerErrorType;
use LaravelSpectrum\Support\ErrorCollector;
use LaravelSpectrum\Support\HasErrorCollection;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\PrettyPrinter;

/**
 * @phpstan-type PropertyInfo array{
 *     type?: string,
 *     example?: mixed,
 *     format?: string,
 *     items?: array<string, mixed>,
 *     properties?: array<string, array<string, mixed>>,
 *     conditional?: bool,
 *     condition?: string
 * }
 * @phpstan-type ResourceStructure array{
 *     properties?: array<string, PropertyInfo>,
 *     conditionalFields?: list<string>,
 *     nestedResources?: list<string>,
 *     with?: array<string|int, mixed>,
 *     isCollection?: bool,
 *     hasExamples?: bool,
 *     customExample?: mixed,
 *     customExamples?: array<int, mixed>
 * }
 * @phpstan-type OpenApiSchemaArray array<string, mixed>
 */
class ResourceAnalyzer implements HasErrors
{
    use HasErrorCollection;

    protected AstHelper $astHelper;

    protected PrettyPrinter\Standard $printer;

    protected DocumentationCache $cache;

    public function __construct(
        AstHelper $astHelper,
        DocumentationCache $cache,
        ?ErrorCollector $errorCollector = null
    ) {
        $this->initializeErrorCollector($errorCollector);
        $this->astHelper = $astHelper;
        $this->cache = $cache;
        $this->printer = new PrettyPrinter\Standard;
    }

    /**
     * Resourceクラスを解析してレスポンス構造を抽出
     *
     * Returns a ResourceInfo DTO containing field definitions,
     * along with metadata like 'with', 'hasExamples', etc.
     */
    public function analyze(string $resourceClass): ResourceInfo
    {
        try {
            $cached = $this->cache->rememberResource($resourceClass, function () use ($resourceClass) {
                return $this->performAnalysis($resourceClass);
            });

            return ResourceInfo::fromArray($cached);
        } catch (\Exception $e) {
            $this->logException($e, AnalyzerErrorType::AnalysisError, [
                'class' => $resourceClass,
            ]);

            return ResourceInfo::empty();
        }
    }

    /**
     * 実際の解析処理
     *
     * @return ResourceStructure
     */
    protected function performAnalysis(string $resourceClass): array
    {
        if (! class_exists($resourceClass)) {
            $this->logWarning(
                "Resource class does not exist: {$resourceClass}",
                AnalyzerErrorType::ClassNotFound,
                ['class' => $resourceClass]
            );

            return [];
        }

        try {
            $reflection = new \ReflectionClass($resourceClass);

            // JsonResourceを継承していない場合はスキップ
            if (! $reflection->isSubclassOf(JsonResource::class)) {
                $this->logWarning(
                    "Class is not a JsonResource subclass: {$resourceClass}",
                    AnalyzerErrorType::UnsupportedFeature,
                    ['class' => $resourceClass]
                );

                return [];
            }

            $filePath = $reflection->getFileName();
            if (! $filePath || ! file_exists($filePath)) {
                $this->logWarning(
                    "Source file not found for resource: {$resourceClass}",
                    AnalyzerErrorType::FileNotFound,
                    ['class' => $resourceClass]
                );

                return [];
            }

            // ファイルをパース
            $ast = $this->astHelper->parseFile($filePath);
            if (! $ast) {
                $this->logWarning(
                    "Failed to parse source file for resource: {$resourceClass}",
                    AnalyzerErrorType::ParseError,
                    ['class' => $resourceClass, 'file' => $filePath]
                );

                return [];
            }

            // クラスノードを探す
            $classNode = $this->astHelper->findClassNode($ast, $reflection->getShortName());
            if (! $classNode) {
                $this->logWarning(
                    "Class node not found in AST for resource: {$resourceClass}",
                    AnalyzerErrorType::AnalysisError,
                    ['class' => $resourceClass, 'file' => $filePath]
                );

                return [];
            }

            // toArray()メソッドを解析
            $structure = $this->analyzeToArrayMethod($classNode);

            // with()メソッドを解析（追加のメタデータ）
            $additionalData = $this->analyzeWithMethod($classNode);
            if (! empty($additionalData)) {
                $structure['with'] = $additionalData;
            }

            // Check if the resource implements HasExamples interface
            if ($reflection->implementsInterface(HasExamples::class)) {
                $structure['hasExamples'] = true;
                try {
                    $resource = new $resourceClass(null);
                    $structure['customExample'] = $resource->getExample();
                    $structure['customExamples'] = $resource->getExamples();
                } catch (\Exception $e) {
                    $this->logWarning(
                        "Failed to get custom examples from Resource {$resourceClass}: {$e->getMessage()}",
                        AnalyzerErrorType::AnalysisError,
                        ['class' => $resourceClass]
                    );
                }
            }

            return $structure;

        } catch (Error $parseError) {
            $this->logException($parseError, AnalyzerErrorType::ParseError, [
                'class' => $resourceClass,
            ]);

            return [];
        } catch (\Exception $e) {
            $this->logException($e, AnalyzerErrorType::AnalysisError, [
                'class' => $resourceClass,
            ]);

            return [];
        }
    }

    /**
     * toArray()メソッドを解析
     *
     * @return ResourceStructure
     */
    protected function analyzeToArrayMethod(Node\Stmt\Class_ $class): array
    {
        $toArrayMethod = $this->astHelper->findMethodNode($class, 'toArray');
        if (! $toArrayMethod) {
            return [];
        }

        $visitor = new AST\Visitors\ResourceStructureVisitor($this->printer);
        $this->astHelper->traverse([$toArrayMethod], $visitor);

        $result = $visitor->getStructure();

        // コレクションかどうかを判定
        $isCollection = $this->isResourceCollection($class);
        if ($isCollection) {
            $result['isCollection'] = true;
        }

        return $result;
    }

    /**
     * with()メソッドを解析（追加のメタデータ）
     *
     * @return array<string|int, mixed>
     */
    protected function analyzeWithMethod(Node\Stmt\Class_ $class): array
    {
        $withMethod = $this->astHelper->findMethodNode($class, 'with');
        if (! $withMethod) {
            return [];
        }

        $visitor = new AST\Visitors\ArrayReturnExtractorVisitor($this->printer);
        $this->astHelper->traverse([$withMethod], $visitor);

        return $visitor->getArray();
    }

    /**
     * ResourceCollectionかどうかを判定
     */
    protected function isResourceCollection(Node\Stmt\Class_ $class): bool
    {
        // クラス名で判定
        $className = $class->name->toString();
        if (str_ends_with($className, 'Collection')) {
            return true;
        }

        // 親クラスで判定
        if ($class->extends) {
            $parentName = $class->extends->toString();
            if (str_contains($parentName, 'ResourceCollection')) {
                return true;
            }
        }

        return false;
    }

    /**
     * レスポンス構造からOpenAPIスキーマを生成
     *
     * @param  ResourceStructure  $structure
     * @return OpenApiSchemaArray
     */
    public function generateSchema(array $structure): array
    {
        if (empty($structure['properties'])) {
            return ['type' => 'object'];
        }

        $schema = [
            'type' => 'object',
            'properties' => [],
        ];

        $required = [];

        foreach ($structure['properties'] as $key => $info) {
            $propertySchema = $this->generatePropertySchema($info);

            if ($propertySchema) {
                $schema['properties'][$key] = $propertySchema;

                // 条件付きでないフィールドは必須とする
                if (! isset($info['conditional']) || ! $info['conditional']) {
                    $required[] = $key;
                }
            }
        }

        if (! empty($required)) {
            $schema['required'] = $required;
        }

        // 追加のメタデータ
        if (! empty($structure['with'])) {
            $schema['properties'] = array_merge(
                $schema['properties'],
                $this->generatePropertiesFromArray($structure['with'])
            );
        }

        return $schema;
    }

    /**
     * プロパティのスキーマを生成
     *
     * @param  PropertyInfo  $info
     * @return OpenApiSchemaArray
     */
    protected function generatePropertySchema(array $info): array
    {
        $schema = [
            'type' => $info['type'] ?? 'string',
        ];

        // 例がある場合
        if (isset($info['example'])) {
            $schema['example'] = $info['example'];
        }

        // 日付フォーマット
        if (isset($info['format'])) {
            $schema['format'] = $info['format'];
        }

        // 配列の場合
        if ($info['type'] === 'array' && isset($info['items'])) {
            $schema['items'] = $this->generatePropertySchema($info['items']);
        }

        // オブジェクトの場合
        if ($info['type'] === 'object' && isset($info['properties'])) {
            $schema['properties'] = [];
            foreach ($info['properties'] as $key => $propInfo) {
                $schema['properties'][$key] = $this->generatePropertySchema($propInfo);
            }
        }

        // 条件付きフィールドの場合
        if (isset($info['conditional']) && $info['conditional']) {
            $schema['nullable'] = true;
            $schema['description'] = 'Conditional field';

            if (isset($info['condition'])) {
                $schema['description'] .= ' ('.$info['condition'].')';
            }
        }

        return $schema;
    }

    /**
     * 配列から properties を生成
     *
     * @param  array<string|int, mixed>  $array
     * @return array<string, OpenApiSchemaArray>
     */
    protected function generatePropertiesFromArray(array $array): array
    {
        $properties = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $properties[$key] = [
                    'type' => 'object',
                    'properties' => $this->generatePropertiesFromArray($value),
                ];
            } else {
                $properties[$key] = [
                    'type' => gettype($value),
                    'example' => $value,
                ];
            }
        }

        return $properties;
    }
}
