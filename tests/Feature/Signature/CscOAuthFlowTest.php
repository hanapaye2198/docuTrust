<?php

namespace Tests\Feature\Signature;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CscOAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.csc.base_url', 'https://csc.test');
        config()->set('services.csc.client_id', 'client-id');
        config()->set('services.csc.client_secret', 'client-secret');
        config()->set('services.csc.redirect_uri', 'https://app.test/csc/oauth/callback');
    }

    public function test_redirect_stores_state_in_session_and_redirects_to_csc(): void
    {
        $response = $this->get(route('csc.oauth.redirect'));

        $response->assertRedirect();
        $response->assertSessionHas('csc_oauth_state');
        $this->assertStringContainsString('oauth2/authorize', (string) $response->headers->get('Location'));
    }

    public function test_callback_rejects_mismatched_state(): void
    {
        $response = $this
            ->withSession(['csc_oauth_state' => 'expected-state'])
            ->get(route('csc.oauth.callback', [
                'code' => 'abc',
                'state' => 'wrong-state',
            ]));

        $response->assertStatus(403);
    }

    public function test_callback_stores_tokens_in_session(): void
    {
        Http::fake([
            '*/oauth2/token' => Http::response([
                'access_token' => 'access-tok',
                'refresh_token' => 'refresh-tok',
                'expires_in' => 3600,
            ], 200),
        ]);

        $response = $this
            ->withSession(['csc_oauth_state' => 'valid-state'])
            ->get(route('csc.oauth.callback', [
                'code' => 'authcode',
                'state' => 'valid-state',
            ]));

        $response->assertRedirect(route('documents.index'));
        $response->assertSessionHas('csc_access_token', 'access-tok');
        $response->assertSessionHas('csc_refresh_token', 'refresh-tok');
        $response->assertSessionHas('csc_token_expires_at');
    }

    public function test_sign_csc_authorize_returns_redirect_required_for_new_signer(): void
    {
        config()->set('signature.pades_enabled', true);

        $document = Document::factory()->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create();

        $response = $this->postJson(route('sign.csc.authorize', ['token' => $signer->access_token]));

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'redirect_required',
            ]);
        $this->assertStringContainsString('csc/oauth/redirect', $response->json('redirect_url'));
    }
}
