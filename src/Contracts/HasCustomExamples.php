<?php

namespace LaravelSpectrum\Contracts;

interface HasCustomExamples
{
    /**
     * Get custom example mappings for fields
     *
     * @return array<string, callable|mixed>
     */
    public static function getExampleMapping(): array;
}
