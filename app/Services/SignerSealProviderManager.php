<?php

namespace App\Services;

use App\Data\SignerSealResult;
use App\Models\DocumentSigner;
use RuntimeException;

class SignerSealProviderManager
{
    public function __construct(
        private readonly AppManagedSignerSealProvider $appManagedSignerSealProvider,
        private readonly RemoteManagedSignerSealProvider $remoteManagedSignerSealProvider,
    ) {}

    public function seal(DocumentSigner $signer, string $hash): SignerSealResult
    {
        return match ((string) config('docutrust.pki.signing_backend', 'app_managed')) {
            'app_managed' => $this->appManagedSignerSealProvider->seal($signer, $hash),
            'remote_managed' => $this->remoteManagedSignerSealProvider->seal($signer, $hash),
            default => throw new RuntimeException('Unsupported signing backend configured.'),
        };
    }
}
