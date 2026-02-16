<?php

declare(strict_types=1);

namespace LaravelSpectrum\Analyzers;

use Illuminate\Support\Str;
use LaravelSpectrum\Analyzers\Support\AstHelper;
use LaravelSpectrum\Contracts\Analyzers\ClassAnalyzer;
use LaravelSpectrum\Contracts\HasErrors;
use LaravelSpectrum\DTO\FractalTransformerResult;
use LaravelSpectrum\Support\AnalyzerErrorType;
use LaravelSpectrum\Support\AstTypeInferenceEngine;
use LaravelSpectrum\Support\ErrorCollector;
use LaravelSpectrum\Support\FieldNameInference;
use LaravelSpectrum\Support\HasErrorCollection;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Analyzes Fractal Transformer classes to extract schema information.
 *
 * This analyzer processes classes extending League\Fractal\TransformerAbstract
 * to extract transform method properties, available/default includes, and metadata
 * for OpenAPI documentation generation.
 */
class FractalTransformerAnalyzer implements ClassAnalyzer, HasErrors
{
    use HasErrorCollection;

    protected AstHelper $astHelper;

    protected AstTypeInferenceEngine $typeInferenceEngine;

    /**
     * Create a new FractalTransformerAnalyzer instance.
     *
     * @param  AstHelper  $astHelper  AstHelper instance for AST operations
     * @param  ErrorCollector|null  $errorCollector  Optional error collector for logging analysis failures
     * @param  AstTypeInferenceEngine|null  $typeInferenceEngine  Optional type inference engine
     */
    public function __construct(
        AstHelper $astHelper,
        ?ErrorCollector $errorCollector = null,
        ?AstTypeInferenceEngine $typeInferenceEngine = null
    ) {
        $this->initializeErrorCollector($errorCollector);
        $this->astHelper = $astHelper;
        $this->typeInferenceEngine = $typeInferenceEngine ?? new AstTypeInferenceEngine;
    }

    /**
     * Fractal Transformerクラスを解析
     *
     * @return array<string, mixed>
     */
    public function analyze(string $transformerClass): array
    {
        return $this->analyzeToResult($transformerClass)->toArray();
    }

    /**
     * Fractal Transformerクラスを解析してDTOを返す
     *
     * @param  string  $transformerClass  The fully qualified class name of the transformer
     * @return FractalTransformerResult The analysis result (use isValid to check success)
     */
    public function analyzeToResult(string $transformerClass): FractalTransformerResult
    {
        if (! class_exists($transformerClass)) {
            $this->logWarning(
                "Class does not exist: {$transformerClass}",
                AnalyzerErrorType::ClassNotFound,
                ['class' => $transformerClass]
            );

            return FractalTransformerResult::empty();
        }

        try {
            $reflection = new \ReflectionClass($transformerClass);

            // League\Fractal\TransformerAbstractを継承しているか確認
            if (! $reflection->isSubclassOf('League\Fractal\TransformerAbstract')) {
                $this->logWarning(
                    "Class {$transformerClass} does not extend League\\Fractal\\TransformerAbstract",
                    AnalyzerErrorType::InvalidParentClass,
                    ['class' => $transformerClass]
                );

                return FractalTransformerResult::empty();
            }

            $filePath = $reflection->getFileName();
            if (! $filePath) {
                $this->logWarning(
                    "Could not determine file path for class: {$transformerClass}",
                    AnalyzerErrorType::FileNotFound,
                    ['class' => $transformerClass]
                );

                return FractalTransformerResult::empty();
            }

            $ast = $this->astHelper->parseFile($filePath);
            if (! $ast) {
                // AstHelper already logs parse errors
                return FractalTransformerResult::empty();
            }

            $classNode = $this->astHelper->findClassNode($ast, $reflection->getShortName());
            if (! $classNode) {
                $this->logWarning(
                    "Could not find class node for {$reflection->getShortName()} in {$filePath}",
                    AnalyzerErrorType::ClassNodeNotFound,
                    [
                        'class' => $transformerClass,
                        'short_name' => $reflection->getShortName(),
                        'file_path' => $filePath,
                    ]
                );

                return FractalTransformerResult::empty();
            }

            $modelPropertyTypes = $this->extractTransformModelPropertyTypes(
                $reflection,
                $this->astHelper->extractUseStatements($ast)
            );

            return new FractalTransformerResult(
                properties: $this->extractTransformMethod($classNode, $modelPropertyTypes),
                availableIncludes: $this->extractAvailableIncludes($classNode),
                defaultIncludes: $this->extractDefaultIncludes($classNode),
                meta: $this->extractMetaData($classNode),
            );
        } catch (\ReflectionException $e) {
            $this->logException($e, AnalyzerErrorType::ReflectionError, [
                'class' => $transformerClass,
            ]);

            return FractalTransformerResult::empty();
        } catch (\Exception $e) {
            $this->logException($e, AnalyzerErrorType::UnexpectedError, [
                'class' => $transformerClass,
            ]);

            return FractalTransformerResult::empty();
        }
    }

