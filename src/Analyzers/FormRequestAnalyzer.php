<?php

namespace LaravelPrism\Analyzers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LaravelPrism\Support\TypeInference;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;

class FormRequestAnalyzer
{
    protected TypeInference $typeInference;

    protected Parser $parser;

    protected NodeTraverser $traverser;

    protected PrettyPrinter\Standard $printer;

    public function __construct(TypeInference $typeInference)
    {
        $this->typeInference = $typeInference;
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
        $this->traverser = new NodeTraverser;
        $this->printer = new PrettyPrinter\Standard;
    }

    /**
     * FormRequestクラスを解析してパラメータ情報を抽出
     */
    public function analyze(string $requestClass): array
    {
        if (! class_exists($requestClass)) {
            return [];
        }

        try {
            $reflection = new \ReflectionClass($requestClass);

            // FormRequestを継承していない場合はスキップ
            if (! $reflection->isSubclassOf(\Illuminate\Foundation\Http\FormRequest::class)) {
                return [];
            }

            $filePath = $reflection->getFileName();
            if (! $filePath || ! file_exists($filePath) || $reflection->isAnonymous()) {
                // For anonymous classes or when file path is not available, use reflection-based fallback
                return $this->analyzeWithReflection($reflection);
            }

            // ファイルをパース
            $code = file_get_contents($filePath);
            $ast = $this->parser->parse($code);

            if (! $ast) {
                return [];
            }

            // クラスノードを探す
            $classNode = $this->findClassNode($ast, $reflection->getShortName());
            if (! $classNode) {
                return [];
            }

            // rules()メソッドを解析
            $rules = $this->extractRules($classNode);

            // attributes()メソッドを解析
            $attributes = $this->extractAttributes($classNode);

            // パラメータ情報を生成
            return $this->generateParameters($rules, $attributes);

        } catch (Error $parseError) {
            Log::warning("Failed to parse FormRequest: {$requestClass}", [
                'error' => $parseError->getMessage(),
            ]);

            return [];
        } catch (\Exception $e) {
            Log::warning("Failed to analyze FormRequest: {$requestClass}", [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * FormRequestクラスを解析して詳細情報を取得
     */
    public function analyzeWithDetails(string $requestClass): array
    {
        if (!class_exists($requestClass)) {
            return [];
        }
        
        try {
            $reflection = new \ReflectionClass($requestClass);
            
            // FormRequestを継承していない場合はスキップ
            if (!$reflection->isSubclassOf(\Illuminate\Foundation\Http\FormRequest::class)) {
                return [];
            }
            
            $filePath = $reflection->getFileName();
            if (!$filePath || !file_exists($filePath) || $reflection->isAnonymous()) {
                // For anonymous classes or when file path is not available, use reflection-based fallback
                return $this->analyzeWithDetailsUsingReflection($reflection);
            }
            
            // ファイルをパース
            $code = file_get_contents($filePath);
            $ast = $this->parser->parse($code);
            
            if (!$ast) {
                return [];
            }
            
            // クラスノードを探す
            $classNode = $this->findClassNode($ast, $reflection->getShortName());
            if (!$classNode) {
                return [];
            }
            
            // rules()メソッドを解析
            $rules = $this->extractRules($classNode);
            
            // attributes()メソッドを解析
            $attributes = $this->extractAttributes($classNode);
            
            // messages()メソッドを解析
            $messages = $this->extractMessages($classNode);
            
            return [
                'rules' => $rules,
                'attributes' => $attributes,
                'messages' => $messages,
            ];
            
        } catch (\Exception $e) {
            Log::warning("Failed to analyze FormRequest with details: {$requestClass}", [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * messages()メソッドから検証メッセージを抽出
     */
    protected function extractMessages(Node\Stmt\Class_ $class): array
    {
        $messagesMethod = $this->findMethodNode($class, 'messages');
        if (!$messagesMethod) {
            return [];
        }
        
        $visitor = new AST\Visitors\ArrayReturnExtractorVisitor($this->printer);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse([$messagesMethod]);
        
        return $visitor->getArray();
    }
    
    /**
     * Reflection-based analysis with details for anonymous classes
     */
    protected function analyzeWithDetailsUsingReflection(\ReflectionClass $reflection): array
    {
        try {
            // Fallback: try to create instance with mock request
            $instance = $reflection->newInstanceWithoutConstructor();
            
            // Initialize request property to avoid errors
            if ($reflection->hasProperty('request')) {
                $requestProp = $reflection->getProperty('request');
                $requestProp->setAccessible(true);
                $mockRequest = new \Illuminate\Http\Request;
                $requestProp->setValue($instance, $mockRequest);
            }
            
            // Extract rules using reflection
            $rules = [];
            if ($reflection->hasMethod('rules')) {
                $method = $reflection->getMethod('rules');
                $method->setAccessible(true);
                try {
                    $rules = $method->invoke($instance) ?: [];
                } catch (\Exception $e) {
                    // If rules() method fails, return empty
                    return [];
                }
            }
            
            // Extract attributes using reflection
            $attributes = [];
            if ($reflection->hasMethod('attributes')) {
                $method = $reflection->getMethod('attributes');
                $method->setAccessible(true);
                $attributes = $method->invoke($instance) ?: [];
            }
            
            // Extract messages using reflection
            $messages = [];
            if ($reflection->hasMethod('messages')) {
                $method = $reflection->getMethod('messages');
                $method->setAccessible(true);
                $messages = $method->invoke($instance) ?: [];
            }
            
            return [
                'rules' => $rules,
                'attributes' => $attributes,
                'messages' => $messages,
            ];
            
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * クラスノードを探す
     */
    protected function findClassNode(array $ast, string $className): ?Node\Stmt\Class_
    {
        $visitor = new AST\Visitors\ClassFindingVisitor($className);
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->getClassNode();
    }

    /**
     * rules()メソッドから検証ルールを抽出
     */
    protected function extractRules(Node\Stmt\Class_ $class): array
    {
        $rulesMethod = $this->findMethodNode($class, 'rules');
        if (! $rulesMethod) {
            return [];
        }

        $visitor = new AST\Visitors\RulesExtractorVisitor($this->printer);
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse([$rulesMethod]);

        return $visitor->getRules();
    }

    /**
     * attributes()メソッドから属性を抽出
     */
    protected function extractAttributes(Node\Stmt\Class_ $class): array
    {
        $attributesMethod = $this->findMethodNode($class, 'attributes');
        if (! $attributesMethod) {
            return [];
        }

        $visitor = new AST\Visitors\ArrayReturnExtractorVisitor($this->printer);
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse([$attributesMethod]);

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
     * パラメータ情報を生成
     */
    protected function generateParameters(array $rules, array $attributes = []): array
    {
        $parameters = [];

        foreach ($rules as $field => $rule) {
            // 特殊なフィールド（_noticeなど）はスキップ
            if (str_starts_with($field, '_')) {
                continue;
            }

            $ruleArray = is_array($rule) ? $rule : explode('|', $rule);

            $parameters[] = [
                'name' => $field,
                'in' => 'body',
                'required' => $this->isRequired($ruleArray),
                'type' => $this->typeInference->inferFromRules($ruleArray),
                'description' => $attributes[$field] ?? $this->generateDescription($field, $ruleArray),
                'example' => $this->typeInference->generateExample($field, $ruleArray),
                'validation' => $ruleArray,
            ];
        }

        return $parameters;
    }

    /**
     * 必須フィールドかどうかを判定
     */
    protected function isRequired($rules): bool
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        foreach ($rules as $rule) {
            $ruleName = is_string($rule) ? explode(':', $rule)[0] : '';
            if (in_array($ruleName, ['required', 'required_if', 'required_unless', 'required_with', 'required_without'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * フィールドの説明を生成
     */
    protected function generateDescription(string $field, array $rules): string
    {
        $description = Str::title(str_replace(['_', '-'], ' ', $field));

        // 特定のルールから追加情報を生成
        foreach ($rules as $rule) {
            if (is_string($rule)) {
                if (str_starts_with($rule, 'max:')) {
                    $max = Str::after($rule, 'max:');
                    $description .= " (最大{$max}文字)";
                } elseif (str_starts_with($rule, 'min:')) {
                    $min = Str::after($rule, 'min:');
                    $description .= " (最小{$min}文字)";
                }
            }
        }

        return $description;
    }

    /**
     * Reflection-based analysis for anonymous classes
     */
    protected function analyzeWithReflection(\ReflectionClass $reflection): array
    {
        try {
            // For AST parsing of anonymous classes, we need to get the source code
            $filePath = $reflection->getFileName();
            if ($filePath && file_exists($filePath)) {
                // Read the file and find the anonymous class definition
                $code = file_get_contents($filePath);
                $startLine = $reflection->getStartLine() - 1;
                $endLine = $reflection->getEndLine();

                // Extract just the class definition
                $lines = explode("\n", $code);
                $classCode = implode("\n", array_slice($lines, $startLine, $endLine - $startLine));

                // Wrap in <?php tag for parsing
                if (! str_contains($classCode, '<?php')) {
                    $classCode = "<?php\n".$classCode;
                }

                try {
                    $ast = $this->parser->parse($classCode);
                    if ($ast) {
                        // Find the class node (anonymous class)
                        $classNode = $this->findAnonymousClassNode($ast);
                        if ($classNode) {
                            $rules = $this->extractRules($classNode);
                            $attributes = $this->extractAttributes($classNode);

                            return $this->generateParameters($rules, $attributes);
                        }
                    }
                } catch (Error $e) {
                    // Fall back to instance-based extraction
                }
            }

            // Fallback: try to create instance with mock request
            $instance = $reflection->newInstanceWithoutConstructor();

            // Initialize request property to avoid errors
            if ($reflection->hasProperty('request')) {
                $requestProp = $reflection->getProperty('request');
                $requestProp->setAccessible(true);
                $mockRequest = new \Illuminate\Http\Request;
                $requestProp->setValue($instance, $mockRequest);
            }

            // Extract rules using reflection
            $rules = [];
            if ($reflection->hasMethod('rules')) {
                $method = $reflection->getMethod('rules');
                $method->setAccessible(true);
                try {
                    $rules = $method->invoke($instance) ?: [];
                } catch (\Exception $e) {
                    // If rules() method fails, return empty
                    return [];
                }
            }

            // Extract attributes using reflection
            $attributes = [];
            if ($reflection->hasMethod('attributes')) {
                $method = $reflection->getMethod('attributes');
                $method->setAccessible(true);
                $attributes = $method->invoke($instance) ?: [];
            }

            return $this->generateParameters($rules, $attributes);

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Find anonymous class node in AST
     */
    protected function findAnonymousClassNode(array $ast): ?Node\Stmt\Class_
    {
        foreach ($ast as $node) {
            if ($node instanceof Node\Expr\New_ &&
                $node->class instanceof Node\Stmt\Class_) {
                return $node->class;
            }

            // Traverse child nodes
            if ($node instanceof Node\Stmt\Expression &&
                $node->expr instanceof Node\Expr\Assign &&
                $node->expr->expr instanceof Node\Expr\New_ &&
                $node->expr->expr->class instanceof Node\Stmt\Class_) {
                return $node->expr->expr->class;
            }
        }

        return null;
    }
}
