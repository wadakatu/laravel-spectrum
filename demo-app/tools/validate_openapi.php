<?php

declare(strict_types=1);

use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/OpenApiRequirementValidator.php';

if ($argc < 4) {
    fwrite(
        STDERR,
        "Usage: php demo-app/tools/validate_openapi.php <spec-path> <expected-openapi-version> <label> [--report=/path/to/report.json]\n"
    );
    exit(2);
}

$specPath = $argv[1];
$expectedVersion = $argv[2];
$label = $argv[3];
$reportPath = null;

for ($index = 4; $index < $argc; $index++) {
    if (str_starts_with($argv[$index], '--report=')) {
        $reportPath = substr($argv[$index], strlen('--report='));
    }
}

$schemaErrors = [];

$content = file_get_contents($specPath);
if ($content === false) {
    fwrite(STDERR, "[FAIL] {$label}: unable to read spec file: {$specPath}\n");
    exit(1);
}

try {
    /** @var array<string, mixed> $spec */
    $spec = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    fwrite(STDERR, "[FAIL] {$label}: invalid JSON ({$e->getMessage()})\n");
    exit(1);
}

$actualVersion = $spec['openapi'] ?? null;
if (! is_string($actualVersion)) {
    $schemaErrors[] = 'missing or invalid "openapi" version';
} elseif ($actualVersion !== $expectedVersion) {
    $schemaErrors[] = "version mismatch: expected {$expectedVersion}, got {$actualVersion}";
}

$openApi = Reader::readFromJson($content);
$schemaValid = false;
if (! $openApi instanceof OpenApi) {
    $schemaErrors[] = 'failed to parse spec with cebe/openapi Reader';
} else {
    $schemaValid = $openApi->validate();
    if (! $schemaValid) {
        foreach ($openApi->getErrors() as $readerError) {
            $schemaErrors[] = "schema validation error: {$readerError}";
        }
    }
}

$validator = new OpenApiRequirementValidator;
$report = $validator->validate(
    spec: $spec,
    rawJson: $content,
    expectedVersion: $expectedVersion,
    schemaValid: $schemaValid,
    schemaErrors: $schemaErrors
);

if (is_string($reportPath) && $reportPath !== '') {
    $directory = dirname($reportPath);
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

$pathCount = 0;
if (isset($spec['paths']) && is_array($spec['paths'])) {
    $pathCount = count($spec['paths']);
}

if ($report['failures'] !== []) {
    fwrite(STDERR, "[FAIL] {$label} ({$expectedVersion})\n");
    foreach ($report['failures'] as $error) {
        fwrite(STDERR, "  - {$error}\n");
    }
    exit(1);
}

fwrite(
    STDOUT,
    "[PASS] {$label} ({$expectedVersion}) paths={$pathCount} requirements={$report['summary']['passed']}/{$report['summary']['total']}\n"
);
exit(0);
