<?php

namespace App\Services;

use App\Models\BillingProfile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EisAuthService
{
    public function __construct(
        private readonly EisCryptoService $cryptoService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function authenticate(BillingProfile $profile): array
    {
        $baseUrl = trim((string) config('services.eis.base_url', ''));
        $endpoint = trim((string) config('services.eis.auth_endpoint', '/api/authentication'));
        $timeout = (int) config('services.eis.timeout', 30);

        if ($baseUrl === '') {
            throw new RuntimeException('EIS base URL is not configured.');
        }

        $response = Http::baseUrl(rtrim($baseUrl, '/'))
            ->acceptJson()
            ->timeout($timeout)
            ->post($endpoint, [
                'data' => $this->cryptoService->encryptAuthenticationPayload([
                    'accreditationId' => $profile->eis_accreditation_id,
                    'applicationId' => $profile->eis_application_id,
                    'username' => $profile->eis_username,
                    'password' => $profile->eis_password,
                ]),
            ]);

        if (! $response->successful()) {
            $message = $response->json('error');
            if (! is_string($message) || trim($message) === '') {
                $message = $response->body();
            }

            throw new RuntimeException('EIS authentication failed: '.trim($message));
        }

        $data = $response->json('data', []);
        if (! is_array($data)) {
            throw new RuntimeException('EIS authentication response was missing the data payload.');
        }

        return $data;
    }
}
