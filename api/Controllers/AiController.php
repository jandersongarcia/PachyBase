<?php

declare(strict_types=1);

namespace PachyBase\Api\Controllers;

use PachyBase\Http\ApiResponse;
use PachyBase\Http\Request;
use PachyBase\Services\Ai\AiSchemaService;

final class AiController
{
    public function __construct(
        private readonly ?AiSchemaService $service = null
    ) {
    }

    public function schema(Request $request): void
    {
        ApiResponse::document(
            ($this->service ?? new AiSchemaService())->buildSchema()
        );
    }

    public function entities(Request $request): void
    {
        ApiResponse::document(
            ($this->service ?? new AiSchemaService())->listEntities()
        );
    }

    public function entity(Request $request, string $name): void
    {
        ApiResponse::document(
            ($this->service ?? new AiSchemaService())->describeEntity($name)
        );
    }
}
