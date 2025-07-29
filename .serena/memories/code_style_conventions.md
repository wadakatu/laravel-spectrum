# Code Style and Conventions

## PHP Code Style
- **PSR Compliance**: Laravel preset with Pint
- **Namespace**: `LaravelSpectrum\`
- **PHP Version**: Minimum PHP 8.1, with support up to 8.4

## Key Conventions

### Class Structure
- Properties are private with descriptive names
- Constructor dependency injection preferred
- Methods follow clear naming: `analyze()`, `generate()`, `extract()`

### Type Declarations
- Use property type declarations consistently
- Return type hints on all methods
- Use union types where appropriate

### Documentation
- No PHPDoc comments in source (zero-annotation philosophy)
- Let code be self-documenting with clear names
- Comments only for complex logic explanations

### Analyzer Pattern
```php
public function analyze($input): array
{
    // 1. Validate input
    // 2. Extract relevant data  
    // 3. Convert to standard format
    // 4. Return structured array
}
```

### AST Visitor Pattern
- Extend `PhpParser\NodeVisitorAbstract`
- Clear visitor names like `RulesExtractorVisitor`
- Focused single responsibility per visitor

### Error Handling
- Use `ErrorCollector` service for non-fatal errors
- Continue processing on errors when possible
- Report all errors at the end

### Testing
- Test-first development mandatory
- Unit tests for individual components
- Feature tests for integration
- Use fixtures for test data

## Quality Standards
- PHPStan Level 5 (no baseline additions)
- 100% Pint compliance
- Comprehensive test coverage expected