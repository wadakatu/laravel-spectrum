# Laravel Spectrum Project Overview

Laravel Spectrum is a zero-annotation API documentation generator for Laravel and Lumen applications. It analyzes existing code to automatically generate OpenAPI 3.0 documentation without requiring annotations or code changes.

## Key Features
- Zero-configuration required - just install and run
- Smart detection of FormRequests, validation rules, and API Resources
- Real-time updates that instantly reflect code changes
- Export features for Postman and Insomnia
- Built-in mock server that simulates API from OpenAPI documentation
- High performance even for large-scale projects

## Tech Stack
- **PHP**: 8.1+ (supports PHP 8.1, 8.2, 8.3, 8.4)
- **Laravel**: 10.x, 11.x, 12.x
- **Lumen**: Supported via compatibility layer
- **Dependencies**:
  - nikic/php-parser: For AST parsing and static code analysis
  - spatie/fork: For parallel processing
  - workerman/workerman: For WebSocket/live reload server
  - fakerphp/faker: For generating realistic example data

## Project Structure
```
├── src/                    # Main source code
│   ├── Analyzers/         # Extract information from code
│   ├── Generators/        # Convert analyzed data to OpenAPI format
│   ├── Services/          # Supporting functionality
│   ├── Console/           # Artisan commands
│   ├── MockServer/        # Mock API server implementation
│   ├── Performance/       # Performance optimization tools
│   └── Support/           # Helper classes
├── tests/                  # Test suite
├── demo-app/              # Demo application for testing
├── docs/                  # Documentation
└── config/                # Configuration files
```