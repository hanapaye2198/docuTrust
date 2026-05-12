<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeolocationService
{
    /**
     * Resolve geolocation data from an IP address.
     *
     * @return array{
     *   country_code: string|null,
     *   country_name: string|null,
     *   region: string|null,
     *   city: string|null,
     *   latitude: float|null,
     *   longitude: float|null,
     *   is_vpn: bool,
     *   is_proxy: bool,
     *   isp: string|null,
     * }
     */
    public function resolveFromIp(string $ipAddress): array
    {
        $default = [
            'country_code' => null,
            'country_name' => null,
            'region' => null,
            'city' => null,
            'latitude' => null,
            'longitude' => null,
            'is_vpn' => false,
            'is_proxy' => false,
            'isp' => null,
        ];

        if ($ipAddress === '' || $this->isPrivateIp($ipAddress)) {
            return $default;
        }

        try {
            // Use ip-api.com free tier (no key required, 45 req/min)
            $response = Http::timeout(5)
                ->acceptJson()
                ->get(sprintf('http://ip-api.com/json/%s', $ipAddress), [
                    'fields' => 'status,countryCode,country,regionName,city,lat,lon,isp,proxy,hosting',
                ]);

            if ($response->failed() || $response->json('status') !== 'success') {
                return $default;
            }

            return [
                'country_code' => $response->json('countryCode'),
                'country_name' => $response->json('country'),
                'region' => $response->json('regionName'),
                'city' => $response->json('city'),
                'latitude' => $response->json('lat') !== null ? (float) $response->json('lat') : null,
                'longitude' => $response->json('lon') !== null ? (float) $response->json('lon') : null,
                'is_vpn' => (bool) $response->json('hosting', false),
                'is_proxy' => (bool) $response->json('proxy', false),
                'isp' => $response->json('isp'),
            ];
        } catch (Throwable $throwable) {
            Log::channel('errors')->warning('Geolocation lookup failed', [
                'ip' => $ipAddress,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);

            return $default;
        }
    }

    /**
     * Check if the resolved location is within the Philippines.
     */
    public function isWithinPhilippines(string $ipAddress): bool
    {
        $result = $this->resolveFromIp($ipAddress);

        return $result['country_code'] === 'PH';
    }

    /**
     * Detect VPN or proxy usage.
     */
    public function isVpnOrProxy(string $ipAddress): bool
    {
        $result = $this->resolveFromIp($ipAddress);

        return $result['is_vpn'] || $result['is_proxy'];
    }

    private function isPrivateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
