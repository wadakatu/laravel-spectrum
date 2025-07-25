# CI/CD Integration Guide

This guide explains how to integrate Laravel Spectrum into your Continuous Integration/Deployment (CI/CD) pipeline.

## 🎯 Overview

Integrating Laravel Spectrum into your CI/CD pipeline provides the following benefits:

- **Automated Documentation Generation**: Automatically update documentation when code changes
- **Quality Checks**: Verify the completeness of API documentation
- **Automatic Publishing**: Automatically host generated documentation
- **Version Control**: Maintain documentation version history

## 🔧 GitHub Actions

### Basic Workflow

```yaml
name: Generate API Documentation

on:
  push:
    branches: [ main, develop ]
    paths:
      - 'app/Http/Controllers/**'
      - 'app/Http/Requests/**'
      - 'app/Http/Resources/**'
      - 'routes/**'
  pull_request:
    branches: [ main ]

jobs:
  generate-docs:
    runs-on: ubuntu-latest
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, dom, fileinfo, mysql
          coverage: none

      - name: Get composer cache directory
        id: composer-cache
        run: echo "dir=$(composer config cache-files-dir)" >> $GITHUB_OUTPUT

      - name: Cache composer dependencies
        uses: actions/cache@v3
        with:
          path: ${{ steps.composer-cache.outputs.dir }}
          key: ${{ runner.os }}-composer-${{ hashFiles('**/composer.lock') }}
          restore-keys: ${{ runner.os }}-composer-

      - name: Install dependencies
        run: composer install --no-ansi --no-interaction --no-scripts --no-progress --prefer-dist

      - name: Generate API documentation
        run: php artisan spectrum:generate

      - name: Upload documentation
        uses: actions/upload-artifact@v3
        with:
          name: api-documentation
          path: storage/app/spectrum/
          retention-days: 30
```

### Automatic Deployment to GitHub Pages

```yaml
name: Deploy Documentation

on:
  push:
    branches: [ main ]
  workflow_dispatch:

jobs:
  build-and-deploy:
    runs-on: ubuntu-latest
    permissions:
      contents: read
      pages: write
      id-token: write

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Generate documentation
        run: |
          php artisan spectrum:generate
          php artisan spectrum:export:postman
          php artisan spectrum:export:insomnia

      - name: Copy to docs directory
        run: |
          mkdir -p docs
          cp storage/app/spectrum/openapi.json docs/
          cp -r storage/app/spectrum/postman docs/
          cp -r storage/app/spectrum/insomnia docs/

      - name: Create index.html
        run: |
          cat > docs/index.html << 'EOF'
          <!DOCTYPE html>
          <html>
          <head>
              <title>API Documentation</title>
              <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist/swagger-ui.css">
          </head>
          <body>
              <div id="swagger-ui"></div>
              <script src="https://unpkg.com/swagger-ui-dist/swagger-ui-bundle.js"></script>
              <script>
              window.onload = function() {
                  SwaggerUIBundle({
                      url: "./openapi.json",
                      dom_id: '#swagger-ui',
                      deepLinking: true,
                      presets: [
                          SwaggerUIBundle.presets.apis,
                      ],
                  });
              };
              </script>
          </body>
          </html>
          EOF

      - name: Setup Pages
        uses: actions/configure-pages@v3

      - name: Upload artifact
        uses: actions/upload-pages-artifact@v2
        with:
          path: ./docs

      - name: Deploy to GitHub Pages
        id: deployment
        uses: actions/deploy-pages@v2
```

### Pull Request Validation

