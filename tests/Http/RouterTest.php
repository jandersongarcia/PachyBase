<?php

declare(strict_types=1);

namespace Tests\Http;

use PachyBase\Http\Request;
use PachyBase\Http\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    private function makeRequest(string $method, string $path): Request
    {
        return new Request($method, $path);
    }

    public function testMatchesGetRoute(): void
    {
        $router = new Router();
        $called = false;

        $router->get('/', function (Request $req) use (&$called): void {
            $called = true;
        });

        $router->dispatch($this->makeRequest('GET', '/'));

        $this->assertTrue($called);
    }

    public function testReturns404ForUnknownPath(): void
    {
        $router = new Router();
        $router->get('/', fn(Request $r) => null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(404);

        $router->dispatch($this->makeRequest('GET', '/nonexistent'));
    }

    public function testReturns405WhenPathMatchesButMethodDiffers(): void
    {
        $router = new Router();
        $router->get('/resource', fn(Request $r) => null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(405);

        $router->dispatch($this->makeRequest('POST', '/resource'));
    }

    public function testMatchesRouteWithDynamicParam(): void
    {
        $router = new Router();
        $captured = null;

        $router->get('/users/{id}', function (Request $req, string $id) use (&$captured): void {
            $captured = $id;
        });

        $router->dispatch($this->makeRequest('GET', '/users/42'));

        $this->assertSame('42', $captured);
    }

    public function testMiddlewareIsCalledBeforeHandler(): void
    {
        $router = new Router();
        RouterMiddlewareRecorder::$events = [];

        $router->get('/guarded', function (Request $req): void {
            RouterMiddlewareRecorder::$events[] = 'handler';
            $req->setAttribute('handled', true);
        })->middleware([FirstRouterMiddleware::class, SecondRouterMiddleware::class]);

        $router->dispatch($this->makeRequest('GET', '/guarded'));

        $this->assertSame([
            'first:before',
            'second:before',
            'handler',
            'second:after',
            'first:after',
        ], RouterMiddlewareRecorder::$events);
    }

    public function testThrowsRuntimeExceptionForInvalidArrayHandler(): void
    {
        $router = new Router();
        $router->get('/broken', ['MissingController', 'missingMethod']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid route handler');

        $router->dispatch($this->makeRequest('GET', '/broken'));
    }
}

final class RouterMiddlewareRecorder
{
    /**
     * @var list<string>
     */
    public static array $events = [];
}

final class FirstRouterMiddleware
{
    public function handle(Request $request, callable $next): void
    {
        RouterMiddlewareRecorder::$events[] = 'first:before';
        $next();
        RouterMiddlewareRecorder::$events[] = 'first:after';
    }
}

final class SecondRouterMiddleware
{
    public function handle(Request $request, callable $next): void
    {
        RouterMiddlewareRecorder::$events[] = 'second:before';
        $next();
        RouterMiddlewareRecorder::$events[] = 'second:after';
    }
}
