<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

use Illuminate\Support\Facades\Log;

/**
 * Represents an OpenAPI Server object.
 *
 * @see https://spec.openapis.org/oas/v3.1.0#server-object
 */
final readonly class OpenApiServer
{
    /**
     * @param  string  $url  The URL to the target host
     * @param  string  $description  An optional description of the host
     * @param  array<string, array{default: string, description?: string, enum?: array<int, string>}>|null  $variables  Server variables
     */
    public function __construct(
        public string $url,
        public string $description = '',
        public ?array $variables = null,
    ) {}

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            url: $data['url'] ?? '',
            description: $data['description'] ?? '',
            variables: $data['variables'] ?? null,
        );
    }

    /**
     * Convert to OpenAPI server array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'url' => $this->url,
        ];

        if ($this->description !== '') {
            $result['description'] = $this->description;
        }

        if ($this->variables !== null) {
            $result['variables'] = $this->variables;
        }

        return $result;
    }

    /**
     * Check if this server has variables.
     */
    public function hasVariables(): bool
    {
        return $this->variables !== null && count($this->variables) > 0;
    }

    /**
     * Check if this server has a description.
     */
    public function hasDescription(): bool
    {
        return $this->description !== '';
    }

    /**
     * Build the servers array from spectrum config, falling back to APP_URL/api.
     *
     * Reads 'spectrum.servers' config. If empty or not set, returns a single
     * server entry derived from 'app.url'. Invalid (non-array) entries are
     * skipped with a warning.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function buildServersFromConfig(): array
    {
        $servers = config('spectrum.servers');

        if (! is_array($servers) || empty($servers)) {
            return self::defaultServers();
        }

        $result = [];
        foreach ($servers as $index => $server) {
            if (! is_array($server)) {
                Log::warning('Invalid server entry in spectrum.servers config at index '.$index.': expected array, got '.gettype($server).'. Skipping.');

                continue;
            }

            if (! isset($server['url']) || ! is_string($server['url']) || $server['url'] === '') {
                Log::warning('Server entry at index '.$index.' in spectrum.servers config is missing a valid "url" field. Skipping.');

                continue;
            }

            if (isset($server['variables']) && is_array($server['variables'])) {
                foreach ($server['variables'] as $varName => $varDef) {
                    if (! is_array($varDef) || ! isset($varDef['default']) || ! is_string($varDef['default'])) {
                        Log::warning('Server variable "'.$varName.'" at index '.$index.' is missing a valid "default" field. Removing this variable.');
                        unset($server['variables'][$varName]);
                    }
                }
                if (empty($server['variables'])) {
                    unset($server['variables']);
                }
            }

            try {
                $result[] = self::fromArray($server)->toArray();
            } catch (\Throwable $e) {
                Log::warning('Failed to parse server entry at index '.$index.' in spectrum.servers config: '.$e->getMessage().'. Skipping.');

                continue;
            }
        }

        if ($result === []) {
            Log::warning('All entries in spectrum.servers config were invalid. Falling back to default server (APP_URL/api).');

            return self::defaultServers();
        }

        return $result;
    }

    /**
     * Get the default server configuration derived from app.url.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function defaultServers(): array
    {
        $appUrl = config('app.url', 'http://localhost');
        if (! is_string($appUrl) || $appUrl === '') {
            Log::warning('app.url config is not a valid string (got '.gettype($appUrl).'). Falling back to http://localhost.');
            $appUrl = 'http://localhost';
        }

        return [
            [
                'url' => rtrim($appUrl, '/').'/api',
                'description' => 'API Server',
            ],
        ];
    }
}
