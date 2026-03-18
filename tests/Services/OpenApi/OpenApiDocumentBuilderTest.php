<?php

declare(strict_types=1);

namespace Tests\Services\OpenApi;

use PachyBase\Config;
use PachyBase\Database\Connection;
use PachyBase\Release\ProjectMetadata;
use PachyBase\Services\OpenApi\OpenApiDocumentBuilder;
use PHPUnit\Framework\TestCase;

class OpenApiDocumentBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(dirname(__DIR__, 3));
    }

    protected function tearDown(): void
    {
        Connection::reset();
        Config::reset();
        $_SERVER = [];
    }

    public function testBuildsDocumentFromRegisteredRoutesAndCrudMetadata(): void
    {
        $_SERVER['HTTP_HOST'] = 'localhost:8080';

        $document = (new OpenApiDocumentBuilder())->build();

        $this->assertSame('3.0.3', $document['openapi']);
        $this->assertSame(ProjectMetadata::version(), $document['info']['version']);
        $this->assertSame('http://localhost:8080', $document['servers'][0]['url']);
        $this->assertArrayHasKey('/openapi.json', $document['paths']);
        $this->assertArrayHasKey('/ai/schema', $document['paths']);
        $this->assertArrayHasKey('/ai/entities', $document['paths']);
        $this->assertArrayHasKey('/ai/entity/{name}', $document['paths']);
        $this->assertArrayHasKey('/api/auth/login', $document['paths']);
        $this->assertArrayHasKey('/api/system-settings', $document['paths']);
        $this->assertArrayHasKey('/api/system-settings/{id}', $document['paths']);
        $this->assertArrayHasKey('/api/api-tokens', $document['paths']);
        $this->assertSame([['bearerAuth' => []]], $document['paths']['/api/system-settings']['get']['security']);
        $this->assertSame(
            ['string', 'text', 'integer', 'float', 'boolean', 'json'],
            $document['components']['schemas']['CrudSystemSettingsCreateRequest']['properties']['value_type']['enum']
        );
        $this->assertSame(
            50,
            $document['paths']['/api/system-settings']['get']['parameters'][1]['schema']['maximum']
        );
        $this->assertArrayHasKey('CrudApiTokensItem', $document['components']['schemas']);
        $this->assertArrayNotHasKey(
            'token_hash',
            $document['components']['schemas']['CrudApiTokensItem']['properties']
        );
        $this->assertSame(
            'Read the AI-friendly schema document',
            $document['paths']['/ai/schema']['get']['summary']
        );
    }

    public function testDisabledDeleteOperationDocumentsReal405Behavior(): void
    {
        $document = (new OpenApiDocumentBuilder())->build();

        $responses = $document['paths']['/api/system-settings/{id}']['delete']['responses'];

        $this->assertArrayHasKey('405', $responses);
        $this->assertArrayNotHasKey('200', $responses);
    }
}
