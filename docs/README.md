# Laravel Spectrum Documentation

This is the documentation site for Laravel Spectrum, built using [Docusaurus](https://docusaurus.io/).

## Local Development

```bash
cd docs
npm install
npm start
```

This starts a local development server at http://localhost:3000. Changes are reflected live.

## Build

```bash
npm run build
```

Generates static content into the `build` directory.

## Directory Structure

```
docs/
├── docs/           # English documentation (Markdown)
├── i18n/ja/        # Japanese translations
├── src/            # Custom React components
├── static/         # Static assets (images, etc.)
└── docusaurus.config.ts
```

## Adding Documentation

1. Add/edit Markdown files in `docs/docs/`
2. For Japanese translations, edit files in `docs/i18n/ja/docusaurus-plugin-content-docs/current/`
3. Run `npm start` to preview changes
