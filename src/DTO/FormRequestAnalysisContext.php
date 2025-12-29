<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use ReflectionClass;

/**
 * Represents the analysis context for a FormRequest class.
 *
 * This DTO encapsulates the state and data needed to analyze a FormRequest class.
 * It uses a discriminated union pattern with three possible states:
 * - Skip: Class cannot be analyzed (missing, not a FormRequest, AST failed)
 * - Anonymous: Class is anonymous, requires reflection-based analysis
 * - Ready: Full context available for AST-based analysis
 */
final readonly class FormRequestAnalysisContext
{
    /**
     * @param  FormRequestAnalysisContextType  $type  The context type
     * @param  \ReflectionClass<object>|null  $reflection  Reflection of the FormRequest class (for anonymous and ready)
     * @param  array<Stmt>|null  $ast  Parsed AST nodes (for ready only)
     * @param  Class_|null  $classNode  The class node from AST (for ready only)
     * @param  array<string, string>  $useStatements  Map of short names to fully qualified names (for ready only)
     * @param  string|null  $namespace  The namespace of the class (for ready only)
     */
    private function __construct(
        public FormRequestAnalysisContextType $type,
        public ?ReflectionClass $reflection = null,
        public ?array $ast = null,
        public ?Class_ $classNode = null,
        public array $useStatements = [],
        public ?string $namespace = null,
    ) {}

    /**
     * Create a Skip context.
     *
     * Used when the class doesn't exist, is not a FormRequest subclass,
     * AST parsing failed, or the class node was not found in AST.
     */
    public static function skip(): self
    {
        return new self(type: FormRequestAnalysisContextType::Skip);
    }

    /**
     * Create an Anonymous context.
     *
     * Used when the class is anonymous or its file path is unavailable.
     * Analysis will use reflection-based methods.
     *
     * @param  \ReflectionClass<object>  $reflection
     */
    public static function anonymous(ReflectionClass $reflection): self
    {
        return new self(
            type: FormRequestAnalysisContextType::Anonymous,
            reflection: $reflection,
        );
    }

    /**
     * Create a Ready context with full analysis data.
     *
     * Used when full AST-based analysis is available.
     *
     * @param  \ReflectionClass<object>  $reflection
     * @param  array<Stmt>  $ast
     * @param  array<string, string>  $useStatements
     */
    public static function ready(
        ReflectionClass $reflection,
        array $ast,
        Class_ $classNode,
        array $useStatements,
        string $namespace,
    ): self {
        return new self(
            type: FormRequestAnalysisContextType::Ready,
            reflection: $reflection,
            ast: $ast,
            classNode: $classNode,
            useStatements: $useStatements,
            namespace: $namespace,
        );
    }

    /**
     * Check if this is a Skip context.
     */
    public function isSkip(): bool
    {
        return $this->type === FormRequestAnalysisContextType::Skip;
    }

    /**
     * Check if this is an Anonymous context.
     */
    public function isAnonymous(): bool
    {
        return $this->type === FormRequestAnalysisContextType::Anonymous;
    }

    /**
     * Check if this is a Ready context.
     */
    public function isReady(): bool
    {
        return $this->type === FormRequestAnalysisContextType::Ready;
    }

    /**
     * Get the type as a string for backward compatibility.
     */
    public function getTypeAsString(): string
    {
        return $this->type->value;
    }

    /**
     * Check if reflection is available.
     */
    public function hasReflection(): bool
    {
        return $this->reflection !== null;
    }

    /**
     * Check if AST data is available.
     */
    public function hasAst(): bool
    {
        return $this->ast !== null && $this->classNode !== null;
    }

    /**
     * Convert to array for backward compatibility.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = ['type' => $this->type->value];

        if ($this->reflection !== null) {
            $result['reflection'] = $this->reflection;
        }

        if ($this->ast !== null) {
            $result['ast'] = $this->ast;
        }

        if ($this->classNode !== null) {
            $result['classNode'] = $this->classNode;
        }

        if (! empty($this->useStatements)) {
            $result['useStatements'] = $this->useStatements;
        }

        if ($this->namespace !== null) {
            $result['namespace'] = $this->namespace;
        }

        return $result;
    }

    /**
     * Create from array for backward compatibility.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $type = FormRequestAnalysisContextType::from($data['type'] ?? 'skip');

        return new self(
            type: $type,
            reflection: $data['reflection'] ?? null,
            ast: $data['ast'] ?? null,
            classNode: $data['classNode'] ?? null,
            useStatements: $data['useStatements'] ?? [],
            namespace: $data['namespace'] ?? null,
        );
    }
}
