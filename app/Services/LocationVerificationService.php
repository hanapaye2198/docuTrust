<?php

namespace App\Services;

use App\Enums\NotaryGeoVerificationStatus;
use App\Enums\NotaryRequestStatus;
use App\Models\NotaryGeoLog;
use App\Models\NotaryJournal;
use App\Models\NotaryRequest;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class LocationVerificationService
{
    private const ALLOWED_COUNTRY = 'PH';

    public function __construct(
        private readonly GeolocationService $geolocationService,
    ) {}

    /**
     * @param  array{
     *   ip_address?: string|null,
     *   country_code?: string|null,
     *   latitude?: float|null,
     *   longitude?: float|null,
     *   vpn_detected?: bool|null,
     *   source?: string,
     *   city?: string|null,
     * }  $evidence
     */
    public function markVerified(NotaryRequest $request, array $evidence = [], ?int $signerId = null): NotaryRequest
    {
        if (! in_array($request->status, [
            NotaryRequestStatus::Submitted,
            NotaryRequestStatus::IdentityReviewRequired,
            NotaryRequestStatus::IdentityVerified,
            NotaryRequestStatus::LocationReviewRequired,
            NotaryRequestStatus::LocationVerified,
            NotaryRequestStatus::SessionScheduled,
            NotaryRequestStatus::SessionInProgress,
            NotaryRequestStatus::SessionCompleted,
            NotaryRequestStatus::AttorneySigning,
        ], true)) {
            throw new RuntimeException(__('Location verification requires the notarization to be submitted or identity verified first.'));
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
            'location_verified_at' => now(),
            'location_ip_address' => $evidence['ip_address'] ?? null,
            'location_country_code' => $countryCode !== '' ? $countryCode : null,
            'location_latitude' => $evidence['latitude'] ?? null,
            'location_longitude' => $evidence['longitude'] ?? null,
            'location_vpn_detected' => $vpnDetected,
        ])->save();

        if (in_array($request->status, [
            NotaryRequestStatus::Submitted,
            NotaryRequestStatus::IdentityReviewRequired,
            NotaryRequestStatus::IdentityVerified,
            NotaryRequestStatus::LocationReviewRequired,
            NotaryRequestStatus::LocationVerified,
        ], true)) {
            $request->forceFill([
                'status' => NotaryRequestStatus::LocationVerified,
            ])->save();
        }

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

        $this->writeGeoLog(
            request: $request,
            signerId: $signerId,
            country: $countryCode !== '' ? $countryCode : null,
            city: isset($evidence['city']) ? trim((string) $evidence['city']) : null,
            ipAddress: isset($evidence['ip_address']) ? (string) $evidence['ip_address'] : null,
            latitude: isset($evidence['latitude']) ? (float) $evidence['latitude'] : null,
            longitude: isset($evidence['longitude']) ? (float) $evidence['longitude'] : null,
            vpnDetected: $vpnDetected,
            status: NotaryGeoVerificationStatus::Passed,
        );

        Log::channel('audit')->info('Notary location verified', [
            'notary_request_id' => $request->id,
            'signer_id' => $signerId,
            'country_code' => $countryCode,
        ]);

        return $request->fresh();
    }

    /**
     * Evaluate browser-reported coordinates plus server IP intelligence, persist a geo log, and advance the request when checks pass.
     *
     * @param  array{
     *   latitude?: float|null,
     *   longitude?: float|null,
     *   accuracy_meters?: float|null,
     * }  $browser
     * @return array{success: bool, log: NotaryGeoLog, message?: string}
     */
    public function evaluateBrowserLocation(NotaryRequest $request, ?int $signerId, array $browser = []): array
    {
        $ipAddress = (string) request()->ip();
        $resolved = $this->geolocationService->resolveFromIp($ipAddress);
        $vpnDetected = $resolved['is_vpn'] || $resolved['is_proxy'];
        $countryFromIp = $resolved['country_code'] !== null ? strtoupper((string) $resolved['country_code']) : '';

        $latitude = $browser['latitude'] ?? null;
        $longitude = $browser['longitude'] ?? null;

        $failedReason = null;
        if ($countryFromIp !== '' && $countryFromIp !== self::ALLOWED_COUNTRY) {
            $failedReason = __('IP geolocation indicates you are outside the Philippines.');
        } elseif ($vpnDetected) {
            $failedReason = __('VPN or proxy usage appears to be active for this connection.');
        }

        if ($failedReason !== null) {
            $log = $this->writeGeoLog(
                request: $request,
                signerId: $signerId,
                country: $countryFromIp !== '' ? $countryFromIp : null,
                city: $resolved['city'],
                ipAddress: $ipAddress,
                latitude: is_numeric($latitude) ? (float) $latitude : null,
                longitude: is_numeric($longitude) ? (float) $longitude : null,
                vpnDetected: $vpnDetected,
                status: NotaryGeoVerificationStatus::Failed,
            );

            $metadata = is_array($request->metadata) ? $request->metadata : [];
            $metadata['location_verification'] = [
                'verified_at' => now()->toDateTimeString(),
                'result' => 'review_required',
                'country_code' => $countryFromIp !== '' ? $countryFromIp : null,
                'evidence' => [
                    'ip_address' => $ipAddress,
                    'country_code' => $countryFromIp !== '' ? $countryFromIp : null,
                    'latitude' => is_numeric($latitude) ? (float) $latitude : null,
                    'longitude' => is_numeric($longitude) ? (float) $longitude : null,
                    'vpn_detected' => $vpnDetected,
                    'source' => 'browser_and_ip_geolocation',
                    'city' => $resolved['city'],
                ],
                'failure_reason' => (string) $failedReason,
            ];

            $request->markLocationReviewRequired((string) $failedReason);

            $request->forceFill([
                'metadata' => $metadata,
                'location_ip_address' => $ipAddress,
                'location_country_code' => $countryFromIp !== '' ? $countryFromIp : null,
                'location_latitude' => is_numeric($latitude) ? (float) $latitude : null,
                'location_longitude' => is_numeric($longitude) ? (float) $longitude : null,
                'location_vpn_detected' => $vpnDetected,
            ])->save();

            NotaryJournal::query()->create([
                'notary_request_id' => $request->id,
                'notary_user_id' => $request->notary_user_id,
                'entry_type' => 'location_verification_review_required',
                'summary' => (string) $failedReason,
                'legal_assertions' => [
                    'location_verified' => false,
                    'review_required' => true,
                    'country_code' => $countryFromIp !== '' ? $countryFromIp : null,
                    'vpn_detected' => $vpnDetected,
                    'ip_address' => $ipAddress,
                ],
                'recorded_at' => now(),
            ]);

            Log::channel('audit')->warning('Notary location verification failed', [
                'notary_request_id' => $request->id,
                'signer_id' => $signerId,
                'country' => $countryFromIp,
                'vpn' => $vpnDetected,
            ]);

            return [
                'success' => false,
                'log' => $log,
                'message' => $failedReason,
            ];
        }

        $evidence = [
            'ip_address' => $ipAddress,
            'country_code' => self::ALLOWED_COUNTRY,
            'latitude' => is_numeric($latitude) ? (float) $latitude : $resolved['latitude'],
            'longitude' => is_numeric($longitude) ? (float) $longitude : $resolved['longitude'],
            'vpn_detected' => false,
            'source' => 'browser_and_ip_geolocation',
            'city' => $resolved['city'],
        ];

        $fresh = $this->markVerified($request->fresh(), $evidence, $signerId);

        return [
            'success' => true,
            'log' => $fresh->geoLogs()->latest('id')->firstOrFail(),
            'message' => null,
        ];
    }

    private function writeGeoLog(
        NotaryRequest $request,
        ?int $signerId,
        ?string $country,
        ?string $city,
        ?string $ipAddress,
        ?float $latitude,
        ?float $longitude,
        bool $vpnDetected,
        NotaryGeoVerificationStatus $status,
    ): NotaryGeoLog {
        return NotaryGeoLog::query()->create([
            'notary_request_id' => $request->id,
            'notary_signer_id' => $signerId,
            'ip_address' => $ipAddress,
            'country' => $country,
            'city' => $city,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'vpn_detected' => $vpnDetected,
            'verification_status' => $status,
            'verified_at' => now(),
        ]);
    }
}
