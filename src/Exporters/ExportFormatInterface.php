<?php

namespace LaravelSpectrum\Exporters;

use LaravelSpectrum\DTO\OpenApiSpec;

interface ExportFormatInterface
{
    /**
     * Export OpenAPI to specific format
     *
     * @param  OpenApiSpec|array<string, mixed>  $openapi  OpenAPI specification
     * @param  array<string, mixed>  $options  Export options
     * @return array<string, mixed>
     */
    public function export(OpenApiSpec|array $openapi, array $options = []): array;

    /**
     * Get the file extension for this format
     */
    public function getFileExtension(): string;

    /**
     * Get the format name
     */
    public function getFormatName(): string;

    /**
     * Export environment variables
     */
    public function exportEnvironment(array $servers, array $security, string $environment = 'local'): array;
}
