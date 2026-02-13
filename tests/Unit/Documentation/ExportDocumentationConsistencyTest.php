<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Documentation;

use LaravelSpectrum\Console\Commands\ExportInsomniaCommand;
use LaravelSpectrum\Console\Commands\ExportPostmanCommand;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

class ExportDocumentationConsistencyTest extends TestCase
{
    #[Test]
    public function readme_uses_existing_export_commands(): void
    {
        $readme = $this->readFile('README.md');

        $this->assertStringContainsString('php artisan spectrum:export:postman', $readme);
        $this->assertStringContainsString('php artisan spectrum:export:insomnia', $readme);
        $this->assertDoesNotMatchRegularExpression('/php artisan spectrum:export\s+postman/', $readme);
        $this->assertDoesNotMatchRegularExpression('/php artisan spectrum:export\s+insomnia/', $readme);
    }

    #[Test]
    public function export_guide_options_match_command_signatures(): void
    {
        $exportGuide = $this->readFile('docs/docs/export.md');

        $postmanSection = $this->extractSection($exportGuide, '## Postman Export', '## Insomnia Export');
        $insomniaSection = $this->extractSection($exportGuide, '## Insomnia Export', '## Import Procedures');

        $postmanDocumented = $this->extractLongOptions($postmanSection);
        $insomniaDocumented = $this->extractLongOptions($insomniaSection);

        $postmanSignature = $this->extractSignatureOptions(ExportPostmanCommand::class);
        $insomniaSignature = $this->extractSignatureOptions(ExportInsomniaCommand::class);

        sort($postmanDocumented);
        sort($insomniaDocumented);
        sort($postmanSignature);
        sort($insomniaSignature);

        $this->assertSame([], array_values(array_diff($postmanDocumented, $postmanSignature)), 'Postman docs contain unsupported options.');
        $this->assertSame([], array_values(array_diff($postmanSignature, $postmanDocumented)), 'Postman docs are missing supported options.');

        $this->assertSame([], array_values(array_diff($insomniaDocumented, $insomniaSignature)), 'Insomnia docs contain unsupported options.');
        $this->assertSame([], array_values(array_diff($insomniaSignature, $insomniaDocumented)), 'Insomnia docs are missing supported options.');
    }

    #[Test]
    public function export_guide_defaults_match_implementation(): void
    {
        $exportGuide = $this->readFile('docs/docs/export.md');

        $postmanSource = $this->readFile('src/Console/Commands/ExportPostmanCommand.php');
        preg_match("/storage_path\\('([^']+)'\\)/", $postmanSource, $postmanDirMatch);
        preg_match('/\$collectionPath\s*=\s*\$outputDir\.\'\/([^\']+)\'/', $postmanSource, $postmanFileMatch);

        $this->assertNotEmpty($postmanDirMatch, 'Could not detect Postman default output directory from command source.');
        $this->assertNotEmpty($postmanFileMatch, 'Could not detect Postman collection file name from command source.');

        $postmanDefaultPath = $postmanDirMatch[1].'/'.$postmanFileMatch[1];
        $this->assertStringContainsString($postmanDefaultPath, $exportGuide);

        $insomniaSource = $this->readFile('src/Console/Commands/ExportInsomniaCommand.php');
        preg_match('/\$outputPath\s*=\s*storage_path\(\'([^\']+)\'\);/', $insomniaSource, $insomniaPathMatch);

        $this->assertNotEmpty($insomniaPathMatch, 'Could not detect Insomnia default output path from command source.');
        $this->assertStringContainsString($insomniaPathMatch[1], $exportGuide);
    }

    #[Test]
    public function readme_runtime_requirements_match_composer_constraints(): void
    {
        $readme = $this->readFile('README.md');
        $composer = json_decode($this->readFile('composer.json'), true, 512, JSON_THROW_ON_ERROR);

        $phpConstraint = (string) ($composer['require']['php'] ?? '');
        preg_match('/\^(\d+\.\d+)/', $phpConstraint, $phpMatch);

        $this->assertNotEmpty($phpMatch, 'Could not detect PHP minimum version from composer.json.');
        $this->assertStringContainsString('PHP '.$phpMatch[1].'+', $readme);

        $laravelMajors = [];
        foreach (['illuminate/console', 'illuminate/routing', 'illuminate/support'] as $package) {
            $constraint = (string) ($composer['require'][$package] ?? '');
            preg_match_all('/\^(\d+)\./', $constraint, $matches);

            foreach ($matches[1] as $major) {
                $laravelMajors[] = (int) $major;
            }
        }

        $laravelMajors = array_values(array_unique($laravelMajors));
        sort($laravelMajors);

        $this->assertNotEmpty($laravelMajors, 'Could not detect supported Laravel major versions from composer.json.');

        $expectedLine = $this->buildLaravelRequirementLine($laravelMajors);
        $this->assertStringContainsString($expectedLine, $readme);
    }

    private function readFile(string $path): string
    {
        $contents = file_get_contents($this->projectRoot().'/'.ltrim($path, '/'));

        $this->assertNotFalse($contents, "Unable to read file: {$path}");

        return $contents;
    }

    private function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @return array<int, string>
     */
    private function extractLongOptions(string $content): array
    {
        preg_match_all('/--[a-z0-9-]+/i', $content, $matches);

        $options = array_values(array_unique($matches[0] ?? []));
        sort($options);

        return $options;
    }

    /**
     * @return array<int, string>
     */
    private function extractSignatureOptions(string $commandClass): array
    {
        $reflection = new ReflectionClass($commandClass);
        $defaults = $reflection->getDefaultProperties();
        $signature = (string) ($defaults['signature'] ?? '');

        preg_match_all('/\{--([a-z0-9-]+)(?:=)?[^}]*\}/i', $signature, $matches);

        $options = array_map(static fn (string $option): string => '--'.$option, $matches[1] ?? []);
        $options = array_values(array_unique($options));
        sort($options);

        return $options;
    }

    private function extractSection(string $markdown, string $startHeading, string $endHeading): string
    {
        $startPosition = strpos($markdown, $startHeading);
        $endPosition = strpos($markdown, $endHeading);

        $this->assertNotFalse($startPosition, "Missing heading: {$startHeading}");
        $this->assertNotFalse($endPosition, "Missing heading: {$endHeading}");
        $this->assertTrue($endPosition > $startPosition, "Invalid heading order: {$startHeading} before {$endHeading}");

        return substr($markdown, (int) $startPosition, (int) $endPosition - (int) $startPosition);
    }

    /**
     * @param  array<int, int>  $majors
     */
    private function buildLaravelRequirementLine(array $majors): string
    {
        $parts = array_map(static fn (int $major): string => $major.'.x', $majors);

        if (count($parts) === 1) {
            return 'Laravel '.$parts[0];
        }

        if (count($parts) === 2) {
            return 'Laravel '.$parts[0].' or '.$parts[1];
        }

        $last = array_pop($parts);

        return 'Laravel '.implode(', ', $parts).', or '.$last;
    }
}
