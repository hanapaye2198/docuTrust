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
            // Use ipapi.co (HTTPS, free tier 1000 req/day, no API key required)
            $response = Http::timeout(5)
                ->acceptJson()
                ->get(sprintf('https://ipapi.co/%s/json/', $ipAddress));

            if ($response->failed() || $response->json('error') === true) {
                return $default;
            }

            return [
                'country_code' => $response->json('country_code'),
                'country_name' => $response->json('country_name'),
                'region' => $response->json('region'),
                'city' => $response->json('city'),
                'latitude' => $response->json('latitude') !== null ? (float) $response->json('latitude') : null,
                'longitude' => $response->json('longitude') !== null ? (float) $response->json('longitude') : null,
                'is_vpn' => false,
                'is_proxy' => false,
                'isp' => $response->json('org'),
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
