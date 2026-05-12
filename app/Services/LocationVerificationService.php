<?php

namespace App\Services;

use App\Enums\NotaryRequestStatus;
use App\Models\NotaryJournal;
use App\Models\NotaryRequest;
use RuntimeException;

class LocationVerificationService
{
    private const ALLOWED_COUNTRY = 'PH';

    /**
     * @param  array{
     *   ip_address?: string|null,
     *   country_code?: string|null,
     *   latitude?: float|null,
     *   longitude?: float|null,
     *   vpn_detected?: bool|null,
     *   source?: string,
     * }  $evidence
     */
    public function markVerified(NotaryRequest $request, array $evidence = []): NotaryRequest
    {
        if (! in_array($request->status, [
            NotaryRequestStatus::Submitted,
            NotaryRequestStatus::IdentityVerified,
        ], true)) {
            throw new RuntimeException(__('Location verification requires the request to be submitted or identity verified first.'));
        }

        $countryCode = strtoupper(trim((string) ($evidence['country_code'] ?? '')));
        $vpnDetected = (bool) ($evidence['vpn_detected'] ?? false);

        if ($countryCode !== '' && $countryCode !== self::ALLOWED_COUNTRY) {
            throw new RuntimeException(__('Location verification failed. Signer must be physically located in the Philippines.'));
        }

        if ($vpnDetected) {
            throw new RuntimeException(__('Location verification failed. VPN or proxy usage detected.'));
        }

        $request->forceFill([
            'status' => NotaryRequestStatus::LocationVerified,
            'location_verified_at' => now(),
            'location_ip_address' => $evidence['ip_address'] ?? null,
            'location_country_code' => $countryCode !== '' ? $countryCode : null,
            'location_latitude' => $evidence['latitude'] ?? null,
            'location_longitude' => $evidence['longitude'] ?? null,
            'location_vpn_detected' => $vpnDetected,
        ])->save();

        $metadata = is_array($request->metadata) ? $request->metadata : [];
        $metadata['location_verification'] = [
            'verified_at' => now()->toDateTimeString(),
            'result' => 'verified',
            'country_code' => $countryCode,
            'evidence' => $evidence,
        ];
        $request->forceFill(['metadata' => $metadata])->save();

        NotaryJournal::query()->create([
            'notary_request_id' => $request->id,
            'notary_user_id' => $request->notary_user_id,
            'entry_type' => 'location_verification',
            'summary' => __('Location verification completed. Country: :country, VPN detected: :vpn', [
                'country' => $countryCode ?: 'Unknown',
                'vpn' => $vpnDetected ? 'Yes' : 'No',
            ]),
            'legal_assertions' => [
                'location_verified' => true,
                'country_code' => $countryCode,
                'within_jurisdiction' => $countryCode === self::ALLOWED_COUNTRY || $countryCode === '',
                'vpn_detected' => $vpnDetected,
                'ip_address' => $evidence['ip_address'] ?? null,
                'coordinates' => [
                    'latitude' => $evidence['latitude'] ?? null,
                    'longitude' => $evidence['longitude'] ?? null,
                ],
            ],
            'recorded_at' => now(),
        ]);

        return $request->fresh();
    }
}
