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
        ])->assertRedirect(route('settings.trust-profile').'#notary-seal');

        $credential->refresh();

        $this->assertNotNull($credential->seal_image_path);
        Storage::disk('local')->assertExists($credential->seal_image_path);
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
        ])->assertRedirect(route('settings.trust-profile').'#notary-seal');

        $this->assertTrue(app(NotaryRequestWorkflowService::class)->hasAttorneySealOnFile($request));

        $this->get(route('notary.requests.show', [$request, 'fees']))
            ->assertOk()
            ->assertSee('Seal uploaded')
            ->assertSee('No upload is needed here')
            ->assertDontSee('Upload your personal seal in trust profile before creating the official register entry.');
    }

    public function test_trust_profile_shows_notary_seal_section_for_attorneys(): void
    {
        $notary = User::factory()->notary()->create();

        $this->actingAs($notary)
            ->get(route('settings.trust-profile'))
            ->assertOk()
            ->assertSee('Notary personal seal')
            ->assertSee('Upload once here')
            ->assertSee('Choose seal image');
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

        $this->actingAs($notary)
            ->get(route('notary.credentials'))
            ->assertOk()
            ->assertSee('trust profile')
            ->assertSee('Open trust profile');
    }
}
