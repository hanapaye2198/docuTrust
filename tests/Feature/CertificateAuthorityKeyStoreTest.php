<?php

namespace Tests\Feature;

use App\Services\CertificateAuthorityService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CertificateAuthorityKeyStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_ca_private_key_can_be_stored_outside_the_database(): void
    {
        $path = storage_path('framework/testing/root-ca-'.uniqid().'.pem');

        config()->set('docutrust.pki.root_ca_private_key_path', $path);

        $authority = app(CertificateAuthorityService::class)->getOrCreateRootAuthority();

        $this->assertSame('external://root-ca', $authority->private_key_pem);
        $this->assertTrue(File::exists($path));
        $this->assertStringContainsString('BEGIN PRIVATE KEY', File::get($path));

        $reloadedAuthority = $authority->fresh();
        $this->assertNotNull($reloadedAuthority);

        $privateKeyPem = app(\App\Contracts\CertificateAuthorityKeyStore::class)->privateKeyPemFor($reloadedAuthority);
        $this->assertStringContainsString('BEGIN PRIVATE KEY', $privateKeyPem);

        File::delete($path);
    }

    public function test_existing_root_ca_private_key_can_be_moved_to_external_store_via_command(): void
    {
        $path = storage_path('framework/testing/root-ca-move-'.uniqid().'.pem');

        config()->set('docutrust.pki.root_ca_private_key_path', $path);
        $authority = app(CertificateAuthorityService::class)->getOrCreateRootAuthority();

        $externalPem = File::get($path);

        $authority->update([
            'private_key_pem' => $externalPem,
        ]);

        File::delete($path);
        $this->assertFalse(File::exists($path));

        $exitCode = Artisan::call('docutrust:move-root-ca-key');

        $this->assertSame(0, $exitCode);
        $authority->refresh();
        $this->assertSame('external://root-ca', $authority->private_key_pem);
        $this->assertTrue(File::exists($path));
        $this->assertSame($externalPem, File::get($path));

        File::delete($path);
    }
}