    /**
     * transform()メソッドからプロパティを抽出
     *
     * @param  array<string, array<string, array{type: string, format?: string}>>  $modelPropertyTypes
     * @return array<string, array<string, mixed>>
     */
    protected function extractTransformMethod(Node\Stmt\Class_ $class, array $modelPropertyTypes = []): array
    {
        $transformMethod = $this->astHelper->findMethodNode($class, 'transform');
        if (! $transformMethod) {
            return [];
        }

        $properties = [];

        // return文を探す
        $visitor = new class extends NodeVisitorAbstract
        {
            /** @var Node\Expr\Array_|null */
            public ?\PhpParser\Node\Expr\Array_ $returnArray = null;

            /** @var array<string, Node\Expr\Array_> */
            public array $arrayAssignments = [];

            public function enterNode(Node $node): null
            {
                if ($node instanceof Node\Expr\Assign
                    && $node->var instanceof Node\Expr\Variable
                    && is_string($node->var->name)
                    && $node->expr instanceof Node\Expr\Array_) {
                    $this->arrayAssignments[$node->var->name] = $node->expr;
                }

                if ($node instanceof Node\Stmt\Return_) {
                    if ($node->expr instanceof Node\Expr\Array_) {
                        $this->returnArray = $node->expr;
                    }

                    if ($node->expr instanceof Node\Expr\Variable
                        && is_string($node->expr->name)
                        && isset($this->arrayAssignments[$node->expr->name])) {
                        $this->returnArray = $this->arrayAssignments[$node->expr->name];
                    }
                }

                return null;
            }
        };

        $this->astHelper->traverse([$transformMethod], $visitor);

        if ($visitor->returnArray) {
            $methodStack = ['transform' => true];
            $variableTypeHints = $this->extractMethodVariableTypeHints($transformMethod, $class, $methodStack, $modelPropertyTypes);
            $properties = $this->parseArrayNode(
                $visitor->returnArray,
                $class,
                $methodStack,
                $modelPropertyTypes,
                $variableTypeHints
            );
        }

        return $properties;
    }

    /**
     * 配列ノードを解析
     *
     * @param  array<string, bool>  $methodStack
     * @param  array<string, array<string, array{type: string, format?: string}>>  $modelPropertyTypes
     * @param  array<string, array{type?: string, format?: string, properties?: array<string, mixed>, items?: array<string, mixed>}>  $variableTypeHints
     * @return array<string, array<string, mixed>>
     */
    protected function parseArrayNode(
        Node\Expr\Array_ $array,
        ?Node\Stmt\Class_ $class = null,
        array $methodStack = [],
        array $modelPropertyTypes = [],
        array $variableTypeHints = []
    ): array {
        $properties = [];

        /** @var array<int, Node\Expr\ArrayItem|null> $items */
        $items = $array->items;

        foreach ($items as $item) {
            if (! $item || ! isset($item->key)) {
                continue;
            }

            $key = $this->getNodeValue($item->key);
            if ($key === null || $key === '') {
                continue;
            }

            $value = $item->value;
            $fieldName = (string) $key;
            $typeInfo = $this->inferTypeFromExpression($value, $class, $methodStack, $modelPropertyTypes, $variableTypeHints);
            $resolvedType = $this->refineTypeFromFieldName($fieldName, $value, $typeInfo);

            $properties[$fieldName] = [
                'type' => $resolvedType['type'] ?? 'string',
                'example' => $this->generateExampleFromNode($fieldName, $value, $resolvedType['type'] ?? null),
                'nullable' => $this->isNullable($value),
            ];

            if (isset($resolvedType['format'])) {
                $properties[$fieldName]['format'] = $resolvedType['format'];
            }

            if (isset($resolvedType['items']) && is_array($resolvedType['items'])) {
                $properties[$fieldName]['items'] = $resolvedType['items'];
            }

            if (isset($resolvedType['properties']) && is_array($resolvedType['properties'])) {
                $properties[$fieldName]['properties'] = $resolvedType['properties'];
                $properties[$fieldName]['type'] = 'object';
            } elseif ($value instanceof Node\Expr\Array_ && $this->isAssociativeArray($value)) {
                $properties[$fieldName]['properties'] = $this->parseArrayNode(
                    $value,
                    $class,
                    $methodStack,
                    $modelPropertyTypes,
                    $variableTypeHints
                );
                $properties[$fieldName]['type'] = 'object';
            }
        }

        return $properties;
    }

    /**
     * @param  array{type?: string, format?: string, properties?: array<string, mixed>, items?: array<string, mixed>}  $typeInfo
     * @return array{type?: string, format?: string, properties?: array<string, mixed>, items?: array<string, mixed>}
     */
    protected function refineTypeFromFieldName(string $fieldName, Node $value, array $typeInfo): array
    {
        // Keep explicit/stronger inference as-is.
        if (($typeInfo['type'] ?? 'string') !== 'string' || isset($typeInfo['format'])) {
            return $typeInfo;
        }

        // Only refine ambiguous getter/property patterns.
        if (! $value instanceof Node\Expr\MethodCall && ! $value instanceof Node\Expr\PropertyFetch) {
            return $typeInfo;
        }

        $fieldType = $this->inferTypeFromFieldName($fieldName);
        if (($fieldType['type'] ?? 'string') === 'string' && ! isset($fieldType['format'])) {
            return $typeInfo;
        }

        return array_merge($typeInfo, [
            'type' => $fieldType['type'] ?? ($typeInfo['type'] ?? 'string'),
            'format' => $fieldType['format'] ?? ($typeInfo['format'] ?? null),
        ]);
    }

