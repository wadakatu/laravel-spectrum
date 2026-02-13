<?php

declare(strict_types=1);

namespace LaravelSpectrum\Generators;

use LaravelSpectrum\DTO\CallbackInfo;

/**
 * Generates OpenAPI callback objects from CallbackInfo DTOs.
 *
 * Converts callback definitions into the OpenAPI callbacks structure:
 * callbacks -> {name} -> {expression} -> {method} -> {operation}
 */
class CallbackGenerator
{
    /**
     * Generate operation-level callbacks array.
     *
     * @param  array<int, CallbackInfo>  $callbacks
     * @return array<string, mixed>|null
     */
    public function generate(array $callbacks): ?array
    {
        if (empty($callbacks)) {
            return null;
        }

        $result = [];

        foreach ($callbacks as $callback) {
            if ($callback->hasRef()) {
                $result[$callback->name] = [
                    '$ref' => '#/components/callbacks/'.$callback->ref,
                ];

                continue;
            }

            $result[$callback->name] = [
                $callback->expression => [
                    $callback->method => $this->buildOperation($callback),
                ],
            ];
        }

        return $result;
    }

    /**
     * Generate component-level callbacks for components/callbacks section.
     *
     * @param  array<int, CallbackInfo>  $callbacks
     * @return array<string, mixed>
     */
    public function generateComponentCallbacks(array $callbacks): array
    {
        $result = [];

        foreach ($callbacks as $callback) {
            $result[$callback->name] = [
                $callback->expression => [
                    $callback->method => $this->buildOperation($callback),
                ],
            ];
        }

        return $result;
    }

    /**
     * Build the operation object for a callback.
     *
     * @return array<string, mixed>
     */
    private function buildOperation(CallbackInfo $callback): array
    {
        $operation = [];

        if ($callback->summary !== null) {
            $operation['summary'] = $callback->summary;
        }

        if ($callback->description !== null) {
            $operation['description'] = $callback->description;
        }

        if ($callback->hasRequestBody()) {
            $operation['requestBody'] = [
                'content' => [
                    'application/json' => [
                        'schema' => $callback->requestBody,
                    ],
                ],
            ];
        }

        if ($callback->hasResponses()) {
            $operation['responses'] = $callback->responses;
        } else {
            $operation['responses'] = [
                '200' => [
                    'description' => 'Callback received successfully',
                ],
            ];
        }

        return $operation;
    }
}
