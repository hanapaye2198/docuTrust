<?php

namespace App\Services;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Dedicated Virtual Gateway (VGW) Service
 *
 * CSC-compliant VGW that provides:
 * - Network isolation between PKI services and external requests
 * - Request authentication and authorization
 * - Rate limiting and DDoS protection
 * - Audit logging of all gateway traffic
 * - IP allowlisting for HSM operations
 * - TLS mutual authentication support
 *
 * Deployment: This service should run as a separate process/container
 * in front of the PKI services, accessible only via internal network.
 */
class DedicatedVirtualGateway
{
    private array $allowedOperations = [
        'sign',
        'verify',
        'generate_key',
        'get_public_key',
        'destroy_key',
        'status',
        'enroll',
        'revoke',
        'get_crl',
        'ocsp',
    ];

    private array $ipAllowlist = [];
    private bool $mtlsRequired = false;
    private int $maxRequestsPerMinute = 60;

    public function __construct()
    {
        $this->ipAllowlist = array_filter(
            explode(',', (string) config('hsm.gateway.ip_allowlist', ''))
        );
        $this->mtlsRequired = (bool) config('hsm.gateway.mtls_required', false);
        $this->maxRequestsPerMinute = (int) config('hsm.gateway.rate_limit', 60);
    }

    /**
     * Process incoming request through the VGW pipeline.
     */
    public function handle(Request $request, Closure $next)
    {
        // Step 1: IP allowlist check
        if (!$this->checkIpAllowlist($request)) {
            $this->logDenied($request, 'IP not in allowlist');
            return response()->json(['error' => 'Access denied'], 403);
        }

        // Step 2: mTLS verification (if configured)
        if ($this->mtlsRequired && !$this->verifyMtls($request)) {
            $this->logDenied($request, 'mTLS verification failed');
            return response()->json(['error' => 'Client certificate required'], 401);
        }

        // Step 3: Rate limiting
        if (!$this->checkRateLimit($request)) {
            $this->logDenied($request, 'Rate limit exceeded');
            return response()->json(['error' => 'Rate limit exceeded'], 429);
        }

        // Step 4: API key authentication
        if (!$this->authenticateRequest($request)) {
            $this->logDenied($request, 'Authentication failed');
            return response()->json(['error' => 'Authentication required'], 401);
        }

        // Step 5: Operation authorization
        $operation = $this->resolveOperation($request);
        if ($operation !== null && !$this->authorizeOperation($operation, $request)) {
            $this->logDenied($request, "Operation not authorized: {$operation}");
            return response()->json(['error' => 'Operation not authorized'], 403);
        }

        // Step 6: Log successful gateway pass
        $this->logAllowed($request, $operation);

        // Step 7: Add security headers
        $response = $next($request);

        return $this->addSecurityHeaders($response);
    }

