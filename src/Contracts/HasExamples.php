<?php

namespace LaravelSpectrum\Contracts;

interface HasExamples
{
    /**
     * Get example data for this resource
     */
    public function getExample(): array;

    /**
     * Get multiple named examples
     *
     * @return array<string, array>
     */
    public function getExamples(): array;
}
