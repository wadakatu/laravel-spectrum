# Contributing Guide

We welcome contributions to Laravel Spectrum! This guide explains how to contribute to the project.

## 🤝 Ways to Contribute

### Areas You Can Contribute To

- 🐛 **Bug Fixes** - Fix known issues
- ✨ **New Features** - Propose and implement new features
- 📚 **Documentation** - Improve documentation or translations
- 🧪 **Testing** - Improve test coverage
- 🎨 **Refactoring** - Improve code quality
- 🌐 **Translation** - Multilingual support

## 🚀 Setting Up Development Environment

### 1. Fork the Repository

```bash
# After forking on GitHub
git clone https://github.com/YOUR_USERNAME/laravel-spectrum.git
cd laravel-spectrum
```

### 2. Install Dependencies

```bash
composer install
npm install
```

### 3. Configure Test Environment

```bash
# Create .env file for testing
cp .env.testing.example .env.testing

# Set up test DB (using SQLite)
touch database/testing.sqlite
```

### 4. Set Up pre-commit Hooks

```bash
# Install Husky
npm run prepare

# Or manually set up Git hooks
cp .github/hooks/pre-commit .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit
```

## 📝 Coding Standards

### PHP Coding Standards

Laravel Spectrum follows [PSR-12](https://www.php-fig.org/psr/psr-12/).

```php
<?php

namespace LaravelSpectrum\Analyzers;

use Illuminate\Support\Collection;
use LaravelSpectrum\Contracts\Analyzer;

class ExampleAnalyzer implements Analyzer
{
    /**
     * Constructor dependency injection
     */
    public function __construct(
        private readonly Collection $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Execute analysis
     *
     * @param mixed $target Analysis target
     * @return array Analysis results
     * @throws AnalysisException
     */
    public function analyze($target): array
    {
        try {
            // Implementation
            return $this->performAnalysis($target);
        } catch (\Exception $e) {
            $this->logger->error('Analysis failed', [
                'target' => $target,
                'error' => $e->getMessage(),
            ]);
            
            throw new AnalysisException(
                'Failed to analyze target',
                previous: $e
            );
        }
    }
}
```

### Automatic Code Style Fixes

```bash
# Using Laravel Pint
composer format

# Or specific files only
vendor/bin/pint path/to/file.php

# Dry run (check changes)
vendor/bin/pint --test
```

### Static Analysis

```bash
# Run PHPStan
composer analyze

# Specify level
vendor/bin/phpstan analyze --level=8
```

## 🧪 Writing Tests

### Unit Tests

```php
namespace LaravelSpectrum\Tests\Unit\Analyzers;

use LaravelSpectrum\Analyzers\ExampleAnalyzer;
use LaravelSpectrum\Tests\TestCase;

class ExampleAnalyzerTest extends TestCase
{
    private ExampleAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new ExampleAnalyzer();
    }

    /** @test */
    public function it_analyzes_simple_case(): void
    {
        // Arrange
        $input = ['key' => 'value'];
        
        // Act
        $result = $this->analyzer->analyze($input);
        
        // Assert
        $this->assertArrayHasKey('processed', $result);
        $this->assertEquals('value', $result['processed']['key']);
    }

    /** @test */
    public function it_throws_exception_for_invalid_input(): void
    {
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('Invalid input');
        
        $this->analyzer->analyze(null);
    }
}
```

### Feature Tests

```php
namespace LaravelSpectrum\Tests\Feature;

use LaravelSpectrum\Tests\TestCase;

class DocumentGenerationTest extends TestCase
{
    /** @test */
    public function it_generates_documentation_for_simple_api(): void
    {
        // Define route
        Route::get('/api/test', fn() => ['status' => 'ok']);
        
        // Execute command
        $this->artisan('spectrum:generate')
            ->assertExitCode(0)
            ->assertSee('Documentation generated successfully');
        
        // Check generated file
        $this->assertFileExists(storage_path('app/spectrum/openapi.json'));
        
        $openapi = json_decode(
            file_get_contents(storage_path('app/spectrum/openapi.json')),
            true
        );
        
        $this->assertArrayHasKey('/api/test', $openapi['paths']);
    }
}
```

### Running Tests

```bash
# Run all tests
composer test

# Run specific test
composer test -- --filter=ExampleAnalyzerTest

# With coverage report
composer test-coverage
```

## 🔄 Pull Request Process

### 1. Create a Branch

```bash
# Feature addition
git checkout -b feature/amazing-feature

# Bug fix
git checkout -b fix/issue-123

# Documentation
git checkout -b docs/improve-readme
```

### 2. Commit Messages

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```bash
# Feature addition
git commit -m "feat: add support for complex validation rule detection"

# Bug fix
git commit -m "fix: correctly detect nested array validation rules"

# Documentation
git commit -m "docs: add Japanese translation"

# Breaking change
git commit -m "feat!: change default output format to YAML

BREAKING CHANGE: The default output format has been changed from JSON to YAML."
```

### 3. Pull Request Template

```markdown
## Summary
Brief description of the problem this PR solves or the feature it adds

## Changes
- [ ] Specific change 1
- [ ] Specific change 2

## Tests
- [ ] Added/updated unit tests
- [ ] Added/updated feature tests
- [ ] Manually tested

## Related Issues
Fixes #123

## Screenshots (if UI changes)
Before and after screenshots

## Checklist
- [ ] Follows code style guidelines
- [ ] Self-review completed
- [ ] Documentation updated
- [ ] No breaking changes (or clearly documented if present)
```

### 4. Review Process

1. **Automated Checks** - CI/CD runs automatically
2. **Code Review** - Maintainers review the code
3. **Feedback** - Make corrections as needed
4. **Merge** - Merged after approval

## 📋 Contributor License Agreement (CLA)

You will need to agree to the CLA on your first contribution. This is handled automatically.

## 🏗️ Project Structure

```
laravel-spectrum/
├── src/
│   ├── Analyzers/          # Code analysis classes
│   ├── Cache/              # Cache related
│   ├── Console/            # Artisan commands
│   ├── Contracts/          # Interfaces
│   ├── Events/             # Event classes
│   ├── Exceptions/         # Exception classes
│   ├── Exporters/          # Export functionality
│   ├── Facades/            # Laravel facades
│   ├── Formatters/         # Formatters
│   ├── Generators/         # Generators
│   ├── MockServer/         # Mock server
│   ├── Services/           # Service classes
│   └── Support/            # Helper classes
├── tests/
│   ├── Unit/               # Unit tests
│   ├── Feature/            # Feature tests
│   └── Fixtures/           # Test fixtures
├── config/                 # Configuration files
└── docs/                   # Documentation
```

## 🎯 Focus Areas

We are currently seeking contributions in the following areas:

### 1. Performance Improvements
- Optimization for large projects
- Memory usage reduction
- Parallel processing improvements

### 2. New Features
- gRPC support
- WebSocket API support
- Plugin system development

### 3. Ecosystem
- IDE extensions
- CI tool integrations
- Porting to other frameworks

## 🌐 Translation

### Adding a New Language

1. Create `resources/lang/{locale}` directory
2. Copy and translate existing language files
3. Translate documentation (`docs/{locale}/`)

### Translation Guidelines

- Don't force translation of technical terms
- Prioritize readability
- Accurately convey the original intent

## 📞 Communication

### GitHub Issues
- Bug reports
- Feature requests
- Questions

### GitHub Discussions
- Idea discussions
- RFCs (Request for Comments)
- Community support

### Other
- Twitter: [@LaravelSpectrum](https://twitter.com/LaravelSpectrum)
- Email: contribute@laravel-spectrum.dev

## 🏆 Contributors

Contributors are listed in [README.md](https://github.com/wadakatu/laravel-spectrum#contributors).

## 📄 License

Contributed code is released under the same MIT license as the project.

---

**Thank you!** Your contributions make Laravel Spectrum better. 🎉