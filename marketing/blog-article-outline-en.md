# Blog Article Outline (English)

## Article 1: Introduction Article (Dev.to / Hashnode)

### Title Options
- "Zero-Annotation API Documentation for Laravel: Meet Laravel Spectrum"
- "Stop Writing Swagger Annotations - Generate Laravel API Docs Automatically"
- "How to Add OpenAPI Docs to Your Laravel Project in 5 Minutes"

### Structure

#### 1. Hook (Problem Statement)
- API documentation is tedious but necessary
- Swagger-PHP requires countless annotations
- Docs drift out of sync with code

#### 2. Introducing Laravel Spectrum
- Zero-annotation approach
- Analyzes your existing code
- Just released v1.0.0!

#### 3. Quick Start (5 minutes)
```bash
composer require wadakatu/laravel-spectrum
php artisan vendor:publish --tag=spectrum-config
php artisan spectrum:generate
# That's it! Check storage/app/spectrum/openapi.json
```

#### 4. What Gets Analyzed
- FormRequest validation rules → Request schemas
- API Resources → Response schemas
- Controller return statements → Response types
- Auth middleware → Security schemes

#### 5. Killer Features
- `spectrum:watch` - Hot reload with browser refresh
- `spectrum:mock` - Built-in mock server
- Export to Postman/Insomnia

#### 6. Comparison Table
| Feature | Spectrum | Swagger-PHP | Scribe |
|---------|----------|-------------|--------|
| Zero Annotations | Yes | No | Partial |
| Setup Time | 5 min | Hours | 30 min |
| Mock Server | Yes | No | No |
| Live Reload | Yes | No | No |

#### 7. Conclusion & CTA
- GitHub link
- Documentation link
- Request for Stars

---

## Article 2: Comparison Article

### Title Options
- "Laravel API Documentation Tools Compared: 2025 Edition"
- "Swagger-PHP vs Scribe vs Laravel Spectrum: Which One Should You Choose?"

### Structure
1. Tool Overview
2. Feature Comparison Matrix
3. Use Case Recommendations
4. Migration Guide
5. ROI Analysis

---

## Article 3: Tutorial Article

### Title Options
- "Generate Complete OpenAPI Docs from Your Existing Laravel App"
- "The Fastest Way to Document Your Laravel API"

### Structure
1. Prerequisites
2. Installation
3. Configuration walkthrough
4. Advanced features
5. Customization tips
6. CI/CD integration

---

## Common CTA Section

```markdown
## Links

- GitHub: https://github.com/wadakatu/laravel-spectrum
- Documentation: [docs URL]
- Packagist: https://packagist.org/packages/wadakatu/laravel-spectrum

If you found this useful, please give us a star on GitHub!
```

## Tags

Dev.to: `#laravel` `#php` `#openapi` `#swagger` `#webdev`
Hashnode: `Laravel` `PHP` `API Development` `Documentation`
