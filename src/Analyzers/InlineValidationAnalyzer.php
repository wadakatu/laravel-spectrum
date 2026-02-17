<?php

declare(strict_types=1);

namespace LaravelSpectrum\Analyzers;

use LaravelSpectrum\Contracts\HasErrors;
use LaravelSpectrum\DTO\FileUploadInfo;
use LaravelSpectrum\DTO\InlineParameterInfo;
use LaravelSpectrum\DTO\InlineValidationInfo;
use LaravelSpectrum\Support\AnalyzerErrorType;
use LaravelSpectrum\Support\ErrorCollector;
use LaravelSpectrum\Support\FileSizeFormatter;
use LaravelSpectrum\Support\HasErrorCollection;
use LaravelSpectrum\Support\TypeInference;
use LaravelSpectrum\Support\ValidationRules;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use ReflectionClass;
use ReflectionMethod;

/**
 * @phpstan-type ValidationRulesArray array<string, string|array<int, string>>
 * @phpstan-type MessagesArray array<string, string>
 * @phpstan-type AttributesArray array<string, string>
 * @phpstan-type UseStatementsArray array<string, string>
 * @phpstan-type FileInfoArray array{
 *     type?: string,
 *     is_image?: bool,
 *     mimes?: array<int, string>,
 *     mime_types?: array<int, string>,
 *     max_size?: int|null,
 *     min_size?: int|null,
 *     dimensions?: array<string, int|float|string>,
 *     multiple?: bool
 * }
 * @phpstan-type ValidationEntry array{type: string, rules: ValidationRulesArray, messages: MessagesArray, attributes: AttributesArray}
 */
class InlineValidationAnalyzer implements HasErrors
{
    use HasErrorCollection;

    protected TypeInference $typeInference;

    protected EnumAnalyzer $enumAnalyzer;

    protected FileUploadAnalyzer $fileUploadAnalyzer;

    protected Parser $parser;

    protected PrettyPrinter\Standard $printer;

    public function __construct(TypeInference $typeInference, ?EnumAnalyzer $enumAnalyzer = null, ?FileUploadAnalyzer $fileUploadAnalyzer = null, ?ErrorCollector $errorCollector = null)
    {
        $this->initializeErrorCollector($errorCollector);
        $this->typeInference = $typeInference;
        $this->enumAnalyzer = $enumAnalyzer ?? new EnumAnalyzer;
        $this->fileUploadAnalyzer = $fileUploadAnalyzer ?? new FileUploadAnalyzer;
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
        $this->printer = new PrettyPrinter\Standard;
    }

