<?php

namespace Tests\Feature;

use App\Models\NotarialRegisterEntry;
use App\Models\NotaryCredential;
use App\Models\NotaryRequest;
use App\Models\User;
use App\Services\NotarySealService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotarySealServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_and_stores_verification_qr_from_primary_provider(): void
    {
        Storage::fake('local');
        Http::fake([
            'https://chart.googleapis.com/*' => Http::response('png-primary', 200, ['Content-Type' => 'image/png']),
            'https://api.qrserver.com/*' => Http::response('png-fallback', 200, ['Content-Type' => 'image/png']),
        ]);

        $entry = $this->makeRegisterEntry('qr-primary-token');

        $path = app(NotarySealService::class)->generateVerificationQrCode($entry);

        $this->assertSame('notary/qr/qr-primary-token.png', $path);
        Storage::disk('local')->assertExists($path);
        $this->assertSame('png-primary', Storage::disk('local')->get($path));
        $this->assertSame($path, $entry->fresh()->qr_code_path);
    }

    public function test_it_falls_back_to_secondary_provider_when_primary_generation_fails(): void
    {
        Storage::fake('local');
        Http::fake([
            'https://chart.googleapis.com/*' => Http::response('', 500),
            'https://api.qrserver.com/*' => Http::response('png-fallback', 200, ['Content-Type' => 'image/png']),
        ]);

        $entry = $this->makeRegisterEntry('qr-fallback-token');

        $path = app(NotarySealService::class)->generateVerificationQrCode($entry);

        $this->assertSame('notary/qr/qr-fallback-token.png', $path);
        Storage::disk('local')->assertExists($path);
        $this->assertSame('png-fallback', Storage::disk('local')->get($path));
        $this->assertSame($path, $entry->fresh()->qr_code_path);
    }

    private function makeRegisterEntry(string $token): NotarialRegisterEntry
    {
        $notary = User::factory()->notary()->create();
        $credential = NotaryCredential::factory()->create([
            'user_id' => $notary->id,
        ]);
        $request = NotaryRequest::factory()->for($notary)->create();

        return NotarialRegisterEntry::factory()->create([
            'notary_request_id' => $request->id,
            'notary_credential_id' => $credential->id,
            'qr_verification_token' => $token,
            'qr_code_path' => null,
        ]);
    }
}