    /**
     * @param  array<string, bool>  $methodStack
     * @param  array<string, array<string, array{type: string, format?: string}>>  $modelPropertyTypes
     * @param  array<string, array{type?: string, format?: string, properties?: array<string, mixed>, items?: array<string, mixed>}>  $variableTypeHints
     * @return array{type?: string, format?: string, properties?: array<string, mixed>, items?: array<string, mixed>}
     */
    protected function inferTypeFromExpression(
        Node\Expr $expression,
        ?Node\Stmt\Class_ $class = null,
        array $methodStack = [],
        array $modelPropertyTypes = [],
        array $variableTypeHints = []
    ): array {
        if ($this->isBooleanExpression($expression)) {
            return ['type' => 'boolean'];
        }

        if ($expression instanceof Node\Expr\Variable && is_string($expression->name) && isset($variableTypeHints[$expression->name])) {
            return $variableTypeHints[$expression->name];
        }

        if ($expression instanceof Node\Expr\BinaryOp\Coalesce) {
            return $this->inferTypeFromCoalesceExpression(
                $expression,
                $class,
                $methodStack,
                $modelPropertyTypes,
                $variableTypeHints
            );
        }

        if ($expression instanceof Node\Expr\PropertyFetch) {
            $modelCastType = $this->inferTypeFromModelPropertyCast($expression, $modelPropertyTypes);
            if ($modelCastType !== null) {
                return $modelCastType;
            }
        }

        if ($class !== null && $expression instanceof Node\Expr\MethodCall) {
            $methodCallType = $this->inferTypeFromTransformerMethodCall($expression, $class, $methodStack, $modelPropertyTypes);
            if ($methodCallType !== null) {
                return $methodCallType;
            }
        }

        if ($expression instanceof Node\Expr\FuncCall) {
            $arrayMapType = $this->inferTypeFromArrayMapCall(
                $expression,
                $class,
                $methodStack,
                $modelPropertyTypes,
                $variableTypeHints
            );
            if ($arrayMapType !== null) {
                return $arrayMapType;
            }
        }

        if ($expression instanceof Node\Expr\Array_) {
            if ($this->isAssociativeArray($expression)) {
                return [
                    'type' => 'object',
                    'properties' => $this->parseArrayNode(
                        $expression,
                        $class,
                        $methodStack,
                        $modelPropertyTypes,
                        $variableTypeHints
                    ),
                ];
            }

            return ['type' => 'array'];
        }

        return $this->typeInferenceEngine->inferFromNode($expression);
    }

    /**
     * @param  array<string, bool>  $methodStack
     * @param  array<string, array<string, array{type: string, format?: string}>>  $modelPropertyTypes
     * @return array{type?: string, format?: string, properties?: array<string, mixed>, items?: array<string, mixed>}|null
     */
    protected function inferTypeFromTransformerMethodCall(Node\Expr\MethodCall $methodCall, Node\Stmt\Class_ $class, array $methodStack, array $modelPropertyTypes = []): ?array
    {
        if (! $methodCall->var instanceof Node\Expr\Variable || $methodCall->var->name !== 'this') {
            return null;
        }

        if (! $methodCall->name instanceof Node\Identifier) {
            return null;
        }

        $methodName = $methodCall->name->toString();
        if (isset($methodStack[$methodName])) {
            return null;
        }

        $method = $this->astHelper->findMethodNode($class, $methodName);
        if (! $method) {
            return null;
        }

        $returnExpression = $this->extractMethodReturnExpression($method);
        if (! $returnExpression) {
            return null;
        }

        $nextStack = $methodStack;
        $nextStack[$methodName] = true;

        $methodVariableTypeHints = $this->extractMethodVariableTypeHints($method, $class, $nextStack, $modelPropertyTypes);

        return $this->inferTypeFromExpression(
            $returnExpression,
            $class,
            $nextStack,
            $modelPropertyTypes,
            $methodVariableTypeHints
        );
    }

    /**
     * @param  array<string, array<string, array{type: string, format?: string}>>  $modelPropertyTypes
     * @return array{type: string, format?: string}|null
     */
    protected function inferTypeFromModelPropertyCast(Node\Expr\PropertyFetch $propertyFetch, array $modelPropertyTypes): ?array
    {
        if (! $propertyFetch->var instanceof Node\Expr\Variable || ! is_string($propertyFetch->var->name)) {
            return null;
        }

        if (! $propertyFetch->name instanceof Node\Identifier) {
            return null;
        }

        $variableName = $propertyFetch->var->name;
        $propertyName = $propertyFetch->name->toString();

        return $modelPropertyTypes[$variableName][$propertyName] ?? null;
    }

