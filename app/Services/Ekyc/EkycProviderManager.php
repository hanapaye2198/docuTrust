<?php

namespace App\Services\Ekyc;

use App\Contracts\Ekyc\EkycVerificationProvider;
use App\Services\Ekyc\Sumsub\SumsubEkycProvider;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class EkycProviderManager
{
    public function __construct(private readonly Container $container) {}

    /**
     * Resolve the eKYC verification provider by name.
     */
    public function driver(?string $name = null): EkycVerificationProvider
    {
        $name ??= $this->defaultDriver();

        return match ($name) {
            'sumsub' => $this->container->make(SumsubEkycProvider::class),
            'tesseract' => $this->container->make(TesseractEkycProvider::class),
            default => throw new InvalidArgumentException("Unknown eKYC driver: {$name}"),
        };
    }

    /**
     * Get the default driver name from configuration.
     */
    public function defaultDriver(): string
    {
        return (string) config('ekyc.default_driver', 'tesseract');
    }

    /**
     * Whether the current default driver uses async verification.
     */
    public function isAsync(): bool
    {
        return $this->driver()->isAsync();
    }
}
