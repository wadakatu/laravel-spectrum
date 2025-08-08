<?php

require 'vendor/autoload.php';

use LaravelSpectrum\Analyzers\EnumAnalyzer;
use LaravelSpectrum\Analyzers\FileUploadAnalyzer;
use LaravelSpectrum\Analyzers\InlineValidationAnalyzer;
use LaravelSpectrum\Support\TypeInference;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

$analyzer = new InlineValidationAnalyzer(
    new TypeInference(new NodeTraverser, new Standard),
    new EnumAnalyzer,
    new FileUploadAnalyzer
);

// Test the actual controller code
$controllerCode = file_get_contents('app/Http/Controllers/AnonymousFormRequestController.php');
$parser = (new ParserFactory)->createForNewestSupportedVersion();
$ast = $parser->parse($controllerCode);

// Find the class and its methods
$methods = [];
foreach ($ast as $node) {
    if ($node instanceof PhpParser\Node\Stmt\Namespace_) {
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof PhpParser\Node\Stmt\Class_) {
                foreach ($stmt->stmts as $member) {
                    if ($member instanceof PhpParser\Node\Stmt\ClassMethod) {
                        $methods[$member->name->toString()] = $member;
                    }
                }
            }
        }
    }
}

echo 'Methods found: '.implode(', ', array_keys($methods))."\n\n";

if (isset($methods['updateProfile'])) {
    echo "Analyzing updateProfile method...\n";
    $result = $analyzer->analyze($methods['updateProfile']);

    echo "\nValidation rules found:\n";
    print_r($result);

    echo "\nRule types:\n";
    foreach ($result['rules'] as $field => $rule) {
        echo "  $field: ".(is_array($rule) ? 'array['.implode(', ', $rule).']' : "string: $rule")."\n";
    }
} else {
    echo "updateProfile method not found\n";
}

if (isset($methods['store'])) {
    echo "\n\nAnalyzing store method...\n";
    $result = $analyzer->analyze($methods['store']);

    echo "\nValidation rules found:\n";
    print_r($result);

    echo "\nRule types:\n";
    foreach ($result['rules'] as $field => $rule) {
        echo "  $field: ".(is_array($rule) ? 'array['.implode(', ', $rule).']' : "string: $rule")."\n";
    }
}