    /**
     * メソッド内のインラインバリデーションを解析
     *
     * @param  ReflectionClass<object>|null  $contextClass
     */
    public function analyze(Node\Stmt\ClassMethod $method, ?ReflectionClass $contextClass = null): ?InlineValidationInfo
    {
        try {
            $visitor = new class extends NodeVisitorAbstract
            {
                /** @var array<string, mixed> */
                public array $validations = [];

                public function enterNode(Node $node): null
                {
                    // $this->validate() の呼び出しを検出
                    if ($node instanceof Node\Expr\MethodCall &&
                        $node->var instanceof Node\Expr\Variable &&
                        $node->var->name === 'this' &&
                        $node->name instanceof Node\Identifier &&
                        $node->name->name === 'validate') {

                        $this->extractValidation($node);

                        return null;
                    }

                    // request()->validate() の呼び出しを検出
                    if ($node instanceof Node\Expr\MethodCall &&
                        $node->var instanceof Node\Expr\FuncCall &&
                        $node->var->name instanceof Node\Name &&
                        $node->var->name->toString() === 'request' &&
                        $node->name instanceof Node\Identifier &&
                        $node->name->name === 'validate') {

                        $this->extractRequestValidation($node);

                        return null;
                    }

                    // $request->validate() with anonymous FormRequest
                    // e.g., $request->validate((new class extends FormRequest { ... })->rules())
                    if ($node instanceof Node\Expr\MethodCall &&
                        $node->name instanceof Node\Identifier &&
                        $node->name->name === 'validate' &&
                        count($node->args) >= 1 &&
                        $node->args[0]->value instanceof Node\Expr\MethodCall) {

                        $innerCall = $node->args[0]->value;
                        if ($innerCall->var instanceof Node\Expr\New_ &&
                            $innerCall->var->class instanceof Node\Stmt\Class_ &&
                            $innerCall->name instanceof Node\Identifier &&
                            $innerCall->name->name === 'rules') {

                            // Check if anonymous class extends FormRequest
                            if ($innerCall->var->class->extends) {
                                $extendedClass = $innerCall->var->class->extends->toString();
                                if (str_ends_with($extendedClass, 'FormRequest')) {
                                    $this->extractAnonymousFormRequestValidation($innerCall->var->class);

                                    return null;
                                }
                            }
                        }
                    }

                    // $request->validate() の呼び出しを検出 (変数経由)
                    if ($node instanceof Node\Expr\MethodCall &&
                        $node->var instanceof Node\Expr\Variable &&
                        $node->name instanceof Node\Identifier &&
                        $node->name->name === 'validate' &&
                        $this->isRequestVariable($node->var)) {

                        $this->extractRequestVariableValidation($node);

                        return null;
                    }

                    // Validator::make() の呼び出しも検出
                    if ($node instanceof Node\Expr\StaticCall &&
                        $node->class instanceof Node\Name &&
                        in_array($node->class->toString(), ['Validator', '\\Validator', 'Illuminate\\Support\\Facades\\Validator']) &&
                        $node->name instanceof Node\Identifier &&
                        $node->name->name === 'make') {

                        $this->extractValidatorMake($node);

                        return null;
                    }

                    return null;
                }

                protected function extractValidation(Node\Expr\MethodCall $node): void
                {
                    if (count($node->args) < 2) {
                        return;
                    }

                    // 第2引数がルール配列
                    $rulesArg = $node->args[1]->value;

                    // 第3引数がカスタムメッセージ（オプション）
                    $messagesArg = isset($node->args[2]) ? $node->args[2]->value : null;

                    // 第4引数がカスタム属性名（オプション）
                    $attributesArg = isset($node->args[3]) ? $node->args[3]->value : null;

                    $validation = [
                        'type' => 'inline',
                        'rules' => $this->extractRulesArray($rulesArg),
                        'messages' => $messagesArg ? $this->extractArray($messagesArg) : [],
                        'attributes' => $attributesArg ? $this->extractArray($attributesArg) : [],
                    ];

                    if (! empty($validation['rules'])) {
                        $this->validations[] = $validation;
                    }
                }

                protected function extractRequestValidation(Node\Expr\MethodCall $node): void
                {
                    if (count($node->args) < 1) {
                        return;
                    }

                    // 第1引数がルール配列
                    $rulesArg = $node->args[0]->value;

                    // 第2引数がカスタムメッセージ（オプション）
                    $messagesArg = isset($node->args[1]) ? $node->args[1]->value : null;

                    // 第3引数がカスタム属性名（オプション）
                    $attributesArg = isset($node->args[2]) ? $node->args[2]->value : null;

                    $validation = [
                        'type' => 'request_validate',
                        'rules' => $this->extractRulesArray($rulesArg),
                        'messages' => $messagesArg ? $this->extractArray($messagesArg) : [],
                        'attributes' => $attributesArg ? $this->extractArray($attributesArg) : [],
                    ];

                    if (! empty($validation['rules'])) {
                        $this->validations[] = $validation;
                    }
                }

                protected function extractRequestVariableValidation(Node\Expr\MethodCall $node): void
                {
                    if (count($node->args) < 1) {
                        return;
                    }

                    // 第1引数がルール配列
                    $rulesArg = $node->args[0]->value;

                    // 第2引数がカスタムメッセージ（オプション）
                    $messagesArg = isset($node->args[1]) ? $node->args[1]->value : null;

                    // 第3引数がカスタム属性名（オプション）
                    $attributesArg = isset($node->args[2]) ? $node->args[2]->value : null;

                    $validation = [
                        'type' => 'request_variable_validate',
                        'rules' => $this->extractRulesArray($rulesArg),
                        'messages' => $messagesArg ? $this->extractArray($messagesArg) : [],
                        'attributes' => $attributesArg ? $this->extractArray($attributesArg) : [],
                    ];

                    if (! empty($validation['rules'])) {
                        $this->validations[] = $validation;
                    }
                }

                /**
                 * Extract validation from anonymous FormRequest class
                 */
                protected function extractAnonymousFormRequestValidation(Node\Stmt\Class_ $classNode): void
                {
                    // Find the rules() method in the anonymous class
                    $rulesMethod = null;
                    $messagesMethod = null;
                    $attributesMethod = null;

                    foreach ($classNode->getMethods() as $method) {
                        if ($method->name->toString() === 'rules') {
                            $rulesMethod = $method;
                        } elseif ($method->name->toString() === 'messages') {
                            $messagesMethod = $method;
                        } elseif ($method->name->toString() === 'attributes') {
                            $attributesMethod = $method;
                        }
                    }

                    if ($rulesMethod === null) {
                        return;
                    }

                    // Extract rules from the rules() method
                    $rules = $this->extractReturnedArray($rulesMethod);
                    $messages = $messagesMethod ? $this->extractReturnedArray($messagesMethod) : [];
                    $attributes = $attributesMethod ? $this->extractReturnedArray($attributesMethod) : [];

                    $validation = [
                        'type' => 'anonymous_form_request',
                        'rules' => $rules,
                        'messages' => $messages,
                        'attributes' => $attributes,
                    ];

                    if (! empty($validation['rules'])) {
                        $this->validations[] = $validation;
                    }
                }

                /**
                 * Extract array from return statement in a method
                 *
                 * @return array<string, string|array<int, string>>
                 */
                private function extractReturnedArray(Node\Stmt\ClassMethod $method): array
                {
                    $returnedArray = [];

                    $visitor = new class extends NodeVisitorAbstract
                    {
                        /** @var array<string, mixed> */
                        public array $returnedArray = [];

                        public function enterNode(Node $node): ?int
                        {
                            if ($node instanceof Node\Stmt\Return_ &&
                                $node->expr instanceof Node\Expr\Array_) {
                                $this->returnedArray = $this->extractArray($node->expr);

                                return NodeTraverser::STOP_TRAVERSAL;
                            }

                            return null;
                        }

                        /** @return array<string, string|array<int, string>> */
                        protected function extractArray(Node\Expr\Array_ $node): array
                        {
                            $array = [];
                            foreach ($node->items as $item) {
                                if (! $item->key) {
                                    continue;
                                }

                                $key = $this->getNodeValue($item->key);
                                $value = $this->extractValue($item->value);

                                if ($key && $value !== null) {
                                    $array[$key] = $value;
                                }
                            }

                            return $array;
                        }

                        /** @return string|array<int, string>|null */
                        protected function extractValue(Node $node): string|array|null
                        {
                            if ($node instanceof Node\Scalar\String_) {
                                return $node->value;
                            } elseif ($node instanceof Node\Expr\Array_) {
                                $ruleArray = [];
                                foreach ($node->items as $ruleItem) {
                                    if ($ruleItem->value instanceof Node\Scalar\String_) {
                                        $ruleArray[] = $ruleItem->value->value;
                                    } elseif ($ruleItem->value instanceof Node\Expr\StaticCall ||
                                             $ruleItem->value instanceof Node\Expr\New_) {
                                        $printer = new \PhpParser\PrettyPrinter\Standard;
                                        $ruleArray[] = $printer->prettyPrintExpr($ruleItem->value);
                                    }
                                }

                                return $ruleArray;
                            } elseif ($node instanceof Node\Expr\BinaryOp\Concat) {
                                $printer = new \PhpParser\PrettyPrinter\Standard;

                                return $printer->prettyPrintExpr($node);
                            }

                            return null;
                        }

                        protected function getNodeValue(Node $node): string|int|float|null
                        {
                            if ($node instanceof Node\Scalar\String_) {
                                return $node->value;
                            } elseif ($node instanceof Node\Scalar\LNumber) {
                                return $node->value;
                            } elseif ($node instanceof Node\Scalar\DNumber) {
                                return $node->value;
                            }

                            return null;
                        }
                    };

                    $traverser = new NodeTraverser;
                    $traverser->addVisitor($visitor);
                    $traverser->traverse([$method]);

                    return $visitor->returnedArray;
                }

                /**
                 * 変数がRequestインスタンスかどうかを判定
                 */
                protected function isRequestVariable(Node\Expr\Variable $var): bool
                {
                    // 一般的なRequest変数名をチェック
                    $commonRequestVarNames = [
                        'request', 'req', 'httpRequest', 'r',
                        'input', 'data', 'requestData',
                    ];

                    if (in_array($var->name, $commonRequestVarNames)) {
                        return true;
                    }

                    // TODO: より高度な型推論を実装する場合は、
                    // メソッドの引数の型ヒントをチェックするロジックを追加

                    return false;
                }

                protected function extractValidatorMake(Node\Expr\StaticCall $node): void
                {
                    if (count($node->args) < 2) {
                        return;
                    }

                    // 第2引数がルール配列
                    $rulesArg = $node->args[1]->value;

                    // 第3引数がカスタムメッセージ（オプション）
                    $messagesArg = isset($node->args[2]) ? $node->args[2]->value : null;

                    $validation = [
                        'type' => 'validator_make',
                        'rules' => $this->extractRulesArray($rulesArg),
                        'messages' => $messagesArg ? $this->extractArray($messagesArg) : [],
                        'attributes' => [],
                    ];

                    if (! empty($validation['rules'])) {
                        $this->validations[] = $validation;
                    }
                }

                /** @return array<string, string|array<int, string>> */
                protected function extractRulesArray(Node $node): array
                {
                    if (! $node instanceof Node\Expr\Array_) {
                        return [];
                    }

                    $rules = [];

                    foreach ($node->items as $item) {
                        if (! $item->key) {
                            continue;
                        }

                        $key = $this->getNodeValue($item->key);
                        $value = $item->value;

                        // ルールが文字列の場合
                        if ($value instanceof Node\Scalar\String_) {
                            $rules[$key] = $value->value;
                        }
                        // 連結演算子の場合
                        elseif ($value instanceof Node\Expr\BinaryOp\Concat) {
                            $printer = new \PhpParser\PrettyPrinter\Standard;
                            $rules[$key] = $printer->prettyPrintExpr($value);
                        }
                        // ルールが配列の場合
                        elseif ($value instanceof Node\Expr\Array_) {
                            $ruleArray = [];
                            foreach ($value->items as $ruleItem) {
                                if ($ruleItem->value instanceof Node\Scalar\String_) {
                                    $ruleArray[] = $ruleItem->value->value;
                                }
                                // Handle Rule::enum() or new Enum() instances
                                elseif ($ruleItem->value instanceof Node\Expr\StaticCall ||
                                        $ruleItem->value instanceof Node\Expr\New_) {
                                    // Convert AST node to string representation
                                    $printer = new \PhpParser\PrettyPrinter\Standard;
                                    $ruleArray[] = $printer->prettyPrintExpr($ruleItem->value);
                                }
                                // Handle Closure validation rules
                                elseif ($ruleItem->value instanceof Node\Expr\Closure ||
                                        $ruleItem->value instanceof Node\Expr\ArrowFunction) {
                                    // Mark closure as custom rule
                                    $ruleArray[] = 'custom:closure_validation';
                                }
                            }
                            $rules[$key] = $ruleArray;
                        }
                    }

                    return $rules;
                }

                /** @return array<string, string|int|float> */
                protected function extractArray(Node $node): array
                {
                    if (! $node instanceof Node\Expr\Array_) {
                        return [];
                    }

                    $array = [];

                    foreach ($node->items as $item) {
                        if (! $item->key) {
                            continue;
                        }

                        $key = $this->getNodeValue($item->key);
                        $value = $this->getNodeValue($item->value);

                        if ($key && $value) {
                            $array[$key] = $value;
                        }
                    }

                    return $array;
                }

                protected function getNodeValue(Node $node): string|int|float|null
                {
                    if ($node instanceof Node\Scalar\String_) {
                        return $node->value;
                    } elseif ($node instanceof Node\Scalar\LNumber) {
                        return $node->value;
                    } elseif ($node instanceof Node\Scalar\DNumber) {
                        return $node->value;
                    }

                    return null;
                }
            };

            $traverser = new NodeTraverser;
            $traverser->addVisitor($visitor);
            $traverser->traverse([$method]);

            foreach ($this->extractCustomStaticValidationEntries($method, $contextClass) as $entry) {
                $visitor->validations[] = $entry;
            }

            // 複数のバリデーションがある場合はマージ
            return $this->mergeValidations($visitor->validations);
        } catch (\Exception $e) {
            $this->logException($e, AnalyzerErrorType::InlineValidationError, [
                'method' => $method->name->toString(),
            ]);

            return null;
        }
    }