```yaml
name: Validate API Documentation

on:
  pull_request:
    types: [opened, synchronize, reopened]

jobs:
  validate:
    runs-on: ubuntu-latest
    
    steps:
      - name: Checkout PR branch
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Generate documentation
        run: |
          php artisan spectrum:generate --dry-run
          
      - name: Check for errors
        run: |
          if [ -f storage/logs/spectrum.log ]; then
            if grep -q "ERROR" storage/logs/spectrum.log; then
              echo "Errors found in documentation generation:"
              cat storage/logs/spectrum.log
              exit 1
            fi
          fi

      - name: Comment PR
        uses: actions/github-script@v6
        if: always()
        with:
          script: |
            const message = `### 📚 API Documentation Check
            
            ${context.job.status === 'success' ? '✅ Documentation generated successfully!' : '❌ Documentation generation failed.'}
            
            View the [workflow run](${context.serverUrl}/${context.repo.owner}/${context.repo.repo}/actions/runs/${context.runId})`;
            
            github.rest.issues.createComment({
              issue_number: context.issue.number,
              owner: context.repo.owner,
              repo: context.repo.repo,
              body: message
            });
```

## 🚢 GitLab CI/CD

### .gitlab-ci.yml

```yaml
stages:
  - build
  - test
  - generate
  - deploy

variables:
  MYSQL_DATABASE: laravel
  MYSQL_ROOT_PASSWORD: secret

# Cache configuration
.composer_cache:
  cache:
    key: ${CI_COMMIT_REF_SLUG}-composer
    paths:
      - vendor/

# PHP environment setup
.php_setup:
  image: php:8.2-cli
  before_script:
    - apt-get update && apt-get install -y git unzip libzip-dev
    - docker-php-ext-install zip pdo_mysql
    - curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install dependencies
install:
  extends:
    - .php_setup
    - .composer_cache
  stage: build
  script:
    - composer install --prefer-dist --no-ansi --no-interaction --no-progress
  artifacts:
    paths:
      - vendor/
    expire_in: 1 hour

# Run tests
test:
  extends: .php_setup
  stage: test
  services:
    - mysql:8.0
  dependencies:
    - install
  script:
    - cp .env.example .env
    - php artisan key:generate
    - php artisan migrate --force
    - php artisan test

# Generate documentation
generate-docs:
  extends: .php_setup
  stage: generate
  dependencies:
    - install
  script:
    - php artisan spectrum:generate
    - php artisan spectrum:export:postman
    - php artisan spectrum:export:insomnia
  artifacts:
    paths:
      - storage/app/spectrum/
    expire_in: 1 week
  only:
    - main
    - develop

# Deploy to GitLab Pages
pages:
  stage: deploy
  dependencies:
    - generate-docs
  script:
    - mkdir -p public
    - cp storage/app/spectrum/openapi.json public/
    - |
      cat > public/index.html << 'EOF'
      <!DOCTYPE html>
      <html>
      <head>
          <title>${CI_PROJECT_NAME} API Documentation</title>
          <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist/swagger-ui.css">
      </head>
      <body>
          <div id="swagger-ui"></div>
          <script src="https://unpkg.com/swagger-ui-dist/swagger-ui-bundle.js"></script>
          <script>
          window.onload = function() {
              SwaggerUIBundle({
                  url: "./openapi.json",
                  dom_id: '#swagger-ui',
              });
          };
          </script>
      </body>
      </html>
      EOF
  artifacts:
    paths:
      - public
  only:
    - main
```

## 🔵 Bitbucket Pipelines

### bitbucket-pipelines.yml

```yaml
image: php:8.2-cli

definitions:
  caches:
    composer: vendor/

pipelines:
  default:
    - step:
        name: Install Dependencies
        caches:
          - composer
        script:
          - apt-get update && apt-get install -y git unzip
          - curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
          - composer install
        artifacts:
          - vendor/**

    - step:
        name: Generate Documentation
        script:
          - php artisan spectrum:generate
          - php artisan spectrum:export:postman
        artifacts:
          - storage/app/spectrum/**

  branches:
    main:
      - step:
          name: Install and Generate
          caches:
            - composer
          script:
            - apt-get update && apt-get install -y git unzip zip
            - curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
            - composer install
            - php artisan spectrum:generate
          artifacts:
            - storage/app/spectrum/**
            
      - step:
          name: Deploy to S3
          deployment: production
          script:
            - pipe: atlassian/aws-s3-deploy:1.1.0
              variables:
                AWS_ACCESS_KEY_ID: $AWS_ACCESS_KEY_ID
                AWS_SECRET_ACCESS_KEY: $AWS_SECRET_ACCESS_KEY
                AWS_DEFAULT_REGION: $AWS_DEFAULT_REGION
                S3_BUCKET: $S3_BUCKET
                LOCAL_PATH: 'storage/app/spectrum'
                EXTRA_ARGS: '--acl public-read'
```

