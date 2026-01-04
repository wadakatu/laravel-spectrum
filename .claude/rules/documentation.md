# Documentation Rules

## When to Update Documentation

Documentation updates are required when:

1. **New Features** - Any new analyzer, generator, or command
2. **API Changes** - Changes to public methods, configuration options, or CLI arguments
3. **Behavior Changes** - Modified default behavior or output format
4. **Bug Fixes** - If the fix changes documented behavior

## Documentation Structure

```
docs/
├── docs/           # English documentation (primary)
├── i18n/ja/        # Japanese translations
└── docusaurus.config.ts
```

## How to Update

### English Documentation
Edit files in `docs/docs/`:
- `installation.md` - Setup instructions
- `basic-usage.md` - Getting started
- `config-reference.md` - Configuration options
- `cli-reference.md` - Artisan commands
- `api-reference.md` - Public API

### Japanese Translations
Edit corresponding files in `docs/i18n/ja/docusaurus-plugin-content-docs/current/`

## Documentation Checklist

Before completing a feature or fix, verify:

- [ ] New features are documented
- [ ] Changed behavior is reflected in docs
- [ ] Code examples are accurate and tested
- [ ] Both EN and JA docs are updated (if applicable)

## Building Documentation Locally

```bash
cd docs
npm install
npm start    # Preview at localhost:3000
npm run build  # Verify build succeeds
```

## Do NOT Update Docs For

- Internal refactoring with no public API changes
- Test-only changes
- CI/CD configuration changes
- Code style fixes
