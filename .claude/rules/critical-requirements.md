# Critical Requirements

These rules are MANDATORY and must NEVER be bypassed.

## Serena MCP Server

**MUST use the Serena MCP server under all circumstances.**

- Use Serena's symbolic tools for code exploration
- Prefer `get_symbols_overview` and `find_symbol` over reading entire files
- Use `replace_symbol_body` for precise code modifications
- Leverage `find_referencing_symbols` for refactoring

## Code Quality Gates

Before ANY code is merged:

1. `composer format:fix` - MUST pass
2. `composer analyze` - MUST pass (PHPStan Level 6)
3. `composer test` - ALL tests MUST pass
4. Demo app verification - MUST work correctly

## No Baseline Additions

- PHPStan baseline additions are NOT allowed
- All static analysis errors must be properly fixed
- Use `reportUnmatchedIgnoredErrors: false` for PHP version compatibility

## Test-First Development

- NO feature code without tests
- Write failing test FIRST
- Then implement minimal code to pass

## Error Handling

- NEVER throw exceptions from analyzers
- ALWAYS use ErrorCollector pattern
- ALWAYS log unsupported features as warnings