## 🟢 CircleCI

### .circleci/config.yml

```yaml
version: 2.1

executors:
  php-executor:
    docker:
      - image: cimg/php:8.2
      - image: cimg/mysql:8.0
        environment:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: test_db

jobs:
  build:
    executor: php-executor
    steps:
      - checkout
      
      - restore_cache:
          keys:
            - v1-composer-{{ checksum "composer.lock" }}
            - v1-composer-
            
      - run:
          name: Install Dependencies
          command: |
            sudo apt-get update
            sudo apt-get install -y libzip-dev
            sudo docker-php-ext-install zip pdo_mysql
            composer install -n --prefer-dist
            
      - save_cache:
          key: v1-composer-{{ checksum "composer.lock" }}
          paths:
            - vendor
            
      - persist_to_workspace:
          root: .
          paths:
            - vendor

  generate-docs:
    executor: php-executor
    steps:
      - checkout
      
      - attach_workspace:
          at: .
          
      - run:
          name: Setup Environment
          command: |
            cp .env.example .env
            php artisan key:generate
            
      - run:
          name: Generate Documentation
          command: |
            php artisan spectrum:generate
            php artisan spectrum:export:postman --environment
            php artisan spectrum:export:insomnia
            
      - store_artifacts:
          path: storage/app/spectrum
          destination: api-documentation
          
      - persist_to_workspace:
          root: .
          paths:
            - storage/app/spectrum

  deploy:
    docker:
      - image: cimg/base:stable
    steps:
      - checkout
      
      - attach_workspace:
          at: .
          
      - run:
          name: Install AWS CLI
          command: |
            curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip"
            unzip awscliv2.zip
            sudo ./aws/install
            
      - run:
          name: Deploy to S3
          command: |
            aws s3 sync storage/app/spectrum/ s3://${S3_BUCKET}/api-docs/ --delete
            aws cloudfront create-invalidation --distribution-id ${CLOUDFRONT_ID} --paths "/*"

workflows:
  version: 2
  build-and-deploy:
    jobs:
      - build
      - generate-docs:
          requires:
            - build
      - deploy:
          requires:
            - generate-docs
          filters:
            branches:
              only: main
```

## 🐳 Docker Integration

### Dockerfile.docs

```dockerfile
# Build stage
FROM php:8.2-cli AS builder

WORKDIR /app

# Install required extensions
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip pdo_mysql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --optimize

# Generate documentation
RUN php artisan spectrum:generate

# Production stage
FROM nginx:alpine

# Copy documentation files
COPY --from=builder /app/storage/app/spectrum /usr/share/nginx/html
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf

# Add Swagger UI
RUN apk add --no-cache curl && \
    mkdir -p /usr/share/nginx/html/swagger-ui && \
    curl -L https://github.com/swagger-api/swagger-ui/archive/v4.19.1.tar.gz | tar xz -C /tmp && \
    cp -r /tmp/swagger-ui-*/dist/* /usr/share/nginx/html/swagger-ui/

# Create index.html
RUN echo '<!DOCTYPE html><html><head><title>API Documentation</title><link rel="stylesheet" href="/swagger-ui/swagger-ui.css"></head><body><div id="swagger-ui"></div><script src="/swagger-ui/swagger-ui-bundle.js"></script><script>SwaggerUIBundle({url: "/openapi.json", dom_id: "#swagger-ui"});</script></body></html>' > /usr/share/nginx/html/index.html

EXPOSE 80
```

