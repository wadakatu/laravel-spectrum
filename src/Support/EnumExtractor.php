<?php

namespace LaravelSpectrum\Support;

use BackedEnum;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionEnum;

class EnumExtractor
{
    /**
     * Extract values from an enum class
     *
     * @throws InvalidArgumentException
     */
    public function extractValues(string $enumClass): array
    {
        if (! enum_exists($enumClass)) {
            throw new InvalidArgumentException("Class {$enumClass} is not a valid enum");
        }

        $values = [];

        foreach ($enumClass::cases() as $case) {
            if ($case instanceof BackedEnum) {
                $values[] = $case->value;
            } else {
                // For unit enums, use the case name
                $values[] = $case->name;
            }
        }

        return $values;
    }

    /**
     * Get the type of enum values (string or integer)
     *
     * @throws InvalidArgumentException
     */
    public function getEnumType(string $enumClass): string
    {
        if (! enum_exists($enumClass)) {
            throw new InvalidArgumentException("Class {$enumClass} is not a valid enum");
        }

        // Check if it's a backed enum
        if ($this->isBackedEnum($enumClass)) {
            $reflection = new ReflectionEnum($enumClass);
            $backingType = $reflection->getBackingType();

            if ($backingType !== null) {
                $typeName = $backingType->getName();

                return $typeName === 'int' ? 'integer' : 'string';
            }
        }

        // Default to string for unit enums
        return 'string';
    }

    /**
     * Check if the enum is a backed enum
     *
     * @throws InvalidArgumentException
     */
    public function isBackedEnum(string $enumClass): bool
    {
        if (! enum_exists($enumClass)) {
            throw new InvalidArgumentException("Class {$enumClass} is not a valid enum");
        }

        $reflection = new ReflectionClass($enumClass);

        return $reflection->implementsInterface(BackedEnum::class);
    }
}
