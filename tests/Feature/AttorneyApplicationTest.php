<?php

namespace Tests\Feature;

use App\Enums\NotaryCredentialStatus;
use App\Enums\OnboardingStep;
use App\Enums\UserRole;
use App\Models\NotaryCredential;
use App\Models\User;
use App\Services\AttorneyApplicationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AttorneyApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake((string) config('filesystems.docutrust_disk', 'local'));
        Mail::fake();
    }

    private function readyClient(): User
    {
        $user = User::factory()->create([
            'role' => UserRole::Client,
            'onboarding_step' => OnboardingStep::Completed,
            'email_verified_at' => now(),
            'mobile_verified_at' => now(),
            'mfa_enabled' => true,
        ]);

        return $user;
    }

    public function test_client_can_submit_attorney_application(): void
    {
        $client = $this->readyClient();

        $this->actingAs($client);

        Volt::test('settings.attorney-application')
            ->set('commissionNumber', 'CN-TEST-0001')
            ->set('commissionJurisdiction', 'Davao City')
            ->set('commissionIssuedAt', now()->subMonths(6)->format('Y-m-d'))
            ->set('commissionExpiresAt', now()->addYear()->format('Y-m-d'))
            ->set('rollNumber', '12345')
            ->set('ibpNumber', 'IBP-001')
            ->set('ptrNumber', 'PTR-001')
            ->set('commissionDocument', UploadedFile::fake()->create('commission.pdf', 100, 'application/pdf'))
            ->set('ibpDocument', UploadedFile::fake()->create('ibp.pdf', 100, 'application/pdf'))
            ->set('ptrDocument', UploadedFile::fake()->create('ptr.pdf', 100, 'application/pdf'))
            ->set('sealImage', UploadedFile::fake()->image('seal.png'))
            ->assertSee('Seal selected')
            ->set('signatureImage', UploadedFile::fake()->image('sig.png'))
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notary_credentials', [
            'user_id' => $client->id,
            'commission_number' => 'CN-TEST-0001',
            'status' => NotaryCredentialStatus::Pending->value,
        ]);

        $this->assertSame(UserRole::Client, $client->fresh()->role);
    }

    public function test_notary_admin_can_approve_application_and_promote_user_to_notary(): void
    {
        $this->seed(DatabaseSeeder::class);

        $client = $this->readyClient();
        $credential = NotaryCredential::factory()->create([
            'user_id' => $client->id,
            'status' => NotaryCredentialStatus::Pending->value,
            'submitted_at' => now(),
            'commission_expires_at' => now()->addYear(),
        ]);

        $admin = User::query()->where('email', 'notaryadmin@docutrust.tech')->first();
        $this->assertNotNull($admin);

        app(AttorneyApplicationService::class)->approve($credential, $admin);

        $client->refresh();
        $credential->refresh();

        $this->assertSame(UserRole::Notary, $client->role);
        $this->assertSame(NotaryCredentialStatus::Active->value, $credential->status);
        $this->assertSame($admin->id, $credential->reviewed_by_user_id);
    }

    public function test_notary_admin_can_reject_application_and_keep_client_role(): void
    {
        $this->seed(DatabaseSeeder::class);

        $client = $this->readyClient();
        $credential = NotaryCredential::factory()->create([
            'user_id' => $client->id,
            'status' => NotaryCredentialStatus::Pending->value,
            'submitted_at' => now(),
            'commission_expires_at' => now()->addYear(),
        ]);

        $admin = User::query()->where('email', 'notaryadmin@docutrust.tech')->first();
        $this->assertNotNull($admin);

        app(AttorneyApplicationService::class)->reject($credential, $admin, 'Incomplete PTR document');

        $client->refresh();
        $credential->refresh();

        $this->assertSame(UserRole::Client, $client->role);
        $this->assertSame(NotaryCredentialStatus::Rejected->value, $credential->status);
        $this->assertSame('Incomplete PTR document', $credential->rejection_reason);
    }

    public function test_notary_without_active_credential_cannot_access_notary_dashboard(): void
    {
        $notary = User::factory()->notaryWithoutCredential()->create();

        $this->actingAs($notary)
            ->get(route('notary.dashboard'))
            ->assertRedirect(route('settings.attorney-application'));
    }

    public function test_notary_with_active_credential_can_access_notary_dashboard(): void
    {
        $notary = User::factory()->notary()->create([
            'onboarding_step' => OnboardingStep::Completed,
            'mfa_enabled' => true,
        ]);

        NotaryCredential::factory()->create([
            'user_id' => $notary->id,
            'status' => NotaryCredentialStatus::Active->value,
            'commission_expires_at' => now()->addYear(),
        ]);

        $this->actingAs($notary)
            ->get(route('notary.dashboard'))
            ->assertOk();
    }

    public function test_expired_commission_blocks_practice_eligibility(): void
    {
        $notary = User::factory()->notary()->create([
            'onboarding_step' => OnboardingStep::Completed,
            'mfa_enabled' => true,
        ]);

        NotaryCredential::factory()->create([
            'user_id' => $notary->id,
            'status' => NotaryCredentialStatus::Active->value,
            'commission_expires_at' => now()->subDay(),
        ]);

        $eligibility = app(AttorneyApplicationService::class)->practiceEligibility($notary);

        $this->assertFalse($eligibility['allowed']);
        $this->assertTrue(app(AttorneyApplicationService::class)->canSubmitApplication($notary->fresh()));
    }

    public function test_client_cannot_view_attorney_applications_queue(): void
    {
        $response = $this->actingAs($this->readyClient())
            ->get(route('admin.attorney-applications.index'));

        $this->assertContains($response->status(), [403, 302]);
    }

    public function test_notary_admin_can_view_attorney_applications_queue(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'notaryadmin@docutrust.tech')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.attorney-applications.index'))
            ->assertOk();
    }
}
