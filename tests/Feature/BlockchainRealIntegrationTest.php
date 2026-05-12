<?php

namespace Tests\Feature;

use App\Services\BlockchainProofService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Real HTTP integration tests against a running blockchain-service (see blockchain-service/).
 *
 * Default PHPUnit runs skip these tests. To execute:
 *
 *   PowerShell:
 *     $env:BLOCKCHAIN_INTEGRATION_TEST="true"
 *     $env:BLOCKCHAIN_SERVICE_URL="http://127.0.0.1:3001"
 *     php artisan test --compact tests/Feature/BlockchainRealIntegrationTest.php
 *
 * Optional on-chain round trip (consumes gas on Polygon):
 *
 *     $env:BLOCKCHAIN_INTEGRATION_ANCHOR="true"
 */
class BlockchainRealIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->integrationTestsEnabled()) {
            $this->markTestSkipped(
                'Set BLOCKCHAIN_INTEGRATION_TEST=true and start blockchain-service to run real HTTP integration tests.'
            );
        }

        // Ensure the blockchain service URL is set in config for real HTTP calls.
        config()->set('services.blockchain.base_url', $this->blockchainBaseUrl());
        config()->set('services.blockchain.timeout', 30);
    }

    public function test_live_blockchain_service_health_endpoint(): void
    {
        $baseUrl = $this->blockchainBaseUrl();
        $response = Http::timeout(15)
            ->acceptJson()
            ->get(rtrim($baseUrl, '/').'/health');

        $this->assertTrue($response->successful(), 'Health endpoint did not return a 2xx response.');

        $json = $response->json();
        $this->assertIsArray($json);
        $this->assertArrayHasKey('status', $json);
        $this->assertSame('ok', $json['status']);
        $this->assertArrayHasKey('blockchain', $json);
    }

    public function test_live_verify_existing_on_chain_hash(): void
    {
        $health = Http::timeout(15)
            ->acceptJson()
            ->get(rtrim($this->blockchainBaseUrl(), '/').'/health')
            ->json();

        $blockchainEnabled = (bool) data_get($health, 'blockchain.enabled', false);
        if (! $blockchainEnabled) {
            $this->markTestSkipped(
                'blockchain-service reports blockchain anchoring disabled.'
            );
        }

        // Use a hash that was previously anchored on Polygon Amoy.
        $knownHash = 'bce6ef5476351a50823efad9df8ce81d5378bbfd55723221508621e10d7f8ab2';

        $proof = app(BlockchainProofService::class);
        $result = $proof->verifyDocumentHash($knownHash);

        $this->assertTrue($result['exists'], 'Known hash should exist on-chain.');
        $this->assertIsInt($result['proof_timestamp']);
        $this->assertGreaterThan(0, $result['proof_timestamp']);
        $this->assertIsString($result['submitted_by']);
        $this->assertNotSame('', $result['submitted_by']);
    }

    public function test_live_verify_non_existent_hash_returns_false(): void
    {
        $health = Http::timeout(15)
            ->acceptJson()
            ->get(rtrim($this->blockchainBaseUrl(), '/').'/health')
            ->json();

        $blockchainEnabled = (bool) data_get($health, 'blockchain.enabled', false);
        if (! $blockchainEnabled) {
            $this->markTestSkipped(
                'blockchain-service reports blockchain anchoring disabled.'
            );
        }

        // Use a random hash that has never been anchored.
        $randomHash = hash('sha256', 'never-anchored-'.uniqid('', true));

        $proof = app(BlockchainProofService::class);
        $result = $proof->verifyDocumentHash($randomHash);

        $this->assertFalse($result['exists'], 'Random hash should not exist on-chain.');
    }

    public function test_live_anchor_and_verify_round_trip(): void
    {
        if (! $this->anchorRoundTripEnabled()) {
            $this->markTestSkipped(
                'Set BLOCKCHAIN_INTEGRATION_ANCHOR=true to run a live anchor + verify round trip (costs gas).'
            );
        }

        $health = Http::timeout(15)
            ->acceptJson()
            ->get(rtrim($this->blockchainBaseUrl(), '/').'/health')
            ->json();

        $blockchainEnabled = (bool) data_get($health, 'blockchain.enabled', false);
        if (! $blockchainEnabled) {
            $this->markTestSkipped(
                'blockchain-service reports blockchain anchoring disabled. Set POLYGON_RPC_URL, POLYGON_PRIVATE_KEY, and DOCUMENT_NOTARY_ADDRESS on the service.'
            );
        }

        $hash = hash('sha256', 'docutrust-integration-'.uniqid('', true));

        $proof = app(BlockchainProofService::class);
        $transactionId = $proof->anchorDocumentHash($hash);

        $this->assertIsString($transactionId);
        $this->assertNotSame('', $transactionId);

        // Allow time for the transaction to be confirmed on-chain.
        sleep(5);

        $result = $proof->verifyDocumentHash($hash, $transactionId);

        $this->assertTrue($result['exists'], "Hash should exist on-chain after anchoring. TX: {$transactionId}");
        $this->assertNotFalse($result['transaction_matches']);
    }

    private function integrationTestsEnabled(): bool
    {
        return filter_var(env('BLOCKCHAIN_INTEGRATION_TEST', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function anchorRoundTripEnabled(): bool
    {
        return filter_var(env('BLOCKCHAIN_INTEGRATION_ANCHOR', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function blockchainBaseUrl(): string
    {
        $baseUrl = (string) config('services.blockchain.base_url');

        return $baseUrl !== '' ? $baseUrl : 'http://127.0.0.1:3001';
    }
}
