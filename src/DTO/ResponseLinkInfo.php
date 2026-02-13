<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents an OpenAPI Link Object attached to a response.
 *
 * Link objects describe relationships to other operations using runtime
 * expressions such as $response.body#/id.
 */
final readonly class ResponseLinkInfo
{
    /**
     * @param  int|string  $statusCode  Response status code to attach this link to (e.g. 200, "201")
     * @param  string  $name  Link name (key under responses.<status>.links)
     * @param  string|null  $operationId  Target operationId
     * @param  string|null  $operationRef  Target operationRef
     * @param  array<string, mixed>|null  $parameters  Runtime expression parameter map
     * @param  mixed  $requestBody  Runtime expression request body
     * @param  string|null  $description  Link description
     * @param  array<string, mixed>|null  $server  Optional server override
     */
    public function __construct(
        public int|string $statusCode,
        public string $name,
        public ?string $operationId = null,
        public ?string $operationRef = null,
        public ?array $parameters = null,
        public mixed $requestBody = null,
        public ?string $description = null,
        public ?array $server = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['statusCode']) && ! isset($data['status_code'])) {
            throw new \InvalidArgumentException(
                "Response link config requires a 'statusCode' key."
            );
        }
        if (! isset($data['name']) || ! is_string($data['name']) || trim($data['name']) === '') {
            throw new \InvalidArgumentException(
                "Response link config requires a non-empty 'name' key (string)."
            );
        }

        $operationId = $data['operationId'] ?? $data['operation_id'] ?? null;
        $operationRef = $data['operationRef'] ?? $data['operation_ref'] ?? null;
        if (! is_string($operationId) && $operationId !== null) {
            throw new \InvalidArgumentException("Response link '{$data['name']}' has invalid 'operationId' value.");
        }
        if (! is_string($operationRef) && $operationRef !== null) {
            throw new \InvalidArgumentException("Response link '{$data['name']}' has invalid 'operationRef' value.");
        }
        if (is_string($operationId) && trim($operationId) === '') {
            throw new \InvalidArgumentException("Response link '{$data['name']}' has empty 'operationId' value.");
        }
        if (is_string($operationRef) && trim($operationRef) === '') {
            throw new \InvalidArgumentException("Response link '{$data['name']}' has empty 'operationRef' value.");
        }
        if ($operationId === null && $operationRef === null) {
            throw new \InvalidArgumentException(
                "Response link '{$data['name']}' requires either 'operationId' or 'operationRef'."
            );
        }
        if ($operationId !== null && $operationRef !== null) {
            throw new \InvalidArgumentException(
                "Response link '{$data['name']}' cannot specify both 'operationId' and 'operationRef'."
            );
        }

        $statusCode = $data['statusCode'] ?? $data['status_code'];
        if (! is_string($statusCode) && ! is_int($statusCode)) {
            throw new \InvalidArgumentException(
                "Response link '{$data['name']}' has invalid 'statusCode' value."
            );
        }
        if (is_string($statusCode) && trim($statusCode) === '') {
            throw new \InvalidArgumentException(
                "Response link '{$data['name']}' has empty 'statusCode' value."
            );
        }

        $parameters = $data['parameters'] ?? null;
        if (! is_array($parameters) && $parameters !== null) {
            throw new \InvalidArgumentException(
                "Response link '{$data['name']}' has invalid 'parameters' value."
            );
        }

        $server = $data['server'] ?? null;
        if (! is_array($server) && $server !== null) {
            throw new \InvalidArgumentException(
                "Response link '{$data['name']}' has invalid 'server' value."
            );
        }

        $description = $data['description'] ?? null;
        if (! is_string($description) && $description !== null) {
            throw new \InvalidArgumentException(
                "Response link '{$data['name']}' has invalid 'description' value."
            );
        }

        return new self(
            statusCode: $statusCode,
            name: $data['name'],
            operationId: $operationId,
            operationRef: $operationRef,
            parameters: $parameters,
            requestBody: $data['requestBody'] ?? $data['request_body'] ?? null,
            description: $description,
            server: $server,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'statusCode' => $this->statusCode,
            'name' => $this->name,
        ];

        if ($this->operationId !== null) {
            $result['operationId'] = $this->operationId;
        }
        if ($this->operationRef !== null) {
            $result['operationRef'] = $this->operationRef;
        }
        if ($this->parameters !== null) {
            $result['parameters'] = $this->parameters;
        }
        if ($this->requestBody !== null) {
            $result['requestBody'] = $this->requestBody;
        }
        if ($this->description !== null) {
            $result['description'] = $this->description;
        }
        if ($this->server !== null) {
            $result['server'] = $this->server;
        }

        return $result;
    }

    /**
     * Convert to OpenAPI Link Object (without status code/name wrapper).
     *
     * @return array<string, mixed>
     */
    public function toLinkObject(): array
    {
        $result = [];

        if ($this->operationId !== null) {
            $result['operationId'] = $this->operationId;
        }
        if ($this->operationRef !== null) {
            $result['operationRef'] = $this->operationRef;
        }
        if ($this->parameters !== null) {
            $result['parameters'] = $this->parameters;
        }
        if ($this->requestBody !== null) {
            $result['requestBody'] = $this->requestBody;
        }
        if ($this->description !== null) {
            $result['description'] = $this->description;
        }
        if ($this->server !== null) {
            $result['server'] = $this->server;
        }

        return $result;
    }
}