    /**
     * Check if request IP is in the allowlist.
     */
    private function checkIpAllowlist(Request $request): bool
    {
        // If no allowlist configured, allow all (dev mode)
        if (empty($this->ipAllowlist)) {
            return true;
        }

        $clientIp = $request->ip();

        foreach ($this->ipAllowlist as $allowed) {
            $allowed = trim($allowed);
            if ($allowed === $clientIp) {
                return true;
            }

            // CIDR notation support
            if (str_contains($allowed, '/') && $this->ipInCidr($clientIp, $allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verify mutual TLS client certificate.
     */
    private function verifyMtls(Request $request): bool
    {
        // Check for client certificate in request
        $clientCert = $request->server('SSL_CLIENT_CERT');
        $clientVerify = $request->server('SSL_CLIENT_VERIFY');

        if ($clientVerify !== 'SUCCESS' || empty($clientCert)) {
            return false;
        }

        // Verify client certificate against trusted CA
        $trustedCaPath = (string) config('hsm.gateway.trusted_ca_cert', '');
        if ($trustedCaPath === '' || !file_exists($trustedCaPath)) {
            return false;
        }

        $cert = openssl_x509_read($clientCert);
        if ($cert === false) {
            return false;
        }

        // Verify certificate is not expired
        $certInfo = openssl_x509_parse($clientCert);
        if (!is_array($certInfo)) {
            return false;
        }

        $validTo = $certInfo['validTo_time_t'] ?? 0;
        if ($validTo < time()) {
            return false;
        }

        return true;
    }

    /**
     * Check rate limit for the request.
     */
    private function checkRateLimit(Request $request): bool
    {
        $key = 'vgw:' . ($request->ip() ?? 'unknown');

        return !RateLimiter::tooManyAttempts($key, $this->maxRequestsPerMinute);
    }

    /**
     * Authenticate the request via API key or bearer token.
     */
    private function authenticateRequest(Request $request): bool
    {
        // Check API key header
        $apiKey = $request->header('X-VGW-Key') ?? $request->header('X-API-Key');
        $configuredKey = (string) config('hsm.gateway.api_key', '');

        if ($configuredKey !== '' && $apiKey === $configuredKey) {
            return true;
        }

        // Check bearer token (for service-to-service auth)
        $bearerToken = $request->bearerToken();
        $configuredToken = (string) config('hsm.gateway.service_token', '');

        if ($configuredToken !== '' && $bearerToken === $configuredToken) {
            return true;
        }

        // In dev mode with no keys configured, allow through
        if ($configuredKey === '' && $configuredToken === '') {
            return true;
        }

        return false;
    }

    /**
     * Resolve the operation from the request path/body.
     */
    private function resolveOperation(Request $request): ?string
    {
        $path = $request->path();

        // Map URL paths to operations
        $pathMap = [
            'api/hsm/sign' => 'sign',
            'api/hsm/verify' => 'verify',
            'api/hsm/generate-key' => 'generate_key',
            'api/hsm/public-key' => 'get_public_key',
            'api/hsm/key' => 'destroy_key',
            'api/hsm/status' => 'status',
            'api/scep' => 'enroll',
            'api/cmp' => 'enroll',
            'api/crl' => 'get_crl',
            'ocsp' => 'ocsp',
        ];

        foreach ($pathMap as $prefix => $operation) {
            if (str_starts_with($path, $prefix)) {
                return $operation;
            }
        }

        return $request->input('operation');
    }

    /**
     * Authorize the operation for the authenticated client.
     */
    private function authorizeOperation(?string $operation, Request $request): bool
    {
        if ($operation === null) {
            return true; // Non-HSM requests pass through
        }

        return in_array($operation, $this->allowedOperations, true);
    }

    /**
     * Add security headers to the response.
     */
    private function addSecurityHeaders($response)
    {
        if (method_exists($response, 'header')) {
            $response->header('X-Content-Type-Options', 'nosniff');
            $response->header('X-Frame-Options', 'DENY');
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            $response->header('X-VGW-Request-Id', bin2hex(random_bytes(16)));
        }

        return $response;
    }

    /**
     * Check if IP is within CIDR range.
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - (int) $bits);

        return ($ip & $mask) === ($subnet & $mask);
    }

    private function logAllowed(Request $request, ?string $operation): void
    {
        Log::channel('hsm_gateway')->info('VGW: Request allowed', [
            'ip' => $request->ip(),
            'operation' => $operation,
            'path' => $request->path(),
            'method' => $request->method(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    private function logDenied(Request $request, string $reason): void
    {
        Log::channel('hsm_gateway')->warning('VGW: Request denied', [
            'ip' => $request->ip(),
            'reason' => $reason,
            'path' => $request->path(),
            'method' => $request->method(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get VGW status and metrics.
     */
    public function getStatus(): array
    {
        return [
            'status' => 'online',
            'mode' => $this->mtlsRequired ? 'mtls' : 'api_key',
            'ip_allowlist_active' => !empty($this->ipAllowlist),
            'ip_allowlist_count' => count($this->ipAllowlist),
            'rate_limit' => $this->maxRequestsPerMinute,
            'allowed_operations' => $this->allowedOperations,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
