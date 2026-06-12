<?php

namespace Tests\Feature;

use App\Http\Controllers\LoginController;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Tests\TestCase;

class LoginTokenExpirationTest extends TestCase
{
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

    public function testCheckLoginRejectsExpiredTokenWithoutRefreshingIt(): void
    {
        $now = 1770897600;
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
    }
}