    protected function extractMethodReturnExpression(Node\Stmt\ClassMethod $method): ?Node\Expr
    {
        $visitor = new class extends NodeVisitorAbstract
        {
            public ?Node\Expr $returnExpression = null;

            public int $functionLikeDepth = 0;

            public function enterNode(Node $node): null
            {
                if ($node instanceof Node\Stmt\ClassMethod
                    || $node instanceof Node\Expr\Closure
                    || $node instanceof Node\Expr\ArrowFunction) {
                    $this->functionLikeDepth++;
                }

                if (! $node instanceof Node\Stmt\Return_ || ! $node->expr instanceof Node\Expr) {
                    return null;
                }

                if ($this->functionLikeDepth !== 1) {
                    return null;
                }

                $this->returnExpression = $node->expr;

                return null;
            }

            public function leaveNode(Node $node): null
            {
                if ($node instanceof Node\Stmt\ClassMethod
                    || $node instanceof Node\Expr\Closure
                    || $node instanceof Node\Expr\ArrowFunction) {
                    $this->functionLikeDepth--;
                }

                return null;
            }
        };

        $this->astHelper->traverse([$method], $visitor);

        return $visitor->returnExpression;
    }

    /**
     * @param  array<string, bool>  $methodStack
     * @param  array<string, array<string, array{type: string, format?: string}>>  $modelPropertyTypes
     * @return array<string, array{type?: string, format?: string, properties?: array<string, mixed>, items?: array<string, mixed>}>
     */
    protected function extractMethodVariableTypeHints(
        Node\Stmt\ClassMethod $method,
        ?Node\Stmt\Class_ $class = null,
        array $methodStack = [],
        array $modelPropertyTypes = []
    ): array {
        $visitor = new class extends NodeVisitorAbstract
        {
            /** @var array<string, Node\Expr> */
            public array $assignments = [];

            /** @var array<string, array<int, Node\Expr>> */
            public array $appendedValues = [];

            public function enterNode(Node $node): null
            {
                if (! $node instanceof Node\Expr\Assign) {
                    return null;
                }

                if ($node->var instanceof Node\Expr\Variable && is_string($node->var->name)) {
                    $this->assignments[$node->var->name] = $node->expr;

                    return null;
                }

                if ($node->var instanceof Node\Expr\ArrayDimFetch
                    && $node->var->dim === null
                    && $node->var->var instanceof Node\Expr\Variable
                    && is_string($node->var->var->name)) {
                    $this->appendedValues[$node->var->var->name][] = $node->expr;
                }

                return null;
            }
        };

        $this->astHelper->traverse([$method], $visitor);

        $variableTypeHints = [];

        foreach ($visitor->assignments as $variableName => $assignmentExpr) {
            if ($assignmentExpr instanceof Node\Expr\Array_ && ! $this->isAssociativeArray($assignmentExpr)) {
                $inferredArrayType = $this->inferTypeFromAppendedArrayValues(
                    $visitor->appendedValues[$variableName] ?? [],
                    $class,
                    $methodStack,
                    $modelPropertyTypes,
                    $variableTypeHints
                );

                if (isset($inferredArrayType['items'])) {
                    $variableTypeHints[$variableName] = $inferredArrayType;

                    continue;
                }
            }

            $variableTypeHints[$variableName] = $this->inferTypeFromExpression(
                $assignmentExpr,
                $class,
                $methodStack,
                $modelPropertyTypes,
                $variableTypeHints
            );
        }

        foreach ($visitor->appendedValues as $variableName => $appendedValues) {
            if (isset($variableTypeHints[$variableName])) {
                continue;
            }

            $variableTypeHints[$variableName] = $this->inferTypeFromAppendedArrayValues(
                $appendedValues,
                $class,
                $methodStack,
                $modelPropertyTypes,
                $variableTypeHints
            );
        }

        return $variableTypeHints;
    }

    /**
     * @param  array<string, bool>  $methodStack
     * @param  array<string, array<string, array{type: string, format?: string}>>  $modelPropertyTypes
     * @param  array<string, array{type?: string, format?: string, properties?: array<string, mixed>, items?: array<string, mixed>}>  $variableTypeHints
     * @return array{type?: string, format?: string, properties?: array<string, mixed>, items?: array<string, mixed>}
     */
    protected function inferTypeFromCoalesceExpression(
        Node\Expr\BinaryOp\Coalesce $expression,
        ?Node\Stmt\Class_ $class = null,
        array $methodStack = [],
        array $modelPropertyTypes = [],
        array $variableTypeHints = []
    ): array {
        $leftType = $this->inferTypeFromExpression(
            $expression->left,
            $class,
            $methodStack,
            $modelPropertyTypes,
            $variableTypeHints
        );
        $rightType = $this->inferTypeFromExpression(
            $expression->right,
            $class,
            $methodStack,
            $modelPropertyTypes,
            $variableTypeHints
        );

        if (($leftType['type'] ?? null) === 'null' || ($this->isAmbiguousStringTypeInfo($leftType) && $this->hasSpecificTypeInformation($rightType))) {
            return $rightType;
        }

        return $leftType;
    }