    /**
     * @param  ReflectionClass<object>|null  $contextClass
     * @return array<int, ValidationEntry>
     */
    protected function extractCustomStaticValidationEntries(Node\Stmt\ClassMethod $method, ?ReflectionClass $contextClass): array
    {
        $visitor = new class extends NodeVisitorAbstract
        {
            /** @var array<int, string> */
            public array $calledClasses = [];

            public function enterNode(Node $node): null
            {
                if (! $node instanceof Node\Expr\StaticCall) {
                    return null;
                }

                if (! $node->class instanceof Node\Name || ! $node->name instanceof Node\Identifier) {
                    return null;
                }

                if ($node->name->toString() !== 'validation') {
                    return null;
                }

                $this->calledClasses[] = $node->class->toString();

                return null;
            }
        };

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse([$method]);

        $entries = [];
        $processedClasses = [];

        foreach ($visitor->calledClasses as $calledClass) {
            $resolvedClass = $this->resolveValidationClassName($calledClass, $contextClass);
            if ($resolvedClass === null || in_array($resolvedClass, $processedClasses, true)) {
                continue;
            }

            $processedClasses[] = $resolvedClass;
            $entry = $this->extractValidationEntryFromClass($resolvedClass);

            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @param  ReflectionClass<object>|null  $contextClass
     */
    protected function resolveValidationClassName(string $className, ?ReflectionClass $contextClass): ?string
    {
        $normalizedClassName = ltrim($className, '\\');
        $lowerClassName = strtolower($normalizedClassName);

        if (in_array($lowerClassName, ['self', 'static'], true)) {
            return $contextClass?->getName();
        }

        if ($lowerClassName === 'parent') {
            return $contextClass?->getParentClass()?->getName() ?: null;
        }

        if (class_exists($normalizedClassName)) {
            return $normalizedClassName;
        }

        if ($contextClass === null) {
            return null;
        }

        $namespace = $contextClass->getNamespaceName();
        if ($namespace !== '') {
            $candidate = $namespace.'\\'.$normalizedClassName;
            if (class_exists($candidate)) {
                return $candidate;
            }
        }

        $useStatements = $this->extractUseStatements($contextClass);

        if (isset($useStatements[$normalizedClassName]) && class_exists($useStatements[$normalizedClassName])) {
            return $useStatements[$normalizedClassName];
        }

        if (str_contains($normalizedClassName, '\\')) {
            [$alias, $suffix] = explode('\\', $normalizedClassName, 2);
            if (isset($useStatements[$alias])) {
                $candidate = $useStatements[$alias].'\\'.$suffix;
                if (class_exists($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * @param  ReflectionClass<object>  $contextClass
     * @return array<string, string>
     */
    protected function extractUseStatements(ReflectionClass $contextClass): array
    {
        $fileName = $contextClass->getFileName();
        if (! is_string($fileName) || ! file_exists($fileName)) {
            return [];
        }

        $content = file_get_contents($fileName);
        if ($content === false) {
            return [];
        }

        if (! preg_match_all('/^use\s+([^;]+);/m', $content, $matches)) {
            return [];
        }

        $useStatements = [];

        foreach ($matches[1] as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }

            if (preg_match('/^(.+)\s+as\s+(\w+)$/i', $statement, $parts)) {
                $fullyQualifiedClassName = trim($parts[1]);
                $alias = trim($parts[2]);
            } else {
                $fullyQualifiedClassName = $statement;
                $alias = basename(str_replace('\\', '/', $statement));
            }

            $useStatements[$alias] = ltrim($fullyQualifiedClassName, '\\');
        }

        return $useStatements;
    }

    /**
     * @return ValidationEntry|null
     */
    protected function extractValidationEntryFromClass(string $className): ?array
    {
        if (! class_exists($className)) {
            return null;
        }

        $classReflection = new ReflectionClass($className);
        if (! $classReflection->hasMethod('validation')) {
            return null;
        }

        $validationMethod = $classReflection->getMethod('validation');
        if (! $validationMethod->isStatic()) {
            return null;
        }

        $methodNode = $this->findClassMethodNode($validationMethod);
        if ($methodNode === null) {
            return null;
        }

        return $this->extractValidationEntryFromMethodNode($methodNode);
    }

    protected function findClassMethodNode(ReflectionMethod $methodReflection): ?Node\Stmt\ClassMethod
    {
        $fileName = $methodReflection->getFileName();
        if (! is_string($fileName) || ! file_exists($fileName)) {
            return null;
        }

        $code = file_get_contents($fileName);
        if ($code === false) {
            return null;
        }

        try {
            $ast = $this->parser->parse($code);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($ast)) {
            return null;
        }

        $targetStartLine = $methodReflection->getStartLine();
        $targetMethodName = $methodReflection->getName();

        $visitor = new class($targetMethodName, $targetStartLine) extends NodeVisitorAbstract
        {
            public ?Node\Stmt\ClassMethod $methodNode = null;

            public function __construct(
                private readonly string $targetMethodName,
                private readonly int $targetStartLine
            ) {}

            public function enterNode(Node $node): ?int
            {
                if (! $node instanceof Node\Stmt\ClassMethod) {
                    return null;
                }

                if (
                    $node->name->toString() === $this->targetMethodName &&
                    $node->getStartLine() === $this->targetStartLine
                ) {
                    $this->methodNode = $node;

                    return NodeTraverser::STOP_TRAVERSAL;
                }

                return null;
            }
        };

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->methodNode;
    }

    /**
     * @return ValidationEntry|null
     */
    protected function extractValidationEntryFromMethodNode(Node\Stmt\ClassMethod $methodNode): ?array
    {
        $visitor = new class extends NodeVisitorAbstract
        {
            public ?Node\Expr\StaticCall $validatorMakeCall = null;

            public function enterNode(Node $node): ?int
            {
                if (! $node instanceof Node\Expr\StaticCall) {
                    return null;
                }

                if (! $node->class instanceof Node\Name || ! $node->name instanceof Node\Identifier) {
                    return null;
                }

                if ($node->name->toString() !== 'make') {
                    return null;
                }

                $className = ltrim($node->class->toString(), '\\');
                if (! in_array($className, ['Validator', 'Illuminate\\Support\\Facades\\Validator'], true)) {
                    return null;
                }

                $this->validatorMakeCall = $node;

                return NodeTraverser::STOP_TRAVERSAL;
            }
        };

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse([$methodNode]);

        $validatorMakeCall = $visitor->validatorMakeCall;
        if ($validatorMakeCall === null || count($validatorMakeCall->args) < 2) {
            return null;
        }

        $rules = $this->extractRulesArray($validatorMakeCall->args[1]->value);
        if ($rules === []) {
            return null;
        }

        $messages = [];
        if (isset($validatorMakeCall->args[2])) {
            $messages = $this->extractScalarArray($validatorMakeCall->args[2]->value);
        }

        return [
            'type' => 'custom_static_validation',
            'rules' => $rules,
            'messages' => $messages,
            'attributes' => [],
        ];
    }

    /**
     * @return ValidationRulesArray
     */
    protected function extractRulesArray(Node $node): array
    {
        if (! $node instanceof Node\Expr\Array_) {
            return [];
        }

        $rules = [];

        foreach ($node->items as $item) {
            if ($item === null || $item->key === null) {
                continue;
            }

            $key = $this->getNodeScalarValue($item->key);
            if (! is_string($key)) {
                continue;
            }

            $value = $item->value;
            if ($value instanceof Node\Scalar\String_) {
                $rules[$key] = $value->value;

                continue;
            }

            if ($value instanceof Node\Expr\BinaryOp\Concat) {
                $rules[$key] = $this->printer->prettyPrintExpr($value);

                continue;
            }

            if (! $value instanceof Node\Expr\Array_) {
                continue;
            }

            $ruleArray = [];
            foreach ($value->items as $ruleItem) {
                if ($ruleItem === null) {
                    continue;
                }

                if ($ruleItem->value instanceof Node\Scalar\String_) {
                    $ruleArray[] = $ruleItem->value->value;
                } elseif (
                    $ruleItem->value instanceof Node\Expr\StaticCall ||
                    $ruleItem->value instanceof Node\Expr\New_
                ) {
                    $ruleArray[] = $this->printer->prettyPrintExpr($ruleItem->value);
                } elseif (
                    $ruleItem->value instanceof Node\Expr\Closure ||
                    $ruleItem->value instanceof Node\Expr\ArrowFunction
                ) {
                    $ruleArray[] = 'custom:closure_validation';
                }
            }

            $rules[$key] = $ruleArray;
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function extractScalarArray(Node $node): array
    {
        if (! $node instanceof Node\Expr\Array_) {
            return [];
        }

        $array = [];
        foreach ($node->items as $item) {
            if ($item === null || $item->key === null) {
                continue;
            }

            $key = $this->getNodeScalarValue($item->key);
            $value = $this->getNodeScalarValue($item->value);

            if (is_string($key) && is_string($value)) {
                $array[$key] = $value;
            }
        }

        return $array;
    }

    protected function getNodeScalarValue(Node $node): string|int|float|null
    {
        if ($node instanceof Node\Scalar\String_) {
            return $node->value;
        }

        if ($node instanceof Node\Scalar\LNumber) {
            return $node->value;
        }

        if ($node instanceof Node\Scalar\DNumber) {
            return $node->value;
        }

        return null;
    }

    /**
     * 複数のバリデーションをマージ
     *
     * @param  array<int, ValidationEntry>  $validations
     */
    protected function mergeValidations(array $validations): ?InlineValidationInfo
    {
        if (empty($validations)) {
            return null;
        }

        // 最初のバリデーションをベースにする
        $merged = [
            'rules' => [],
            'messages' => [],
            'attributes' => [],
        ];

        foreach ($validations as $validation) {
            // ルールをマージ
            foreach ($validation['rules'] as $field => $rule) {
                $merged['rules'][$field] = $rule;
            }

            // メッセージをマージ
            foreach ($validation['messages'] as $key => $message) {
                $merged['messages'][$key] = $message;
            }

            // 属性をマージ
            foreach ($validation['attributes'] as $field => $attribute) {
                $merged['attributes'][$field] = $attribute;
            }
        }

        return new InlineValidationInfo(
            rules: $merged['rules'],
            messages: $merged['messages'],
            attributes: $merged['attributes'],
        );
    }

    /**
     * バリデーションルールからパラメータ情報を生成（配列を返す - 後方互換性のため）
     *
     * @param  array<string, mixed>  $validation
     * @param  array<string, string>  $useStatements
     * @return array<array<string, mixed>>
     */
    public function generateParameters(array $validation, ?string $namespace = null, array $useStatements = []): array
    {
        return array_map(
            fn (InlineParameterInfo $param) => $param->toArray(),
            $this->generateParametersToResult($validation, $namespace, $useStatements)
        );
    }

    /**
     * バリデーションルールからInlineParameterInfo DTOの配列を生成
     *
     * @param  array<string, mixed>  $validation
     * @param  array<string, string>  $useStatements
     * @return array<InlineParameterInfo>
     */
    public function generateParametersToResult(array $validation, ?string $namespace = null, array $useStatements = []): array
    {
        $parameters = [];

        if (! isset($validation['rules']) || ! is_array($validation['rules'])) {
            return [];
        }

        // Analyze file upload fields (returns FileUploadInfo DTOs)
        $fileFields = $this->fileUploadAnalyzer->analyzeRulesToResult($validation['rules']);

        foreach ($validation['rules'] as $field => $rules) {
            // Check if this is a file upload field
            if (isset($fileFields[$field])) {
                $fileInfo = $fileFields[$field];
                $rulesList = is_array($rules) ? $rules : explode('|', $rules);

                $parameters[] = new InlineParameterInfo(
                    name: $field,
                    type: 'file',
                    required: in_array('required', $rulesList) || $this->hasRequiredIf($rulesList),
                    rules: $rules,
                    description: $this->generateFileDescription($field, $fileInfo->toArray(), $validation['attributes'] ?? []),
                    format: 'binary',
                    fileInfo: $fileInfo,
                );

                continue;
            }

            // Check if this is a concatenated string expression
            $isConcatenated = is_string($rules) && preg_match('/[\'"].*\|.*[\'"].*\..*::class/', $rules);

            // ルールを正規化（文字列でも配列でも処理できるように）
            if ($isConcatenated) {
                // For concatenated strings, check the whole expression first
                $rulesList = [$rules];
            } else {
                $rulesList = is_array($rules) ? $rules : explode('|', $rules);
            }

            // Check for enum rules
            $enumInfo = null;
            foreach ($rulesList as $singleRule) {
                $enumResult = $this->enumAnalyzer->analyzeValidationRule($singleRule, $namespace, $useStatements);
                if ($enumResult) {
                    $enumInfo = $enumResult;
                    break;
                }
            }

            // If concatenated and we found enum, extract other rules from the string part
            if ($isConcatenated && $enumInfo) {
                // Extract the string part before concatenation
                if (preg_match('/[\'"]([^\'"]*)[\'"]\s*\./', $rules, $matches)) {
                    $stringPart = $matches[1];
                    $additionalRules = explode('|', $stringPart);
                    // Merge with the full rule for other processing
                    $rulesList = array_merge($additionalRules, [$rules]);
                }
            } elseif (! $isConcatenated) {
                // Normal processing for non-concatenated rules
                $rulesList = is_array($rules) ? $rules : explode('|', $rules);
            }

            $type = $this->typeInference->inferFromRules($rulesList);
            $format = $this->inferFormat($rulesList);

            // Build constraints from validation rules
            $minLength = null;
            $maxLength = null;
            $minimum = null;
            $maximum = null;
            $inlineEnum = null;
            $hasInvalidInlineEnum = false;

            foreach ($rulesList as $rule) {
                if (is_string($rule) && strpos($rule, ':') !== false) {
                    [$ruleName, $ruleValue] = explode(':', $rule, 2);

                    switch ($ruleName) {
                        case 'min':
                            if ($type === 'string') {
                                $minLength = (int) $ruleValue;
                            } else {
                                $minimum = (int) $ruleValue;
                            }
                            break;
                        case 'max':
                            if ($type === 'string') {
                                $maxLength = (int) $ruleValue;
                            } else {
                                $maximum = (int) $ruleValue;
                            }
                            break;
                        case 'in':
                            $enumValues = ValidationRules::extractSafeInRuleValues($ruleValue);
                            if ($enumValues === null) {
                                $hasInvalidInlineEnum = true;
                            } else {
                                $inlineEnum = $enumValues;
                            }
                            break;
                        case 'size':
                            if ($type === 'string') {
                                $minLength = (int) $ruleValue;
                                $maxLength = (int) $ruleValue;
                            }
                            break;
                    }
                }
            }

            if ($hasInvalidInlineEnum) {
                $inlineEnum = null;
            }

            $parameters[] = new InlineParameterInfo(
                name: $field,
                type: $type,
                required: in_array('required', $rulesList) || $this->hasRequiredIf($rulesList),
                rules: $rules,
                description: $this->generateDescription($field, $rulesList, $validation['attributes'] ?? [], $namespace, $useStatements),
                format: $format,
                minLength: $minLength,
                maxLength: $maxLength,
                minimum: $minimum,
                maximum: $maximum,
                inlineEnum: $enumInfo ? null : $inlineEnum,
                enumInfo: $enumInfo,
            );
        }

        return $parameters;
    }

    /**
     * Infer format from validation rules.
     *
     * @param  array<string>  $rulesList
     */
    protected function inferFormat(array $rulesList): ?string
    {
        if (in_array('email', $rulesList)) {
            return 'email';
        }
        if (in_array('url', $rulesList)) {
            return 'uri';
        }
        if (in_array('uuid', $rulesList)) {
            return 'uuid';
        }
        if (in_array('date', $rulesList)) {
            return 'date';
        }
        if (in_array('datetime', $rulesList)) {
            return 'date-time';
        }

        return null;
    }

    /**
     * required_if等の条件付き必須ルールをチェック
     *
     * @param  array<int, mixed>  $rules
     */
    protected function hasRequiredIf(array $rules): bool
    {
        foreach ($rules as $rule) {
            if (is_string($rule) && strpos($rule, 'required_') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * フィールドの説明を生成
     *
     * @param  array<int, mixed>  $rules
     * @param  AttributesArray  $attributes
     * @param  UseStatementsArray  $useStatements
     */
    protected function generateDescription(string $field, array $rules, array $attributes, ?string $namespace = null, array $useStatements = []): string
    {
        // カスタム属性名があれば使用
        $fieldName = $attributes[$field] ?? str_replace('_', ' ', ucfirst($field));

        $descriptions = [];

        foreach ($rules as $rule) {
            if (is_string($rule)) {
                if ($rule === 'required') {
                    $descriptions[] = 'Required';
                } elseif (strpos($rule, 'min:') === 0) {
                    $min = explode(':', $rule)[1];
                    $descriptions[] = "Minimum: {$min}";
                } elseif (strpos($rule, 'max:') === 0) {
                    $max = explode(':', $rule)[1];
                    $descriptions[] = "Maximum: {$max}";
                } elseif ($rule === 'email') {
                    $descriptions[] = 'Must be a valid email address';
                } elseif (strpos($rule, 'custom:') === 0) {
                    $descriptions[] = 'Custom validation applied';
                }
            }

            // Check for enum rule and add enum class name to description
            $enumResult = $this->enumAnalyzer->analyzeValidationRule($rule, $namespace, $useStatements);
            if ($enumResult) {
                $enumClassName = class_basename($enumResult->class);
                $descriptions[] = "({$enumClassName})";
            }
        }

        return $fieldName.(! empty($descriptions) ? ' - '.implode(', ', $descriptions) : '');
    }

    /**
     * ファイルフィールドの説明を生成
     *
     * @param  FileInfoArray  $fileInfo
     * @param  AttributesArray  $attributes
     */
    protected function generateFileDescription(string $field, array $fileInfo, array $attributes): string
    {
        // カスタム属性名があれば使用
        $fieldName = $attributes[$field] ?? str_replace('_', ' ', ucfirst($field));

        $parts = [];

        if (! empty($fileInfo['mimes'])) {
            $parts[] = 'Allowed types: '.implode(', ', $fileInfo['mimes']);
        }

        if (isset($fileInfo['max_size'])) {
            $maxSize = FileSizeFormatter::format($fileInfo['max_size']);
            $parts[] = "Max size: {$maxSize}";
        }

        if (isset($fileInfo['min_size'])) {
            $minSize = FileSizeFormatter::format($fileInfo['min_size']);
            $parts[] = "Min size: {$minSize}";
        }

        if (! empty($fileInfo['dimensions'])) {
            if (isset($fileInfo['dimensions']['width']) && isset($fileInfo['dimensions']['height'])) {
                $parts[] = "Dimensions: {$fileInfo['dimensions']['width']}x{$fileInfo['dimensions']['height']}";
            }
            if (isset($fileInfo['dimensions']['min_width']) && isset($fileInfo['dimensions']['min_height'])) {
                $parts[] = "Min dimensions: {$fileInfo['dimensions']['min_width']}x{$fileInfo['dimensions']['min_height']}";
            }
            if (isset($fileInfo['dimensions']['max_width']) && isset($fileInfo['dimensions']['max_height'])) {
                $parts[] = "Max dimensions: {$fileInfo['dimensions']['max_width']}x{$fileInfo['dimensions']['max_height']}";
            }
            if (isset($fileInfo['dimensions']['ratio'])) {
                $parts[] = "Aspect ratio: {$fileInfo['dimensions']['ratio']}";
            }
        }

        return $fieldName.(! empty($parts) ? ' - '.implode('. ', $parts) : '');
    }
}
