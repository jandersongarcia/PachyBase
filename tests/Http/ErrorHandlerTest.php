<?php

declare(strict_types=1);

namespace Tests\Http;

use PachyBase\Config;
use PachyBase\Http\AuthenticationException;
use PachyBase\Http\ApiResponse;
use PachyBase\Http\AuthorizationException;
use PachyBase\Http\ErrorHandler;
use PachyBase\Http\ResponseCaptured;
use PachyBase\Http\ValidationException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ErrorHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        ApiResponse::disableCapture();
        Config::reset();
        $_SERVER = [];
    }

    public function testRenderException404ProducesContractPayload(): void
    {
        Config::override([
            'APP_ENV' => 'development',
            'APP_DEBUG' => 'true',
        ]);
        ApiResponse::enableCapture();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/missing';

        try {
            ErrorHandler::renderException(new RuntimeException('Not Found', 404));
            $this->fail('Expected captured response.');
        } catch (ResponseCaptured $captured) {
            $this->assertSame(404, $captured->getStatusCode());
            $this->assertSame('NOT_FOUND', $captured->getPayload()['error']['code']);
            $this->assertSame('Not Found', $captured->getPayload()['error']['message']);
            $this->assertSame('/missing', $captured->getPayload()['meta']['path']);
        }
    }

    public function testRenderExceptionHidesInternalMessageOutsideDebug(): void
    {
        Config::override([
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'true',
        ]);
        ApiResponse::enableCapture();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/boom';

        try {
            ErrorHandler::renderException(new RuntimeException('Sensitive database error', 500));
            $this->fail('Expected captured response.');
        } catch (ResponseCaptured $captured) {
            $this->assertSame(500, $captured->getStatusCode());
            $this->assertSame('INTERNAL_SERVER_ERROR', $captured->getPayload()['error']['code']);
            $this->assertSame('An unexpected internal error occurred.', $captured->getPayload()['error']['message']);
            $this->assertSame([], $captured->getPayload()['error']['details']);
        }
    }

    public function testRenderValidationExceptionUsesValidationContract(): void
    {
        Config::override([
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
        ]);
        ApiResponse::enableCapture();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/users';

        try {
            ErrorHandler::renderException(new ValidationException(
                'The request payload is invalid.',
                [[
                    'field' => 'email',
                    'code' => 'invalid_format',
                    'message' => 'The email must be a valid email address.',
                ]]
            ));
            $this->fail('Expected captured response.');
        } catch (ResponseCaptured $captured) {
            $payload = $captured->getPayload();

            $this->assertSame(422, $captured->getStatusCode());
            $this->assertSame('VALIDATION_ERROR', $payload['error']['code']);
            $this->assertSame('validation_error', $payload['error']['type']);
            $this->assertSame('email', $payload['error']['details'][0]['field']);
        }
    }

    public function testRenderAuthenticationExceptionUsesAuthenticationContract(): void
    {
        Config::override([
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
        ]);
        ApiResponse::enableCapture();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/private';

        try {
            ErrorHandler::renderException(new AuthenticationException(
                'Bearer token is missing or invalid.',
                'INVALID_TOKEN'
            ));
            $this->fail('Expected captured response.');
        } catch (ResponseCaptured $captured) {
            $payload = $captured->getPayload();

            $this->assertSame(401, $captured->getStatusCode());
            $this->assertSame('INVALID_TOKEN', $payload['error']['code']);
            $this->assertSame('authentication_error', $payload['error']['type']);
            $this->assertSame('Bearer token is missing or invalid.', $payload['error']['message']);
            $this->assertSame([], $payload['error']['details']);
        }
    }

    public function testRenderAuthorizationExceptionUsesAuthorizationContract(): void
    {
        Config::override([
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
        ]);
        ApiResponse::enableCapture();
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $_SERVER['REQUEST_URI'] = '/admin/users/7';

        try {
            ErrorHandler::renderException(new AuthorizationException(
                'You do not have permission to delete users.',
                'INSUFFICIENT_PERMISSIONS'
            ));
            $this->fail('Expected captured response.');
        } catch (ResponseCaptured $captured) {
            $payload = $captured->getPayload();

            $this->assertSame(403, $captured->getStatusCode());
            $this->assertSame('INSUFFICIENT_PERMISSIONS', $payload['error']['code']);
            $this->assertSame('authorization_error', $payload['error']['type']);
            $this->assertSame('You do not have permission to delete users.', $payload['error']['message']);
        }
    }
}