    /**
     * @param  array<string, bool>  $methodStack
     * @param  array<string, array<string, array{type: string, format?: string}>>  $modelPropertyTypes
     * @param  array<string, array{type?: string, format?: string, properties?: array<string, mixed>, items?: array<string, mixed>}>  $variableTypeHints
     * @return array{type?: string, format?: string, properties?: array<string, mixed>, items?: array<string, mixed>}|null
     */
    protected function inferTypeFromArrayMapCall(
        Node\Expr\FuncCall $functionCall,
        ?Node\Stmt\Class_ $class = null,
        array $methodStack = [],
        array $modelPropertyTypes = [],
        array $variableTypeHints = []
    ): ?array {
        if (! $functionCall->name instanceof Node\Name || strtolower($functionCall->name->toString()) !== 'array_map') {
            return null;
        }

        $type = ['type' => 'array'];
        if (! isset($functionCall->args[0])) {
            return $type;
        }

        $itemType = $this->inferTypeFromArrayMapCallback(
            $functionCall->args[0]->value,
            $class,
            $methodStack,
            $modelPropertyTypes,
            $variableTypeHints
        );

        if ($itemType !== null) {
            $type['items'] = $itemType;
        }

        return $type;
    }

    /**
     * @param  array<string, bool>  $methodStack
     * @param  array<string, array<string, array{type: string, format?: string}>>  $modelPropertyTypes
     * @param  array<string, array{type?: string, format?: string, properties?: array<string, mixed>, items?: array<string, mixed>}>  $variableTypeHints
     * @return array{type?: string, format?: string, properties?: array<string, mixed>, items?: array<string, mixed>}|null
     */
    protected function inferTypeFromArrayMapCallback(
        Node\Expr $callback,
        ?Node\Stmt\Class_ $class = null,
        array $methodStack = [],
        array $modelPropertyTypes = [],
        array $variableTypeHints = []
    ): ?array {
        if ($callback instanceof Node\Expr\ArrowFunction) {
            return $this->inferTypeFromExpression(
                $callback->expr,
                $class,
                $methodStack,
                $modelPropertyTypes,
                $variableTypeHints
            );
        }

        if (! $callback instanceof Node\Expr\Closure) {
            return null;
        }

        $visitor = new class extends NodeVisitorAbstract
        {
            public ?Node\Expr $returnExpression = null;

            public function enterNode(Node $node): null
            {
                if ($node instanceof Node\Stmt\Return_ && $node->expr instanceof Node\Expr) {
                    $this->returnExpression = $node->expr;
                }

                return null;
            }
        };

        $this->astHelper->traverse($callback->stmts ?? [], $visitor);
        if (! $visitor->returnExpression instanceof Node\Expr) {
            return null;
        }

        return $this->inferTypeFromExpression(
            $visitor->returnExpression,
            $class,
            $methodStack,
            $modelPropertyTypes,
            $variableTypeHints
        );
    }

    /**
     * @param  array<int, Node\Expr>  $appendedValues
     * @param  array<string, bool>  $methodStack
     * @param  array<string, array<string, array{type: string, format?: string}>>  $modelPropertyTypes
     * @param  array<string, array{type?: string, format?: string, properties?: array<string, mixed>, items?: array<string, mixed>}>  $variableTypeHints
     * @return array{type?: string, format?: string, properties?: array<string, mixed>, items?: array<string, mixed>}
     */
    protected function inferTypeFromAppendedArrayValues(
        array $appendedValues,
        ?Node\Stmt\Class_ $class = null,
        array $methodStack = [],
        array $modelPropertyTypes = [],
        array $variableTypeHints = []
    ): array {
        $result = ['type' => 'array'];
        $items = null;

        foreach ($appendedValues as $appendedValue) {
            $itemType = $this->inferTypeFromExpression(
                $appendedValue,
                $class,
                $methodStack,
                $modelPropertyTypes,
                $variableTypeHints
            );
            $items = $this->mergeArrayItemType($items, $itemType);
        }

        if ($items !== null) {
            $result['items'] = $items;
        }

        return $result;
    }

    /**
     * @param  array{type?: string, format?: string, properties?: array<string, mixed>, items?: array<string, mixed>}|null  $existingType
     * @param  array{type?: string, format?: string, properties?: array<string, mixed>, items?: array<string, mixed>}  $newType
     * @return array{type?: string, format?: string, properties?: array<string, mixed>, items?: array<string, mixed>}
     */
    protected function mergeArrayItemType(?array $existingType, array $newType): array
    {
        if ($existingType === null) {
            return $newType;
        }

        $existingTypeName = $existingType['type'] ?? 'string';
        $newTypeName = $newType['type'] ?? 'string';

        if ($existingTypeName === $newTypeName) {
            if ($existingTypeName === 'object'
                && isset($existingType['properties'], $newType['properties'])
                && is_array($existingType['properties'])
                && is_array($newType['properties'])) {
                $existingType['properties'] = array_merge($existingType['properties'], $newType['properties']);
            }

            if ($existingTypeName === 'array'
                && isset($existingType['items'], $newType['items'])
                && is_array($existingType['items'])
                && is_array($newType['items'])) {
                $existingType['items'] = $this->mergeArrayItemType($existingType['items'], $newType['items']);
            }

            if (! isset($existingType['format']) && isset($newType['format'])) {
                $existingType['format'] = $newType['format'];
            }

            return $existingType;
        }

        if ($this->isAmbiguousStringTypeInfo($existingType) && $this->hasSpecificTypeInformation($newType)) {
            return $newType;
        }

        if ($this->isAmbiguousStringTypeInfo($newType) && $this->hasSpecificTypeInformation($existingType)) {
            return $existingType;
        }

        return ['type' => 'string'];
    }

