# Development Workflow

## Mandatory Development Process

### 1. Use Serena MCP Server
Always use the Serena MCP server for all development tasks - it provides semantic code analysis tools.

### 2. Test-First Development (TDD)
1. Write failing test first
2. Implement minimal code to pass
3. Refactor while keeping tests green

### 3. Adding New Features

#### Example: Adding a New Analyzer
1. Create test in `tests/Unit/Analyzers/`
2. Implement analyzer in `src/Analyzers/`
3. Register in `OpenApiGenerator`
4. Add integration tests
5. Run full test suite
6. Test in demo-app

#### Example: Modifying AST Visitors
1. Write test for expected AST structure
2. Update visitor logic
3. Test with various code patterns
4. Verify performance is acceptable

### 4. Common Development Tasks

#### Working with Cache
```bash
php artisan spectrum:cache --clear    # Clear cache
php artisan spectrum:cache --stats    # Show cache statistics
```

#### Debugging Generation
1. Check error report at `storage/app/spectrum/error-report.json`
2. Enable profiling in config for performance analysis
3. Use incremental generation for large codebases

### 5. Performance Considerations
- Analyzers use parallel processing via spatie/fork
- Chunking for large codebases (configurable chunk size)
- Cache model schemas to avoid repeated analysis
- Monitor memory usage with MemoryManager

### 6. Error Handling Pattern
- All analyzers use ErrorCollector service
- Collect errors without failing the process
- Report all errors at the end
- Users can configure fail_on_error behavior