# Task Completion Checklist

When completing any development task on Laravel Spectrum, follow this checklist:

## 1. Code Quality Checks (MANDATORY)
```bash
composer format:fix     # Fix code style
composer analyze        # Pass static analysis
composer test           # All tests must pass
```

## 2. Test in Demo App (MANDATORY)
```bash
cd demo-app/laravel-app
php artisan spectrum:generate
# Verify generated output in storage/app/spectrum/openapi.json
```

## 3. Audio Notification
```bash
say -v Daniel "Mission Accomplished!"
```

## 4. Pre-PR Requirements
If preparing for a pull request:
1. Ensure all tests pass
2. Code style is clean (Pint)
3. Static analysis passes (PHPStan)
4. Demo app generates valid OpenAPI
5. No new PHPStan baseline entries

## Important Notes
- NEVER commit without user's explicit request
- If unable to find lint/typecheck commands, ask user and suggest saving to CLAUDE.md
- Follow test-first development approach
- Verify real-world functionality in demo-app