    /**
     * @param  array{type?: string, format?: string, properties?: array<string, mixed>, items?: array<string, mixed>}  $typeInfo
     */
    protected function hasSpecificTypeInformation(array $typeInfo): bool
    {
        return ! $this->isAmbiguousStringTypeInfo($typeInfo);
    }

    /**
     * @param  array{type?: string, format?: string, properties?: array<string, mixed>, items?: array<string, mixed>}  $typeInfo
     */
    protected function isAmbiguousStringTypeInfo(array $typeInfo): bool
    {
        return ($typeInfo['type'] ?? 'string') === 'string'
            && ! isset($typeInfo['format'])
            && ! isset($typeInfo['properties'])
            && ! isset($typeInfo['items']);
    }

    protected function isAssociativeArray(Node\Expr\Array_ $array): bool
    {
        foreach ($array->items as $item) {
            if ($item !== null && $item->key instanceof Node\Scalar\String_) {
                return true;
            }
        }

        return false;
    }

    protected function isBooleanExpression(Node $node): bool
    {
        return $node instanceof Node\Expr\BinaryOp\BooleanAnd
            || $node instanceof Node\Expr\BinaryOp\BooleanOr
            || $node instanceof Node\Expr\BinaryOp\LogicalAnd
            || $node instanceof Node\Expr\BinaryOp\LogicalOr
            || $node instanceof Node\Expr\BinaryOp\LogicalXor
            || $node instanceof Node\Expr\BinaryOp\Equal
            || $node instanceof Node\Expr\BinaryOp\NotEqual
            || $node instanceof Node\Expr\BinaryOp\Identical
            || $node instanceof Node\Expr\BinaryOp\NotIdentical
            || $node instanceof Node\Expr\BinaryOp\Greater
            || $node instanceof Node\Expr\BinaryOp\GreaterOrEqual
            || $node instanceof Node\Expr\BinaryOp\Smaller
            || $node instanceof Node\Expr\BinaryOp\SmallerOrEqual
            || $node instanceof Node\Expr\BooleanNot;
    }

    /**
     * @param  \ReflectionClass<\League\Fractal\TransformerAbstract>  $reflection
     * @param  array<string, string>  $useStatements
     * @return array<string, array<string, array{type: string, format?: string}>>
     */
    protected function extractTransformModelPropertyTypes(\ReflectionClass $reflection, array $useStatements = []): array
    {
        if (! $reflection->hasMethod('transform')) {
            return [];
        }

        $transformMethod = $reflection->getMethod('transform');
        $docComment = $transformMethod->getDocComment() ?: null;
        $modelPropertyTypes = [];

        foreach ($transformMethod->getParameters() as $parameter) {
            $type = $parameter->getType();
            $modelClass = null;

            if ($type instanceof \ReflectionNamedType && ! $type->isBuiltin()) {
                $modelClass = $type->getName();
            }

            if ($modelClass === null) {
                $modelClass = $this->extractModelClassFromParamDocComment(
                    $docComment,
                    $parameter->getName(),
                    $useStatements,
                    $reflection->getNamespaceName()
                );
            }

            if ($modelClass === null) {
                continue;
            }

            $castTypes = $this->extractModelCastTypes($modelClass);
            if ($castTypes !== []) {
                $modelPropertyTypes[$parameter->getName()] = $castTypes;
            }
        }

        return $modelPropertyTypes;
    }

    /**
     * @param  array<string, string>  $useStatements
     */
    protected function extractModelClassFromParamDocComment(?string $docComment, string $parameterName, array $useStatements, string $transformerNamespace): ?string
    {
        if ($docComment === null || $docComment === '') {
            return null;
        }

        $pattern = sprintf('/@param\s+([^\s]+)\s+\$%s\b/', preg_quote($parameterName, '/'));
        if (! preg_match($pattern, $docComment, $matches) || ! isset($matches[1])) {
            return null;
        }

        $declaredType = explode('|', (string) $matches[1], 2)[0];
        $declaredType = ltrim(trim($declaredType), '?');
        if ($declaredType === '') {
            return null;
        }

        $builtinTypes = [
            'array',
            'bool',
            'boolean',
            'callable',
            'float',
            'int',
            'integer',
            'iterable',
            'mixed',
            'object',
            'self',
            'static',
            'string',
        ];
        if (in_array(strtolower($declaredType), $builtinTypes, true)) {
            return null;
        }

        if (str_starts_with($declaredType, '\\')) {
            $fqcn = ltrim($declaredType, '\\');

            return class_exists($fqcn) ? $fqcn : null;
        }

        if (isset($useStatements[$declaredType])) {
            return $useStatements[$declaredType];
        }

        $namespacedClass = $transformerNamespace !== '' ? $transformerNamespace.'\\'.$declaredType : $declaredType;
        if (class_exists($namespacedClass)) {
            return $namespacedClass;
        }

        return class_exists($declaredType) ? $declaredType : null;
    }

