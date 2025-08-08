<?php

namespace LaravelSpectrum\Analyzers;

use LaravelSpectrum\Support\ErrorCollector;
use LaravelSpectrum\Support\TypeInference;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class InlineValidationAnalyzer
{
    protected TypeInference $typeInference;

    protected EnumAnalyzer $enumAnalyzer;

    protected FileUploadAnalyzer $fileUploadAnalyzer;

    protected ?ErrorCollector $errorCollector = null;

    public function __construct(TypeInference $typeInference, ?EnumAnalyzer $enumAnalyzer = null, ?FileUploadAnalyzer $fileUploadAnalyzer = null, ?ErrorCollector $errorCollector = null)
    {
        $this->typeInference = $typeInference;
        $this->enumAnalyzer = $enumAnalyzer ?? new EnumAnalyzer;
        $this->fileUploadAnalyzer = $fileUploadAnalyzer ?? new FileUploadAnalyzer;
        $this->errorCollector = $errorCollector;
    }

    /**
     * メソッド内のインラインバリデーションを解析
     */
    public function analyze(Node\Stmt\ClassMethod $method): array
    {
        try {
            $validations = [];

            $visitor = new class extends NodeVisitorAbstract
            {
                public $validations = [];

                public function enterNode(Node $node)
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

                protected function extractValidation(Node\Expr\MethodCall $node)
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

                protected function extractRequestValidation(Node\Expr\MethodCall $node)
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

                protected function extractRequestVariableValidation(Node\Expr\MethodCall $node)
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
                protected function extractAnonymousFormRequestValidation(Node\Stmt\Class_ $classNode)
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
                 */
                protected function extractReturnedArray(Node\Stmt\ClassMethod $method): array
                {
                    $returnedArray = [];

                    $visitor = new class extends NodeVisitorAbstract
                    {
                        public $returnedArray = [];

                        public function enterNode(Node $node): ?int
                        {
                            if ($node instanceof Node\Stmt\Return_ &&
                                $node->expr instanceof Node\Expr\Array_) {
                                $this->returnedArray = $this->extractArray($node->expr);

                                return NodeTraverser::STOP_TRAVERSAL;
                            }

                            return null;
                        }

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

                        protected function extractValue(Node $node)
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

                        protected function getNodeValue(Node $node)
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

                protected function extractValidatorMake(Node\Expr\StaticCall $node)
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

                protected function getNodeValue(Node $node)
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

            // 複数のバリデーションがある場合はマージ
            return $this->mergeValidations($visitor->validations);
        } catch (\Exception $e) {
            $this->errorCollector?->addError(
                'InlineValidationAnalyzer',
                "Failed to analyze inline validation in method: {$e->getMessage()}",
                [
                    'method' => $method->name->toString(),
                    'error_type' => 'inline_validation_error',
                ]
            );

            // エラーが発生しても空の配列を返して処理を継続
            return [];
        }
    }

    /**
     * 複数のバリデーションをマージ
     */
    protected function mergeValidations(array $validations): array
    {
        if (empty($validations)) {
            return [];
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

        return $merged;
    }

    /**
     * バリデーションルールからパラメータ情報を生成
     */
    public function generateParameters(array $validation, ?string $namespace = null, array $useStatements = []): array
    {
        $parameters = [];

        // Analyze file upload fields
        $fileFields = $this->fileUploadAnalyzer->analyzeRules($validation['rules']);

        foreach ($validation['rules'] as $field => $rules) {
            // Check if this is a file upload field
            if (isset($fileFields[$field])) {
                $fileInfo = $fileFields[$field];
                $rulesList = is_array($rules) ? $rules : explode('|', $rules);

                $parameter = [
                    'name' => $field,
                    'type' => 'file',
                    'format' => 'binary',
                    'file_info' => $fileInfo,
                    'required' => in_array('required', $rulesList) || $this->hasRequiredIf($rulesList),
                    'rules' => $rules,
                    'description' => $this->generateFileDescription($field, $fileInfo, $validation['attributes'] ?? []),
                ];

                $parameters[] = $parameter;

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

            $parameter = [
                'name' => $field,
                'type' => $this->typeInference->inferFromRules($rulesList),
                'required' => in_array('required', $rulesList) || $this->hasRequiredIf($rulesList),
                'rules' => $rules,
                'description' => $this->generateDescription($field, $rulesList, $validation['attributes'] ?? [], $namespace, $useStatements),
            ];

            // Add format for special validation rules
            if (in_array('email', $rulesList)) {
                $parameter['format'] = 'email';
            } elseif (in_array('url', $rulesList)) {
                $parameter['format'] = 'uri';
            } elseif (in_array('uuid', $rulesList)) {
                $parameter['format'] = 'uuid';
            } elseif (in_array('date', $rulesList)) {
                $parameter['format'] = 'date';
            } elseif (in_array('datetime', $rulesList)) {
                $parameter['format'] = 'date-time';
            }

            // バリデーションルールから追加情報を抽出
            foreach ($rulesList as $rule) {
                if (is_string($rule) && strpos($rule, ':') !== false) {
                    [$ruleName, $ruleValue] = explode(':', $rule, 2);

                    switch ($ruleName) {
                        case 'min':
                            if ($parameter['type'] === 'string') {
                                $parameter['minLength'] = (int) $ruleValue;
                            } else {
                                $parameter['minimum'] = (int) $ruleValue;
                            }
                            break;
                        case 'max':
                            if ($parameter['type'] === 'string') {
                                $parameter['maxLength'] = (int) $ruleValue;
                            } else {
                                $parameter['maximum'] = (int) $ruleValue;
                            }
                            break;
                        case 'in':
                            $parameter['enum'] = explode(',', $ruleValue);
                            break;
                        case 'size':
                            if ($parameter['type'] === 'string') {
                                $parameter['minLength'] = (int) $ruleValue;
                                $parameter['maxLength'] = (int) $ruleValue;
                            }
                            break;
                    }
                }
            }

            // Add enum information if found
            if ($enumInfo) {
                $parameter['enum'] = $enumInfo;
            }

            $parameters[] = $parameter;
        }

        return $parameters;
    }

    /**
     * required_if等の条件付き必須ルールをチェック
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
                $enumClassName = class_basename($enumResult['class']);
                $descriptions[] = "({$enumClassName})";
            }
        }

        return $fieldName.(! empty($descriptions) ? ' - '.implode(', ', $descriptions) : '');
    }

    /**
     * ファイルフィールドの説明を生成
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
            $maxSize = $this->formatFileSize($fileInfo['max_size']);
            $parts[] = "Max size: {$maxSize}";
        }

        if (isset($fileInfo['min_size'])) {
            $minSize = $this->formatFileSize($fileInfo['min_size']);
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

    /**
     * Format file size to human readable format
     */
    protected function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            $size = $bytes / 1073741824;

            return $size == (int) $size ? sprintf('%dGB', (int) $size) : sprintf('%.1fGB', $size);
        }

        if ($bytes >= 1048576) {
            $size = $bytes / 1048576;

            return $size == (int) $size ? sprintf('%dMB', (int) $size) : sprintf('%.1fMB', $size);
        }

        if ($bytes >= 1024) {
            $size = $bytes / 1024;

            return $size == (int) $size ? sprintf('%dKB', (int) $size) : sprintf('%.1fKB', $size);
        }

        return sprintf('%dB', $bytes);
    }
}
