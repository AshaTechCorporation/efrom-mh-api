<?php

namespace Tests\Feature;

use App\Http\Controllers\LoginController;
use App\Http\Middleware\CheckJWT;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Tests\TestCase;

class LoginTokenExpirationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.login_token_ttl_seconds' => 3600,
            'auth.login_token_valid_after' => null,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        JWT::$timestamp = null;

        parent::tearDown();
    }

    public function testGeneratedLoginTokenExpiresAfterConfiguredOneHour(): void
    {
        $now = 1770897600;
        config(['auth.login_token_ttl_seconds' => 3600]);
        Carbon::setTestNow(Carbon::createFromTimestamp($now));
        JWT::$timestamp = $now;

        $token = (new LoginController())->genToken(123, (object) ['id' => 123]);
        $payload = JWT::decode($token, 'key', ['HS256']);

        $this->assertSame($now, $payload->iat);
        $this->assertSame($now + 3600, $payload->exp);
    }

    public function testCheckLoginReturnsTokenLifetimeMetadataForActiveToken(): void
    {
        $now = 1770897600;
        config(['auth.login_token_ttl_seconds' => 3600]);
        Carbon::setTestNow(Carbon::createFromTimestamp($now));
        JWT::$timestamp = $now;

        $token = (new LoginController())->genToken(123, (object) ['id' => 123]);

        $request = Request::create('/api/check_login', 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $response = (new LoginController())->checkLogin($request);
        $payload = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload['status']);
        $this->assertSame('Active', $payload['message']);
        $this->assertSame($token, $payload['token']);
        $this->assertSame(123, $payload['data']['login_id']);
        $this->assertSame($now, $payload['data']['issued_at']);
        $this->assertSame($now + 3600, $payload['data']['expires_at']);
        $this->assertSame(3600, $payload['data']['configured_ttl_seconds']);
        $this->assertSame(3600, $payload['data']['ttl_seconds']);
        $this->assertSame(60, $payload['data']['ttl_minutes']);
        $this->assertSame(3600, $payload['data']['remaining_seconds']);
        $this->assertSame(60, $payload['data']['remaining_minutes']);
        $this->assertFalse($payload['data']['is_expired']);
    }

    public function testCheckLoginRejectsExpiredTokenWithoutRefreshingIt(): void
    {
        $now = 1770897600;
        Carbon::setTestNow(Carbon::createFromTimestamp($now));
        JWT::$timestamp = $now;

        $token = JWT::encode([
            'iss' => 'key',
            'aud' => 123,
            'lun' => (object) ['id' => 123],
            'iat' => $now - 3601,
            'exp' => $now - 1,
            'nbf' => $now - 3601,
        ], 'key');

        $request = Request::create('/api/check_login', 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $response = (new LoginController())->checkLogin($request);
        $payload = json_decode($response->getContent(), true);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertFalse($payload['status']);
        $this->assertSame('Token is expire', $payload['message']);
        $this->assertArrayNotHasKey('token', $payload);
        $this->assertTrue($payload['data']['is_expired']);
        $this->assertSame(0, $payload['data']['remaining_seconds']);
    }

    public function testCheckLoginRejectsTokenWithoutExpiration(): void
    {
        $now = 1770897600;
        Carbon::setTestNow(Carbon::createFromTimestamp($now));
        JWT::$timestamp = $now;

        $token = JWT::encode([
            'iss' => 'key',
            'aud' => 123,
            'lun' => (object) ['id' => 123],
            'iat' => $now,
            'nbf' => $now,
        ], 'key');

        $request = Request::create('/api/check_login', 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $response = (new LoginController())->checkLogin($request);
        $payload = json_decode($response->getContent(), true);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertFalse($payload['status']);
        $this->assertSame('Token expiration not found', $payload['message']);
        $this->assertArrayNotHasKey('token', $payload);
    }

    public function testCheckLoginRejectsLongLivedTokenAfterConfiguredTtl(): void
    {
        $now = 1770897600;
        Carbon::setTestNow(Carbon::createFromTimestamp($now));
        JWT::$timestamp = $now;

        $token = JWT::encode([
            'iss' => 'key',
            'aud' => 123,
            'lun' => (object) ['id' => 123],
            'iat' => $now - 3601,
            'exp' => $now + 31536000,
            'nbf' => $now - 3601,
        ], 'key');

        $request = Request::create('/api/check_login', 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $response = (new LoginController())->checkLogin($request);
        $payload = json_decode($response->getContent(), true);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertFalse($payload['status']);
        $this->assertSame('Token is expire', $payload['message']);
        $this->assertTrue($payload['data']['is_expired']);
    }

    public function testCheckLoginRejectsTokenIssuedBeforeConfiguredValidAfter(): void
    {
        $now = 1770897600;
        config(['auth.login_token_valid_after' => $now - 600]);
        Carbon::setTestNow(Carbon::createFromTimestamp($now));
        JWT::$timestamp = $now;

        $token = JWT::encode([
            'iss' => 'key',
            'aud' => 123,
            'lun' => (object) ['id' => 123],
            'iat' => $now - 1200,
            'exp' => $now + 31536000,
            'nbf' => $now - 1200,
        ], 'key');

        $request = Request::create('/api/check_login', 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $response = (new LoginController())->checkLogin($request);
        $payload = json_decode($response->getContent(), true);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertFalse($payload['status']);
        $this->assertSame('Token issued before valid window', $payload['message']);
        $this->assertArrayNotHasKey('token', $payload);
    }

    public function testCheckJwtRejectsLongLivedTokenAfterConfiguredTtl(): void
    {
        $now = 1770897600;
        Carbon::setTestNow(Carbon::createFromTimestamp($now));
        JWT::$timestamp = $now;

        $token = JWT::encode([
            'iss' => 'key',
            'aud' => 123,
            'lun' => (object) ['id' => 123],
            'iat' => $now - 3601,
            'exp' => $now + 31536000,
            'nbf' => $now - 3601,
        ], 'key');

        $request = Request::create('/api/manuals', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);
        $nextCalled = false;

        $response = (new CheckJWT())->handle($request, function () use (&$nextCalled) {
            $nextCalled = true;
            return response()->json(['ok' => true]);
        });
        $payload = json_decode($response->getContent(), true);

        $this->assertFalse($nextCalled);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertFalse($payload['status']);
        $this->assertSame('Token is expire', $payload['message']);
    }

    public function testCheckJwtRejectsTokenIssuedBeforeConfiguredValidAfter(): void
    {
        $now = 1770897600;
        config(['auth.login_token_valid_after' => $now - 600]);
        Carbon::setTestNow(Carbon::createFromTimestamp($now));
        JWT::$timestamp = $now;

        $token = JWT::encode([
            'iss' => 'key',
            'aud' => 123,
            'lun' => (object) ['id' => 123],
            'iat' => $now - 1200,
            'exp' => $now + 31536000,
            'nbf' => $now - 1200,
        ], 'key');

        $request = Request::create('/api/manuals', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);
        $nextCalled = false;

        $response = (new CheckJWT())->handle($request, function () use (&$nextCalled) {
            $nextCalled = true;
            return response()->json(['ok' => true]);
        });
        $payload = json_decode($response->getContent(), true);

        $this->assertFalse($nextCalled);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertFalse($payload['status']);
        $this->assertSame('Token issued before valid window', $payload['message']);
    }
}