    /**
     * @return array<string, array{type: string, format?: string}>
     */
    protected function extractModelCastTypes(string $modelClass): array
    {
        if (! class_exists($modelClass)) {
            return [];
        }

        $reflection = new \ReflectionClass($modelClass);

        $defaultProperties = $reflection->getDefaultProperties();
        $casts = $defaultProperties['casts'] ?? null;
        if (! is_array($casts)) {
            return [];
        }

        $castTypes = [];
        foreach ($casts as $property => $castDefinition) {
            if (! is_string($property) || ! is_string($castDefinition)) {
                continue;
            }

            $typeInfo = $this->mapLaravelCastToTypeInfo($castDefinition);
            if ($typeInfo !== null) {
                $castTypes[$property] = $typeInfo;
            }
        }

        return $castTypes;
    }

    /**
     * @return array{type: string, format?: string}|null
     */
    protected function mapLaravelCastToTypeInfo(string $castDefinition): ?array
    {
        $parts = explode(':', strtolower($castDefinition), 2);
        $castType = trim($parts[0]);

        if ($castType === 'encrypted' && isset($parts[1])) {
            $castType = trim($parts[1]);
        }

        if (str_contains($castType, '\\')) {
            $castType = strtolower((string) basename(str_replace('\\', '/', $castType)));
        }

        return match ($castType) {
            'int', 'integer' => ['type' => 'integer'],
            'float', 'double', 'real', 'decimal' => ['type' => 'number'],
            'bool', 'boolean' => ['type' => 'boolean'],
            'array', 'json', 'collection', 'asarrayobject', 'ascollection' => ['type' => 'array'],
            'object', 'asobject' => ['type' => 'object'],
            'date', 'immutable_date' => ['type' => 'string', 'format' => 'date'],
            'datetime', 'immutable_datetime', 'custom_datetime', 'timestamp' => ['type' => 'string', 'format' => 'date-time'],
            default => null,
        };
    }

    /**
     * @return array{type: string, format?: string}
     */
    protected function inferTypeFromFieldName(string $fieldName): array
    {
        $inference = (new FieldNameInference)->inferFieldType($fieldName);
        $semanticType = $inference['type'] ?? 'string';
        $semanticFormat = $inference['format'] ?? null;

        $typeMapping = [
            'id' => 'integer',
            'age' => 'integer',
            'quantity' => 'integer',
            'score' => 'integer',
            'money' => 'number',
            'rating' => 'number',
            'location' => 'number',
            'boolean' => 'boolean',
        ];

        $openApiType = $typeMapping[$semanticType] ?? 'string';
        $result = ['type' => $openApiType];

        if ($openApiType !== 'string' || $semanticFormat === null || $semanticFormat === 'text' || $semanticFormat === 'boolean') {
            return $result;
        }

        $formatMapping = [
            'datetime' => 'date-time',
            'date' => 'date',
            'time' => 'time',
            'email' => 'email',
            'url' => 'uri',
            'image_url' => 'uri',
            'avatar_url' => 'uri',
            'uuid' => 'uuid',
        ];

        if (isset($formatMapping[$semanticFormat])) {
            $result['format'] = $formatMapping[$semanticFormat];
        }

        return $result;
    }

    /**
     * ノードから値を取得
     */
    protected function getNodeValue(Node $node): string|int|null
    {
        if ($node instanceof Node\Scalar\String_) {
            return $node->value;
        }
        if ($node instanceof Node\Scalar\LNumber) {
            return $node->value;
        }
        if ($node instanceof Node\Identifier) {
            return $node->toString();
        }

        return null;
    }

    /**
     * nullable判定
     */
    protected function isNullable(Node $node): bool
    {
        // 三項演算子で null を返す場合
        if ($node instanceof Node\Expr\Ternary) {
            if ($node->else instanceof Node\Expr\ConstFetch) {
                $name = $node->else->name->toString();

                return strtolower($name) === 'null';
            }
        }

        // null合体演算子
        if ($node instanceof Node\Expr\BinaryOp\Coalesce) {
            return true;
        }

        return false;
    }

    /**
     * プロパティ名から例を生成
     *
     * @return int|bool|array<int, mixed>|\stdClass|string
     */
    protected function generateExampleFromNode(string $key, Node $node, ?string $resolvedType = null): int|bool|array|\stdClass|string
    {
        $type = $resolvedType ?? $this->typeInferenceEngine->inferTypeString($node);

        switch ($type) {
            case 'integer':
                if (strpos($key, 'id') !== false) {
                    return 1;
                }
                if (strpos($key, 'count') !== false) {
                    return 100;
                }

                return 42;

            case 'boolean':
                return true;

            case 'array':
                return [];

            case 'object':
                return new \stdClass;

            default:
                // 文字列の場合、キー名から適切な例を生成
                if ($key === 'email') {
                    return 'user@example.com';
                }
                if ($key === 'name') {
                    return 'John Doe';
                }
                if ($key === 'title') {
                    return 'Sample Title';
                }
                if ($key === 'body') {
                    return 'Sample body text';
                }
                if ($key === 'status') {
                    return 'active';
                }
                if ($key === 'type') {
                    return 'default';
                }
                if (strpos($key, 'url') !== false) {
                    return 'https://example.com';
                }
                if (strpos($key, '_at') !== false) {
                    return '2024-01-01T00:00:00+00:00';
                }

                return 'string';
        }
    }

