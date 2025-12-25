# Development Workflow Rules

## Pre-PR Checklist (Required)

**PR作成前に必ずcode-qualityスキルを実行すること。**

Execute these steps in order:

1. `composer format:fix` - Auto-fix code style issues
2. `composer analyze` - Pass PHPStan static analysis
3. `composer test` - All tests must pass
4. Test in demo-app - Verify real-world functionality

## Post-PR Review (Required)

**PR作成後に必ずpost-pr-reviewスキルを実行すること。**

1. Run `/post-pr-review` skill after PR creation
2. Review all Critical and Important issues
3. Address suggestions where appropriate
4. Push fixes and re-run if needed

## Git Workflow

### Branch Naming
- Features: `feature/{description}`
- Bug fixes: `fix/{description}`
- Refactoring: `refactor/{description}`

### Commit Messages
- Use conventional commits format
- Be concise but descriptive
- Reference issues when applicable

### Pull Requests
- Create from feature branch to `main`
- Include summary of changes
- Add test plan if applicable

## Available Skills

### Code Quality
- `/code-quality` - Run all quality checks (PHPStan, Pint, PHPUnit)

### PR Review
- `/post-pr-review` - Self-review and fix PR issues
- `/pr-review-toolkit:review-pr` - Comprehensive PR review

## CI/CD Considerations

- Tests run on PHP 8.1, 8.2, 8.3, 8.4
- PHPStan baseline must work across all PHP versions
- Use `reportUnmatchedIgnoredErrors: false` for cross-version compatibility
