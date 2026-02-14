<?php

declare(strict_types=1);

use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;

require dirname(__DIR__, 2).'/vendor/autoload.php';

if ($argc < 4) {
    fwrite(STDERR, "Usage: php demo-app/tools/validate_openapi.php <spec-path> <expected-openapi-version> <label>\n");
    exit(2);
}

$specPath = $argv[1];
$expectedVersion = $argv[2];
$label = $argv[3];

$errors = [];

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
    $errors[] = 'missing or invalid "openapi" version';
} elseif ($actualVersion !== $expectedVersion) {
    $errors[] = "version mismatch: expected {$expectedVersion}, got {$actualVersion}";
}

$openApi = Reader::readFromJson($content);
if (! $openApi instanceof OpenApi) {
    $errors[] = 'failed to parse spec with cebe/openapi Reader';
} else {
    $valid = $openApi->validate();
    if (! $valid) {
        foreach ($openApi->getErrors() as $readerError) {
            $errors[] = "schema validation error: {$readerError}";
        }
    }
}

if (str_starts_with($expectedVersion, '3.0.')) {
    if (array_key_exists('jsonSchemaDialect', $spec)) {
        $errors[] = 'OpenAPI 3.0.x must not include jsonSchemaDialect';
    }

    if (array_key_exists('webhooks', $spec)) {
        $errors[] = 'OpenAPI 3.0.x must not include webhooks';
    }

    if (preg_match('/"type"\s*:\s*\[/', $content) === 1) {
        $errors[] = 'OpenAPI 3.0.x must not use array form of "type"';
    }
}

if (str_starts_with($expectedVersion, '3.1.')) {
    $dialect = $spec['jsonSchemaDialect'] ?? null;
    if (! is_string($dialect) || $dialect === '') {
        $errors[] = 'OpenAPI 3.1.x must include jsonSchemaDialect';
    }

    if (! array_key_exists('webhooks', $spec)) {
        $errors[] = 'OpenAPI 3.1.x output is expected to include a webhooks section';
    }

    if (preg_match('/"nullable"\s*:/', $content) === 1) {
        $errors[] = 'OpenAPI 3.1.x must not include nullable keyword';
    }
}

$pathCount = 0;
if (isset($spec['paths']) && is_array($spec['paths'])) {
    $pathCount = count($spec['paths']);
}

if ($errors !== []) {
    fwrite(STDERR, "[FAIL] {$label} ({$expectedVersion})\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "  - {$error}\n");
    }
    exit(1);
}

fwrite(STDOUT, "[PASS] {$label} ({$expectedVersion}) paths={$pathCount}\n");
exit(0);
