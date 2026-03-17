<?php

declare(strict_types=1);

namespace Tests\Http;

use PachyBase\Http\ApiResponse;
use PachyBase\Http\ResponseCaptured;
use PHPUnit\Framework\TestCase;

class ApiResponseTest extends TestCase
{
    protected function tearDown(): void
    {
        ApiResponse::disableCapture();
        $_SERVER = [];
    }

    public function testSuccessResponseUsesOfficialContractShape(): void
    {
        ApiResponse::enableCapture();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/status';
        $_SERVER['HTTP_X_REQUEST_ID'] = 'req-123';

        try {
            ApiResponse::success(['status' => 'ok'], ['resource' => 'system.status']);
            $this->fail('Expected captured response.');
        } catch (ResponseCaptured $captured) {
            $payload = $captured->getPayload();

            $this->assertSame(200, $captured->getStatusCode());
            $this->assertTrue($payload['success']);
            $this->assertSame(['status' => 'ok'], $payload['data']);
            $this->assertNull($payload['error']);
            $this->assertSame('1.0', $payload['meta']['contract_version']);
            $this->assertSame('req-123', $payload['meta']['request_id']);
            $this->assertSame('GET', $payload['meta']['method']);
            $this->assertSame('/status', $payload['meta']['path']);
            $this->assertArrayHasKey('timestamp', $payload['meta']);
            $this->assertSame('system.status', $payload['meta']['resource']);
        }
    }

    public function testPaginatedResponseUsesOfficialPaginationMeta(): void
    {
        ApiResponse::enableCapture();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/users?page=2';

        try {
            ApiResponse::paginated(
                [['id' => 11], ['id' => 12]],
                25,
                2,
                10,
                ['resource' => 'users.index']
            );
            $this->fail('Expected captured response.');
        } catch (ResponseCaptured $captured) {
            $pagination = $captured->getPayload()['meta']['pagination'];

            $this->assertSame(25, $pagination['total']);
            $this->assertSame(10, $pagination['per_page']);
            $this->assertSame(2, $pagination['current_page']);
            $this->assertSame(3, $pagination['last_page']);
            $this->assertSame(11, $pagination['from']);
            $this->assertSame(20, $pagination['to']);
        }
    }

    public function testValidationErrorUsesStructuredFieldDetails(): void
    {
        ApiResponse::enableCapture();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/users';

        try {
            ApiResponse::validationError([
                [
                    'field' => 'email',
                    'code' => 'required',
                    'message' => 'The email field is required.',
                ],
            ]);
            $this->fail('Expected captured response.');
        } catch (ResponseCaptured $captured) {
            $payload = $captured->getPayload();

            $this->assertSame(422, $captured->getStatusCode());
            $this->assertFalse($payload['success']);
            $this->assertNull($payload['data']);
            $this->assertSame('VALIDATION_ERROR', $payload['error']['code']);
            $this->assertSame('validation_error', $payload['error']['type']);
            $this->assertSame('The request payload is invalid.', $payload['error']['message']);
            $this->assertSame('email', $payload['error']['details'][0]['field']);
            $this->assertSame('required', $payload['error']['details'][0]['code']);
        }
    }

    public function testAuthenticationErrorUsesOfficialContractShape(): void
    {
        ApiResponse::enableCapture();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/private';

        try {
            ApiResponse::authenticationError();
            $this->fail('Expected captured response.');
        } catch (ResponseCaptured $captured) {
            $payload = $captured->getPayload();

            $this->assertSame(401, $captured->getStatusCode());
            $this->assertFalse($payload['success']);
            $this->assertSame('AUTHENTICATION_REQUIRED', $payload['error']['code']);
            $this->assertSame('authentication_error', $payload['error']['type']);
            $this->assertSame('Authentication is required to access this resource.', $payload['error']['message']);
            $this->assertArrayHasKey('request_id', $payload['meta']);
            $this->assertArrayHasKey('timestamp', $payload['meta']);
            $this->assertSame('GET', $payload['meta']['method']);
            $this->assertSame('/private', $payload['meta']['path']);
        }
    }

    public function testAuthorizationErrorUsesOfficialContractShape(): void
    {
        ApiResponse::enableCapture();
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $_SERVER['REQUEST_URI'] = '/admin/users/7';

        try {
            ApiResponse::authorizationError();
            $this->fail('Expected captured response.');
        } catch (ResponseCaptured $captured) {
            $payload = $captured->getPayload();

            $this->assertSame(403, $captured->getStatusCode());
            $this->assertFalse($payload['success']);
            $this->assertSame('INSUFFICIENT_PERMISSIONS', $payload['error']['code']);
            $this->assertSame('authorization_error', $payload['error']['type']);
            $this->assertSame('You do not have permission to access this resource.', $payload['error']['message']);
            $this->assertArrayHasKey('request_id', $payload['meta']);
            $this->assertArrayHasKey('timestamp', $payload['meta']);
            $this->assertSame('DELETE', $payload['meta']['method']);
            $this->assertSame('/admin/users/7', $payload['meta']['path']);
        }
    }
}
