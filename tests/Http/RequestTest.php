<?php

declare(strict_types=1);

namespace Tests\Http;

use PachyBase\Http\Request;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SERVER = [];
        $_GET = [];
        $_POST = [];
    }

    public function testGetMethodNormalizesToUppercase(): void
    {
        $request = new Request('get', '/');
        $this->assertSame('GET', $request->getMethod());
    }

    public function testGetPathDefaultsToSlash(): void
    {
        $request = new Request('GET', '');
        $this->assertSame('/', $request->getPath());
    }

    public function testQueryReturnsAllWhenNoKeyGiven(): void
    {
        $request = new Request('GET', '/', ['page' => '2', 'limit' => '10']);
        $this->assertSame(['page' => '2', 'limit' => '10'], $request->query());
    }

    public function testQueryReturnsSingleKey(): void
    {
        $request = new Request('GET', '/', ['page' => '3']);
        $this->assertSame('3', $request->query('page'));
    }

    public function testQueryReturnsDefaultForMissingKey(): void
    {
        $request = new Request('GET', '/');
        $this->assertSame('default', $request->query('missing', 'default'));
    }

    public function testJsonReturnsBody(): void
    {
        $request = new Request('POST', '/', [], [], ['name' => 'PachyBase']);
        $this->assertSame('PachyBase', $request->json('name'));
    }

    public function testHeaderLookupIsCaseInsensitive(): void
    {
        $request = new Request('GET', '/', [], ['Content-Type' => 'application/json']);
        $this->assertSame('application/json', $request->header('content-type'));
    }

    public function testCaptureReadsJsonBodyFromContentTypeFallback(): void
    {
        $request = Request::capture(
            [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/users',
                'CONTENT_TYPE' => 'application/json',
            ],
            [],
            [],
            [],
            '{"name":"PachyBase"}'
        );

        $this->assertSame('PachyBase', $request->json('name'));
    }

    public function testCaptureRejectsInvalidJsonPayload(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('Invalid JSON request body.');

        Request::capture(
            [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/users',
                'CONTENT_TYPE' => 'application/json',
            ],
            [],
            [],
            [],
            '{"name":'
        );
    }
}
