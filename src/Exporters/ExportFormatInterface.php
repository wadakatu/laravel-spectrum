<?php

namespace LaravelSpectrum\Exporters;

interface ExportFormatInterface
{
    /**
     * Export OpenAPI to specific format
     */
    public function export(array $openapi, array $options = []): array;

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