### docker-compose.ci.yml

```yaml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    volumes:
      - ./storage/app/spectrum:/app/storage/app/spectrum
    command: >
      sh -c "
        composer install &&
        php artisan spectrum:generate &&
        php artisan spectrum:export:postman &&
        php artisan spectrum:export:insomnia
      "
    environment:
      DB_CONNECTION: mysql
      DB_HOST: db
      DB_PORT: 3306
      DB_DATABASE: laravel
      DB_USERNAME: root
      DB_PASSWORD: secret

  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_DATABASE: laravel
    tmpfs:
      - /var/lib/mysql

  docs:
    build:
      context: .
      dockerfile: Dockerfile.docs
    ports:
      - "8080:80"
    depends_on:
      - app
```

## 🚀 Automation Best Practices

### 1. Environment Variables Management

```yaml
# GitHub Actions
env:
  APP_ENV: testing
  APP_KEY: ${{ secrets.APP_KEY }}
  DB_CONNECTION: sqlite
  DB_DATABASE: :memory:
  SPECTRUM_CACHE_ENABLED: false
```

### 2. Conditional Execution

```yaml
# Execute only when API files change
on:
  push:
    paths:
      - 'app/Http/**'
      - 'routes/**'
      - 'config/spectrum.php'
```

### 3. Utilizing Parallel Processing

```yaml
- name: Generate documentation (optimized)
  run: |
    php artisan spectrum:generate:optimized \
      --workers=4 \
      --chunk-size=100
```

### 4. Cache Utilization

```yaml
- name: Cache Spectrum
  uses: actions/cache@v3
  with:
    path: storage/app/spectrum/cache
    key: ${{ runner.os }}-spectrum-${{ hashFiles('app/Http/**') }}
    restore-keys: |
      ${{ runner.os }}-spectrum-
```

### 5. Notification Setup

```yaml
- name: Notify Slack
  if: failure()
  uses: 8398a7/action-slack@v3
  with:
    status: ${{ job.status }}
    text: 'API Documentation generation failed!'
    webhook_url: ${{ secrets.SLACK_WEBHOOK }}
```

## 📊 Quality Checks

### OpenAPI Specification Validation

```yaml
- name: Validate OpenAPI specification
  run: |
    npm install -g @apidevtools/swagger-cli
    swagger-cli validate storage/app/spectrum/openapi.json
```

### Breaking Changes Detection

```yaml
- name: Check for breaking changes
  uses: oasdiff/oasdiff-action@v0.0.8
  with:
    base: 'https://api.example.com/openapi.json'
    revision: './storage/app/spectrum/openapi.json'
    fail-on: 'breaking'
```

## 🔍 Monitoring and Alerts

### Documentation Generation Monitoring

```yaml
- name: Monitor generation time
  run: |
    START_TIME=$(date +%s)
    php artisan spectrum:generate
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    
    if [ $DURATION -gt 300 ]; then
      echo "::warning::Documentation generation took ${DURATION}s (threshold: 300s)"
    fi
```

### Error Collection

```yaml
- name: Collect error logs
  if: failure()
  run: |
    if [ -f storage/logs/spectrum.log ]; then
      echo "### Spectrum Errors" >> $GITHUB_STEP_SUMMARY
      echo '```' >> $GITHUB_STEP_SUMMARY
      tail -n 50 storage/logs/spectrum.log >> $GITHUB_STEP_SUMMARY
      echo '```' >> $GITHUB_STEP_SUMMARY
    fi
```

## 📚 Related Documentation

- [Basic Usage](./basic-usage.md) - Manual documentation generation
- [Configuration Reference](./config-reference.md) - CI/CD-oriented configuration
- [Performance Optimization](./performance.md) - CI/CD for large projects