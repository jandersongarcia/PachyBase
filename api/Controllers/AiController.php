<?php

declare(strict_types=1);

namespace PachyBase\Api\Controllers;

use PachyBase\Http\ApiResponse;
use PachyBase\Http\Request;
use PachyBase\Services\Ai\AiSchemaService;
use PachyBase\Services\Documentation\BuildDocumentRepository;

final class AiController
{
    public function __construct(
        private readonly ?AiSchemaService $service = null,
        private readonly ?BuildDocumentRepository $documents = null
    ) {
    }

    public function schema(Request $request): void
    {
        $this->respondWithSchema();
    }

    public function schemaFile(Request $request): void
    {
        $this->respondWithSchema();
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

    private function respondWithSchema(): void
    {
        ApiResponse::document(
            ($this->documents ?? new BuildDocumentRepository())->loadAiSchema()
            ?? ($this->service ?? new AiSchemaService())->buildSchema()
        );
    }
}
