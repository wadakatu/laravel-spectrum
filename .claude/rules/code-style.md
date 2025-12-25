# Code Style Rules

## PHP Code Standards

### Static Analysis
- PHPStan Level 6 is required
- No new baseline additions allowed without explicit approval
- All errors must be fixed, not suppressed

### Code Formatting
- Use Laravel Pint with Laravel preset
- Run `composer format:fix` before committing
- Follow PSR-12 coding standards

### Type Declarations
- Always use strict types: `declare(strict_types=1);`
- Provide complete PHPDoc for generic types (e.g., `@param \ReflectionClass<object>`)
- Use union types sparingly; prefer interfaces when possible

### Naming Conventions
- Analyzers: `{Name}Analyzer` (e.g., `FormRequestAnalyzer`)
- Generators: `{Name}Generator` (e.g., `SchemaGenerator`)
- Visitors: `{Name}Visitor` (e.g., `ValidationRuleVisitor`)
- Traits: `Has{Feature}` (e.g., `HasErrorCollection`)

### Error Handling
- Use `AnalyzerErrorType` enum for categorizing errors
- Collect errors via `ErrorCollector` instead of throwing exceptions
- Log warnings for unsupported features, not errors

### Comments and Documentation
- PHPDoc blocks for all public methods
- Explain "why", not "what" in inline comments
- Use Japanese comments only when explaining domain-specific logic
