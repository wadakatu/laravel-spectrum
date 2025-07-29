# Architecture and Design Patterns

## Pipeline Architecture
Laravel Spectrum follows a clear pipeline architecture:

1. **Route Analysis** → Extract route information
2. **Controller Analysis** → Detect validation, responses, parameters  
3. **Resource Analysis** → Analyze API Resources/Transformers
4. **Schema Generation** → Convert to OpenAPI schemas
5. **Document Assembly** → Combine into complete OpenAPI spec

## Core Components

### Analyzers (`src/Analyzers/`)
- Extract information from code using AST parsing
- Each analyzer has a focused responsibility
- Return structured arrays that generators can consume
- Examples: RouteAnalyzer, FormRequestAnalyzer, ResourceAnalyzer

### Generators (`src/Generators/`)
- Convert analyzed data to OpenAPI format
- Handle schema generation and example creation
- OpenApiGenerator orchestrates the entire process

### Services (`src/Services/`)
- DocumentationCache: Performance caching layer
- FileWatcher: Monitors file changes for hot reload
- LiveReloadServer: WebSocket server for real-time updates

### Performance (`src/Performance/`)
- ParallelProcessor: Fork-based parallel processing
- ChunkProcessor: Process large codebases in chunks
- MemoryManager: Monitor and manage memory usage
- DependencyGraph: Track file dependencies

## Key Design Principles

1. **Zero Annotations**: Never require users to add annotations
2. **Smart Detection**: Infer as much as possible from existing code
3. **Graceful Degradation**: Continue processing even with errors
4. **Performance First**: Use parallel processing and caching
5. **Framework Agnostic**: Support both Laravel and Lumen

## Caching Strategy
- Document cached by route/resource hash
- Invalidated on file changes
- Configurable TTL and storage drivers
- Incremental generation for large projects