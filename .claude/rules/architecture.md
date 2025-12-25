# Architecture Rules

## Core Architecture Pattern

Laravel Spectrum follows a pipeline architecture:

```
Route Analysis → Controller Analysis → Resource Analysis → Schema Generation → Document Assembly
```

## Component Categories

### Analyzers (`src/Analyzers/`)
Extract information from code without modifying it.

**Pattern:**
```php
public function analyze($input): array
{
    // 1. Validate input
    // 2. Extract relevant data using AST or Reflection
    // 3. Convert to standard format
    // 4. Return structured array
}
```

**Key Analyzers:**
- `RouteAnalyzer` - Route definitions
- `FormRequestAnalyzer` - Validation rules
- `ControllerAnalyzer` - Controller methods
- `ResourceAnalyzer` - API Resources

### Generators (`src/Generators/`)
Convert analyzed data to OpenAPI format.

**Key Generators:**
- `OpenApiGenerator` - Main orchestrator
- `SchemaGenerator` - Validation to OpenAPI schemas
- `ExampleGenerator` - Realistic examples via Faker

### Services (`src/Services/`)
Supporting functionality like caching and file watching.

## AST Visitor Pattern

For static code analysis, use PHP-Parser visitors:

```php
class MyVisitor extends NodeVisitorAbstract
{
    public function enterNode(Node $node)
    {
        // Extract information from AST nodes
    }
}
```

## Error Collection Pattern

**DO NOT throw exceptions from analyzers.** Instead:

1. Use `ErrorCollector` to collect errors
2. Categorize with `AnalyzerErrorType` enum
3. Continue analysis even when errors occur
4. Report all errors at the end

## Adding New Components

### New Analyzer
1. Create test in `tests/Unit/Analyzers/`
2. Implement in `src/Analyzers/`
3. Register in `OpenApiGenerator` if needed
4. Add integration tests

### New Generator
1. Follow existing generator patterns
2. Accept analyzed data as input
3. Return OpenAPI-compliant structure

## Performance Considerations

- Use parallel processing for large codebases
- Cache expensive computations
- Chunk large datasets
- Avoid reading entire files when AST is sufficient