    /**
     * availableIncludesプロパティを抽出
     *
     * @return array<string, array<string, mixed>>
     */
    protected function extractAvailableIncludes(Node\Stmt\Class_ $class): array
    {
        $defaultValue = $this->getPropertyDefaultArray($class, 'availableIncludes');
        if (! $defaultValue) {
            return [];
        }

        $includes = [];
        /** @var array<int, Node\Expr\ArrayItem|null> $items */
        $items = $defaultValue->items;
        foreach ($items as $item) {
            if ($item && isset($item->value) && $item->value instanceof Node\Scalar\String_) {
                $includeName = $item->value->value;
                $includes[$includeName] = $this->analyzeIncludeMethod($class, $includeName);
            }
        }

        return $includes;
    }

    /**
     * defaultIncludesプロパティを抽出
     *
     * @return array<int, string>
     */
    protected function extractDefaultIncludes(Node\Stmt\Class_ $class): array
    {
        $defaultValue = $this->getPropertyDefaultArray($class, 'defaultIncludes');
        if (! $defaultValue) {
            return [];
        }

        $includes = [];
        /** @var array<int, Node\Expr\ArrayItem|null> $items */
        $items = $defaultValue->items;
        foreach ($items as $item) {
            if ($item && isset($item->value) && $item->value instanceof Node\Scalar\String_) {
                $includes[] = $item->value->value;
            }
        }

        return $includes;
    }

    /**
     * プロパティのデフォルト配列値を取得
     *
     * @param  Node\Stmt\Class_  $class  The class node to search within
     * @param  string  $propertyName  The name of the property to find
     * @return Node\Expr\Array_|null The default array value or null if not found
     */
    protected function getPropertyDefaultArray(Node\Stmt\Class_ $class, string $propertyName): ?Node\Expr\Array_
    {
        $propertyStmt = $this->astHelper->findPropertyNode($class, $propertyName);
        if (! $propertyStmt) {
            return null;
        }

        // Find the property item with the matching name and array default value
        foreach ($propertyStmt->props as $prop) {
            if ($prop->name->toString() === $propertyName && $prop->default instanceof Node\Expr\Array_) {
                return $prop->default;
            }
        }

        return null;
    }

    /**
     * include{Name}メソッドを解析
     *
     * @return array<string, mixed>
     */
    protected function analyzeIncludeMethod(Node\Stmt\Class_ $class, string $includeName): array
    {
        $methodName = 'include'.Str::studly($includeName);
        $method = $this->astHelper->findMethodNode($class, $methodName);

        if (! $method) {
            return ['type' => 'unknown'];
        }

        // メソッドの戻り値を解析
        $returnType = $this->analyzeIncludeReturnType($method);

        return [
            'type' => $returnType['type'] ?? 'object',
            'transformer' => $returnType['transformer'] ?? null,
            'collection' => $returnType['collection'] ?? false,
        ];
    }

    /**
     * includeメソッドの戻り値を解析
     *
     * @return array<string, mixed>
     */
    protected function analyzeIncludeReturnType(Node\Stmt\ClassMethod $method): array
    {
        $visitor = new class extends NodeVisitorAbstract
        {
            /** @var array<string, mixed> */
            public array $returnInfo = [];

            public function enterNode(Node $node): null
            {
                if ($node instanceof Node\Stmt\Return_ && $node->expr instanceof Node\Expr\MethodCall) {
                    $methodName = $node->expr->name instanceof Node\Identifier ?
                        $node->expr->name->toString() : '';

                    if ($methodName === 'item') {
                        $this->returnInfo['type'] = 'object';
                        $this->returnInfo['collection'] = false;
                    } elseif ($methodName === 'collection') {
                        $this->returnInfo['type'] = 'array';
                        $this->returnInfo['collection'] = true;
                    } elseif ($methodName === 'null') {
                        $this->returnInfo['type'] = 'null';
                        $this->returnInfo['collection'] = false;
                    }

                    // Transformerクラスを取得
                    if (isset($node->expr->args[1]) &&
                        $node->expr->args[1]->value instanceof Node\Expr\New_) {
                        $class = $node->expr->args[1]->value->class;
                        if ($class instanceof Node\Name) {
                            $this->returnInfo['transformer'] = $class->getLast();
                        }
                    }
                }

                return null;
            }
        };

        $this->astHelper->traverse([$method], $visitor);

        return $visitor->returnInfo;
    }

    /**
     * メタデータを抽出（将来の拡張用）
     *
     * @return array<string, mixed>
     */
    protected function extractMetaData(Node\Stmt\Class_ $class): array
    {
        // 現在は空配列を返す
        // 将来的にはメタデータ関連のメソッドを解析
        return [];
    }
}
