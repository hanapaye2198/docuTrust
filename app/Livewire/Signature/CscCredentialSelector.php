<?php

namespace App\Livewire\Signature;

use App\Exceptions\CscApiException;
use App\Models\DocumentHash;
use App\Models\DocumentSigner;
use App\Services\Signature\CscApiClient;
use App\Services\Signature\SadLifecycleService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class CscCredentialSelector extends Component
{
    public int $documentId;

    public int $signerId;

    public string $accessToken = '';

    /**
     * @var array<int, string>
     */
    public array $credentials = [];

    public string $selectedCredentialId = '';

    public string $status = 'idle';

    public string $errorMessage = '';

    public function mount(int $documentId, int $signerId): void
    {
        $this->documentId = $documentId;
        $this->signerId = $signerId;
        $this->accessToken = (string) session('csc_access_token', '');

        if ($this->accessToken !== '') {
            $this->loadCredentials();
        }
    }

    public function loadCredentials(): void
    {
        $this->status = 'loading';
        $this->errorMessage = '';

        try {
            $result = app(CscApiClient::class)->listCredentials($this->accessToken);
            $credentialIds = $result['credentialIDs'] ?? [];
            $this->credentials = is_array($credentialIds)
                ? array_values(array_filter($credentialIds, is_string(...)))
                : [];
            $this->status = 'idle';
        } catch (CscApiException $exception) {
            $this->status = 'error';
            $this->errorMessage = $exception->getMessage();
        }
    }

    public function selectCredential(string $credentialId): void
    {
        $this->selectedCredentialId = $credentialId;
        $this->errorMessage = '';

        try {
            app(CscApiClient::class)->getCredentialInfo($this->accessToken, $credentialId);

            DocumentSigner::query()
                ->whereKey($this->signerId)
                ->update([
                    'remote_credential_id' => $credentialId,
                ]);

            $this->dispatch('credentialSelected', credentialId: $credentialId);
        } catch (CscApiException $exception) {
            $this->status = 'error';
            $this->errorMessage = $exception->getMessage();
        }
    }

    public function authorizeCredential(): void
    {
        $this->errorMessage = '';

        if ($this->selectedCredentialId === '') {
            $this->status = 'error';
            $this->errorMessage = __('Select a CSC credential before authorizing signing.');

            return;
        }

        try {
            $docHash = DocumentHash::query()
                ->where('document_id', $this->documentId)
                ->latest('created_at')
                ->first()?->hash ?? '';

            $response = app(CscApiClient::class)->authorize(
                accessToken: $this->accessToken,
                credentialId: $this->selectedCredentialId,
                numSignatures: 1,
                hash: $docHash,
                description: 'DocuTrust document signing authorization',
            );

            $sad = $response['SAD'] ?? '';
            if (! is_string($sad) || $sad === '') {
                $this->status = 'error';
                $this->errorMessage = __('Authorization failed - no SAD returned');

                return;
            }

            app(SadLifecycleService::class)->storeSad(
                documentId: $this->documentId,
                signerId: $this->signerId,
                credentialId: $this->selectedCredentialId,
                sad: $sad,
                ttlSeconds: 300,
            );

            $this->status = 'authorized';
            $this->dispatch('sadAuthorized');
        } catch (CscApiException $exception) {
            $this->status = 'error';
            $this->errorMessage = $exception->getMessage();
        }
    }

    public function connectCsc(): void
    {
        $this->redirectRoute('csc.oauth.redirect', [
            'document_id' => $this->documentId,
            'signer_id' => $this->signerId,
        ]);
    }

    public function render(): View
    {
        return view('livewire.signature.csc-credential-selector');
    }
}
