<?php

namespace LaravelPrism\Analyzers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;

class ResourceAnalyzer
{
    protected Parser $parser;
    protected NodeTraverser $traverser;
    protected PrettyPrinter\Standard $printer;

    public function __construct()
    {
        $this->parser    = (new ParserFactory)->createForNewestSupportedVersion();
        $this->traverser = new NodeTraverser;
        $this->printer   = new PrettyPrinter\Standard;
    }

    /**
     * Resourceクラスを解析してレスポンス構造を抽出
     *
     * @param  bool  $useNewFormat  新しいフォーマット（properties/conditionalFields等）を使用するか
     */
    public function analyze(string $resourceClass, bool $useNewFormat = false): array
    {
        if (! class_exists($resourceClass)) {
            return [];
        }

        try {
            $reflection = new \ReflectionClass($resourceClass);

            // JsonResourceを継承していない場合はスキップ
            if (! $reflection->isSubclassOf(JsonResource::class)) {
                return [];
            }

            $filePath = $reflection->getFileName();
            if (! $filePath || ! file_exists($filePath)) {
                return [];
            }

            // ファイルをパース
            $code = file_get_contents($filePath);
            $ast  = $this->parser->parse($code);

            if (! $ast) {
                return [];
            }

            // クラスノードを探す
            $classNode = $this->findClassNode($ast, $reflection->getShortName());
            if (! $classNode) {
                return [];
            }

            // toArray()メソッドを解析
            $structure = $this->analyzeToArrayMethod($classNode);

            // with()メソッドを解析（追加のメタデータ）
            $additionalData = $this->analyzeWithMethod($classNode);
            if (! empty($additionalData)) {
                $structure['with'] = $additionalData;
            }

            // 新しいフォーマットを使用する場合はそのまま返す
            if ($useNewFormat) {
                return $structure;
            }

            // 旧API互換性のため、既存のテストでフラット構造を期待する場合
            if (! empty($structure['properties'])) {
                // propertiesをルートレベルにマージ
                $flatStructure = $structure['properties'];

                // その他のメタデータも保持（プレフィックスなし）
                if (! empty($structure['isCollection'])) {
                    $flatStructure['isCollection'] = $structure['isCollection'];
                }
                if (! empty($structure['with'])) {
                    $flatStructure['with'] = $structure['with'];
                }

                return $flatStructure;
            }

            return $structure;

        } catch (Error $parseError) {
            Log::warning("Failed to parse Resource: {$resourceClass}", [
                'error' => $parseError->getMessage(),
            ]);

            return [];
        } catch (\Exception $e) {
            Log::warning("Failed to analyze Resource: {$resourceClass}", [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * クラスノードを探す
     */
    protected function findClassNode(array $ast, string $className): ?Node\Stmt\Class_
    {
        $visitor   = new AST\Visitors\ClassFindingVisitor($className);
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->getClassNode();
    }

    /**
     * toArray()メソッドを解析
     */
    protected function analyzeToArrayMethod(Node\Stmt\Class_ $class): array
    {
        $toArrayMethod = $this->findMethodNode($class, 'toArray');
        if (! $toArrayMethod) {
            return [];
        }

        $visitor   = new AST\Visitors\ResourceStructureVisitor($this->printer);
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse([$toArrayMethod]);

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
     */
    protected function analyzeWithMethod(Node\Stmt\Class_ $class): array
    {
        $withMethod = $this->findMethodNode($class, 'with');
        if (! $withMethod) {
            return [];
        }

        $visitor   = new AST\Visitors\ArrayReturnExtractorVisitor($this->printer);
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse([$withMethod]);

        return $visitor->getArray();
    }

    /**
     * メソッドノードを探す
     */
    protected function findMethodNode(Node\Stmt\Class_ $class, string $methodName): ?Node\Stmt\ClassMethod
    {
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\ClassMethod &&
                $stmt->name->toString() === $methodName) {
                return $stmt;
            }
        }

        return null;
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
     */
    public function generateSchema(array $structure): array
    {
        if (empty($structure['properties'])) {
            return ['type' => 'object'];
        }

        $schema = [
            'type'       => 'object',
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
            $schema['nullable']    = true;
            $schema['description'] = 'Conditional field';

            if (isset($info['condition'])) {
                $schema['description'] .= ' (' . $info['condition'] . ')';
            }
        }

        return $schema;
    }

    /**
     * 配列から properties を生成
     */
    protected function generatePropertiesFromArray(array $array): array
    {
        $properties = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $properties[$key] = [
                    'type'       => 'object',
                    'properties' => $this->generatePropertiesFromArray($value),
                ];
            } else {
                $properties[$key] = [
                    'type'    => gettype($value),
                    'example' => $value,
                ];
            }
        }

        return $properties;
    }
}
