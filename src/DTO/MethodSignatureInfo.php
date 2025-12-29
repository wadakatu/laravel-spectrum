<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents method signature analysis result for enum parameters and return types.
 *
 * Used by EnumAnalyzer to provide type-safe analysis results.
 */
final readonly class MethodSignatureInfo
{
    /**
     * @param  array<string, EnumInfo>  $parameters  Map of parameter names to their EnumInfo
     * @param  EnumInfo|null  $return  The return type EnumInfo if it's an enum
     */
    public function __construct(
        public array $parameters,
        public ?EnumInfo $return,
    ) {}

    /**
     * Create an empty instance.
     */
    public static function empty(): self
    {
        return new self(
            parameters: [],
            return: null,
        );
    }

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $parameters = [];

        if (isset($data['parameters']) && is_array($data['parameters'])) {
            foreach ($data['parameters'] as $name => $paramData) {
                if ($paramData instanceof EnumInfo) {
                    $parameters[$name] = $paramData;
                } elseif (is_array($paramData)) {
                    $parameters[$name] = EnumInfo::fromArray($paramData);
                }
            }
        }

        $return = null;
        if (isset($data['return'])) {
            if ($data['return'] instanceof EnumInfo) {
                $return = $data['return'];
            } elseif (is_array($data['return'])) {
                $return = EnumInfo::fromArray($data['return']);
            }
        }

        return new self(
            parameters: $parameters,
            return: $return,
        );
    }

    /**
     * Convert to array.
     *
     * @return array{parameters: array<string, array<string, mixed>>, return: array<string, mixed>|null}
     */
    public function toArray(): array
    {
        $parameters = [];
        foreach ($this->parameters as $name => $enumInfo) {
            $parameters[$name] = $enumInfo->toArray();
        }

        return [
            'parameters' => $parameters,
            'return' => $this->return?->toArray(),
        ];
    }

    /**
     * Check if there are any enum parameters.
     */
    public function hasParameters(): bool
    {
        return count($this->parameters) > 0;
    }

    /**
     * Check if there is an enum return type.
     */
    public function hasReturnType(): bool
    {
        return $this->return !== null;
    }

    /**
     * Check if there are any enums (parameters or return).
     */
    public function hasAnyEnums(): bool
    {
        return $this->hasParameters() || $this->hasReturnType();
    }

    /**
     * Count the number of enum parameters.
     */
    public function parameterCount(): int
    {
        return count($this->parameters);
    }

    /**
     * Get the names of all enum parameters.
     *
     * @return array<int, string>
     */
    public function getParameterNames(): array
    {
        return array_keys($this->parameters);
    }

    /**
     * Get a specific parameter's EnumInfo by name.
     */
    public function getParameter(string $name): ?EnumInfo
    {
        return $this->parameters[$name] ?? null;
    }
}
