<?php

declare(strict_types=1);

namespace PachyBase\Api\Controllers;

use PachyBase\Http\ApiResponse;
use PachyBase\Http\Request;
use PachyBase\Services\Documentation\BuildDocumentRepository;
use PachyBase\Services\OpenApi\OpenApiDocumentBuilder;

final class OpenApiController
{
    public function __construct(
        private readonly ?OpenApiDocumentBuilder $builder = null,
        private readonly ?BuildDocumentRepository $documents = null
    ) {
    }

    public function document(Request $request): void
    {
        ApiResponse::document(
            ($this->documents ?? new BuildDocumentRepository())->loadOpenApi()
            ?? ($this->builder ?? new OpenApiDocumentBuilder())->build($request)
        );
    }
}
