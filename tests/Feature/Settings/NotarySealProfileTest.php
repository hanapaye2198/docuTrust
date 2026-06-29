<?php

namespace Tests\Feature\Settings;

use App\Models\NotaryCredential;
use App\Models\NotaryRequest;
use App\Models\User;
use App\Services\Notary\NotarySealProfileService;
use App\Services\NotaryRequestWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotarySealProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_notary_can_upload_seal_from_trust_profile(): void
    {
        Storage::fake('local');
        config(['filesystems.docutrust_disk' => 'local']);

        $notary = User::factory()->notary()->create();
        $credential = NotaryCredential::query()->where('user_id', $notary->id)->firstOrFail();
        $this->assertNull($credential->seal_image_path);

        $this->actingAs($notary);

        $this->post(route('settings.trust-profile.seal.store'), [
            'notary_seal_upload' => UploadedFile::fake()->image('seal.png'),
        ])->assertRedirect(route('settings.trust-profile', [], false).'#notary-seal');

        $credential->refresh();

        $this->assertNotNull($credential->seal_image_path);
        $this->assertTrue(Storage::disk('local')->exists($credential->seal_image_path));
        $this->assertTrue(app(NotarySealProfileService::class)->hasSealOnFile($notary));
    }

    public function test_uploaded_seal_reflects_on_notary_case_process(): void
    {
        Storage::fake('local');
        config(['filesystems.docutrust_disk' => 'local']);

        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
        ]);

        $this->actingAs($notary);

        $this->post(route('settings.trust-profile.seal.store'), [
            'notary_seal_upload' => UploadedFile::fake()->image('seal.png'),
        ])->assertRedirect(route('settings.trust-profile', [], false).'#notary-seal');

        $this->assertTrue(app(NotaryRequestWorkflowService::class)->hasAttorneySealOnFile($request));

        $this->get(route('notary.requests.show', [$request, 'fees']))
            ->assertOk()
            ->assertSee('Seal uploaded')
            ->assertSee('No upload is needed here')
            ->assertDontSee('Upload your personal seal in trust profile before creating the official register entry.');
    }

    public function test_trust_profile_photo_upload_uses_plain_post_endpoint(): void
    {
        Storage::fake('local');
        config(['filesystems.docutrust_disk' => 'local']);

        $notary = User::factory()->notary()->create();
        $this->assertNull($notary->profile_photo_path);

        $this->actingAs($notary)
            ->post(route('settings.trust-profile.photo.store'), [
                'profile_photo' => UploadedFile::fake()->image('profile.jpg'),
            ])
            ->assertRedirect(route('settings.trust-profile', [], false));

        $notary->refresh();

        $this->assertNotNull($notary->profile_photo_path);
        $this->assertTrue(Storage::disk('local')->exists($notary->profile_photo_path));
    }

    public function test_trust_profile_signature_upload_uses_plain_post_endpoint(): void
    {
        Storage::fake('local');
        config(['filesystems.docutrust_disk' => 'local']);

        $notary = User::factory()->notary()->create();
        $this->assertNull($notary->signature_image_path);

        $this->actingAs($notary)
            ->post(route('settings.trust-profile.signature.store'), [
                'signature_upload' => UploadedFile::fake()->image('signature.png'),
            ])
            ->assertRedirect(route('settings.trust-profile', [], false).'#signature');

        $notary->refresh();

        $this->assertNotNull($notary->signature_image_path);
        $this->assertSame('uploaded', $notary->signature_type);
        $this->assertTrue(Storage::disk('local')->exists($notary->signature_image_path));
    }

    public function test_missing_seal_file_is_not_treated_as_uploaded(): void
    {
        Storage::fake('local');
        config(['filesystems.docutrust_disk' => 'local']);

        $notary = User::factory()->notary()->create();
        $credential = NotaryCredential::query()->where('user_id', $notary->id)->firstOrFail();
        $credential->update(['seal_image_path' => 'notary/seals/missing-seal.png']);
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
        ]);

        $this->assertFalse(app(NotarySealProfileService::class)->hasSealOnFile($notary));
        $this->assertFalse(app(NotaryRequestWorkflowService::class)->hasAttorneySealOnFile($request));

        $this->actingAs($notary)
            ->get(route('notary.requests.show', [$request, 'fees']))
            ->assertOk()
            ->assertSee('Open trust profile')
            ->assertDontSee('Seal uploaded');
    }

    public function test_trust_profile_shows_notary_seal_section_for_attorneys(): void
    {
        $notary = User::factory()->notary()->create();

        $response = $this->actingAs($notary)
            ->get(route('settings.trust-profile'))
            ->assertOk()
            ->assertSee('Notary personal seal')
            ->assertSee('Upload once here')
            ->assertSee('Choose seal image')
            ->assertSee('action="'.route('settings.trust-profile.seal.store', [], false).'"', false)
            ->assertSee('name="notary_seal_upload"', false)
            ->assertSee('action="'.route('settings.trust-profile.photo.store', [], false).'"', false)
            ->assertSee('name="profile_photo"', false)
            ->assertSee('action="'.route('settings.trust-profile.signature.store', [], false).'"', false)
            ->assertSee('name="signature_upload"', false)
            ->assertSee('Preview selected seal')
            ->assertSee('FileReader', false)
            ->assertDontSee('wire:model="notarySealUpload"', false)
            ->assertDontSee('wire:model="profilePhoto"', false)
            ->assertDontSee('wire:model="signatureUpload"', false);

        $href = route('settings.trust-profile', [], false);
        $content = $response->getContent();
        $trustProfileNavOffset = strpos($content, $href);

        $this->assertNotFalse($trustProfileNavOffset);
        $this->assertStringNotContainsString(
            'wire:navigate',
            substr($content, $trustProfileNavOffset, 250)
        );
    }

    public function test_case_seal_prompt_uses_normal_navigation_to_trust_profile_anchor(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
        ]);

        $response = $this->actingAs($notary)
            ->get(route('notary.requests.show', [$request, 'fees']))
            ->assertOk()
            ->assertSee('Open trust profile');

        $href = route('settings.trust-profile', [], false).'#notary-seal';
        $content = $response->getContent();
        $anchorOffset = strpos($content, $href);

        $this->assertNotFalse($anchorOffset);
        $this->assertStringNotContainsString(
            'wire:navigate',
            substr($content, $anchorOffset, 250)
        );
    }

    public function test_trust_profile_seal_asset_route_returns_uploaded_seal(): void
    {
        Storage::fake('local');
        config(['filesystems.docutrust_disk' => 'local']);

        $notary = User::factory()->notary()->create();
        $credential = NotaryCredential::query()->where('user_id', $notary->id)->firstOrFail();
        $path = 'notary/seals/test-seal.png';
        Storage::disk('local')->put($path, 'seal-binary');
        $credential->update(['seal_image_path' => $path]);

        $this->actingAs($notary)
            ->get(route('settings.trust-profile.seal'))
            ->assertOk();
    }

    public function test_credentials_page_points_seal_management_to_trust_profile(): void
    {
        $notary = User::factory()->notary()->create();

        $response = $this->actingAs($notary)
            ->get(route('notary.credentials'))
            ->assertOk()
            ->assertSee('trust profile')
            ->assertSee('Open trust profile');

        $href = route('settings.trust-profile', [], false).'#notary-seal';
        $content = $response->getContent();
        $anchorOffset = strpos($content, $href);

        $this->assertNotFalse($anchorOffset);
        $this->assertStringNotContainsString(
            'wire:navigate',
            substr($content, $anchorOffset, 250)
        );
    }
}
