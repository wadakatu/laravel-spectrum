# Suggested Commands for Laravel Spectrum Development

## Testing Commands
```bash
composer test                               # Run all PHPUnit tests
composer test-coverage                      # Generate HTML coverage report
vendor/bin/phpunit tests/Unit/FormRequestAnalyzerTest.php  # Run specific test file
vendor/bin/phpunit --filter testMethodName  # Run specific test method
```

## Code Quality Commands
```bash
composer format                             # Check code style (dry run)
composer format:fix                         # Auto-fix code style issues
composer analyze                            # Run PHPStan static analysis
```

## Application Commands
```bash
php artisan spectrum:generate              # Generate API documentation
php artisan spectrum:watch                 # Start with hot reload
php artisan spectrum:cache                 # Manage documentation cache
php artisan spectrum:mock                  # Launch mock API server
php artisan spectrum:export:postman        # Export to Postman collection
php artisan spectrum:export:insomnia       # Export to Insomnia collection
```

## System Commands (macOS/Darwin)
```bash
git status                                 # Check git status
git diff                                   # View uncommitted changes
git log --oneline -10                     # View recent commits
ls -la                                     # List files with details
find . -name "*.php" -type f              # Find PHP files
grep -r "pattern" src/                    # Search for pattern in source
say -v Daniel "Mission Accomplished!"      # Audio notification (task complete)
say -v Daniel "Waiting..."                 # Audio notification (waiting)
say -v Daniel "Error Occurs."             # Audio notification (error)
say -v Daniel "Need Confirmation."        # Audio notification (confirmation)
```

## Demo App Testing
```bash
cd demo-app/laravel-12-app
php artisan spectrum:generate
# Check generated output in storage/app/spectrum/openapi.json
```