<?php

namespace Tests\Feature\Signature;

use App\Exceptions\CscApiException;
use App\Livewire\Signature\CscCredentialSelector;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Services\Signature\CscApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class CscCredentialSelectorTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_component_mounts_with_correct_properties(): void
    {
        $document = Document::factory()->create();
        $signer = DocumentSigner::factory()->for($document)->create();

        Livewire::test(CscCredentialSelector::class, [
            'documentId' => $document->id,
            'signerId' => $signer->id,
        ])
            ->assertSet('documentId', $document->id)
            ->assertSet('signerId', $signer->id)
            ->assertSet('status', 'idle')
            ->assertSet('credentials', []);
    }

    public function test_load_credentials_sets_error_on_api_failure(): void
    {
        $client = Mockery::mock(CscApiClient::class);
        $client->shouldReceive('listCredentials')
            ->once()
            ->with('fake-token')
            ->andThrow(new CscApiException('csc/v2/credentials/list', 401, [
                'error' => 'invalid_token',
            ]));
        $this->instance(CscApiClient::class, $client);

        $document = Document::factory()->create();
        $signer = DocumentSigner::factory()->for($document)->create();

        $component = Livewire::test(CscCredentialSelector::class, [
            'documentId' => $document->id,
            'signerId' => $signer->id,
        ])
            ->set('accessToken', 'fake-token')
            ->call('loadCredentials')
            ->assertSet('status', 'error');

        $this->assertNotSame('', $component->get('errorMessage'));
    }

    public function test_authorize_credential_requires_selected_credential(): void
    {
        $document = Document::factory()->create();
        $signer = DocumentSigner::factory()->for($document)->create();

        Livewire::test(CscCredentialSelector::class, [
            'documentId' => $document->id,
            'signerId' => $signer->id,
        ])
            ->set('accessToken', 'tok')
            ->call('authorizeCredential')
            ->assertSet('status', 'error');
    }
}
