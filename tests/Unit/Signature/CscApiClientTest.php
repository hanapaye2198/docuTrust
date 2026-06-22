<?php

namespace Tests\Unit\Signature;

use App\Exceptions\CscApiException;
use App\Services\Signature\CscApiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CscApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.csc.base_url', 'https://csc.test');
        config()->set('services.csc.client_id', 'client-id');
        config()->set('services.csc.client_secret', 'client-secret');
        config()->set('services.csc.timeout', 10);
    }

    public function test_get_info_returns_parsed_response(): void
    {
        Http::fake([
            '*/csc/v2/info' => Http::response(['name' => 'TestTSP'], 200),
        ]);

        $response = (new CscApiClient)->getInfo();

        $this->assertSame('TestTSP', $response['name']);
    }

    public function test_list_credentials_sends_bearer_token(): void
    {
        Http::fake([
            '*/csc/v2/credentials/list' => Http::response([
                'credentialIDs' => ['cred-001'],
            ], 200),
        ]);

        $response = (new CscApiClient)->listCredentials('test-token');

        $this->assertContains('cred-001', $response['credentialIDs']);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_sign_hash_returns_signature(): void
    {
        Http::fake([
            '*/csc/v2/signatures/signHash' => Http::response([
                'signatures' => ['base64sighere=='],
            ], 200),
        ]);

        $response = (new CscApiClient)->signHash('token', 'sad', 'cred-001', 'abc123digest');

        $this->assertSame('base64sighere==', $response['signatures'][0]);
    }

    public function test_csc_api_exception_thrown_on_4xx(): void
    {
        Http::fake([
            '*/csc/v2/credentials/list' => Http::response([
                'error' => 'invalid_token',
                'error_description' => 'Token expired',
            ], 401),
        ]);

        try {
            (new CscApiClient)->listCredentials('expired-token');
            $this->fail('Expected CscApiException was not thrown.');
        } catch (CscApiException $exception) {
            $this->assertSame(401, $exception->getHttpStatus());
            $this->assertStringContainsString('credentials/list', $exception->getEndpoint());
        }
    }

    public function test_csc_api_exception_thrown_on_5xx(): void
    {
        Http::fake([
            '*/csc/v2/info' => Http::response([], 500),
        ]);

        try {
            (new CscApiClient)->getInfo();
            $this->fail('Expected CscApiException was not thrown.');
        } catch (CscApiException $exception) {
            $this->assertSame(500, $exception->getHttpStatus());
        }
    }

    public function test_get_access_token_sends_form_encoded_body(): void
    {
        Http::fake([
            '*/oauth2/token' => Http::response([
                'access_token' => 'tok',
                'expires_in' => 3600,
            ], 200),
        ]);

        $response = (new CscApiClient)->getAccessToken('code123', 'https://app.test/csc/callback');

        $this->assertSame('tok', $response['access_token']);
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'oauth2/token')
                && str_contains($request->body(), 'grant_type=authorization_code')
                && str_contains($request->body(), 'code=code123');
        });
    }
}
