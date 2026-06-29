<?php

namespace Tests\Feature\Ekyc;

use App\Contracts\Ekyc\IdDocumentTextExtractor;
use App\Enums\EkycStatus;
use App\Enums\OnboardingStep;
use App\Exceptions\EkycOcrUnavailableException;
use App\Models\EkycRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Volt\Volt;
use Tests\TestCase;

class OnboardingKycNameVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_kyc_verifies_when_ocr_name_matches_account(): void
    {
        $user = User::factory()->signer()->create([
            'first_name' => 'Juan',
            'middle_name' => 'Dela',
            'last_name' => 'Cruz',
            'name' => 'Juan Dela Cruz',
            'onboarding_step' => OnboardingStep::Kyc,
            'email_verified_at' => now(),
            'mobile_verified_at' => now(),
            'mfa_enabled' => false,
        ]);

        $this->mock(IdDocumentTextExtractor::class)
            ->shouldReceive('extract')
            ->once()
            ->andReturn('JUAN DELA CRUZ PHILIPPINE PASSPORT');

        $this->actingAs($user);

        Volt::test('auth.onboarding-kyc')
            ->set('kyc_id_type', 'passport')
            ->set('id_document', UploadedFile::fake()->image('id.jpg', 200, 200))
            ->call('continue')
            ->assertHasNoErrors()
            ->assertRedirect(route('onboarding.mfa', absolute: false));

        $user->refresh();

        $this->assertSame(EkycStatus::Verified, $user->ekyc_status);
        $this->assertSame(OnboardingStep::Mfa, $user->onboarding_step);
        $this->assertDatabaseHas('ekyc_records', [
            'user_id' => $user->id,
            'status' => EkycStatus::Verified->value,
        ]);
    }

    public function test_kyc_moves_to_pending_review_when_ocr_name_does_not_match(): void
    {
        $user = User::factory()->signer()->create([
            'first_name' => 'Juan',
            'last_name' => 'Cruz',
            'name' => 'Juan Cruz',
            'onboarding_step' => OnboardingStep::Kyc,
            'email_verified_at' => now(),
            'mobile_verified_at' => now(),
            'mfa_enabled' => false,
        ]);

        $this->mock(IdDocumentTextExtractor::class)
            ->shouldReceive('extract')
            ->once()
            ->andReturn('PEDRO SANTOS LICENSE');

        $this->actingAs($user);

        Volt::test('auth.onboarding-kyc')
            ->set('kyc_id_type', 'drivers_license')
            ->set('id_document', UploadedFile::fake()->image('id.jpg', 200, 200))
            ->call('continue')
            ->assertHasNoErrors()
            ->assertRedirect(route('onboarding.mfa', absolute: false));

        $user->refresh();

        $this->assertSame(EkycStatus::Pending, $user->ekyc_status);
        $this->assertSame(OnboardingStep::Mfa, $user->onboarding_step);
        $this->assertNull($user->kyc_verified_at);
        $this->assertDatabaseHas('ekyc_records', [
            'user_id' => $user->id,
            'status' => EkycStatus::Pending->value,
        ]);
    }

    public function test_kyc_moves_to_pending_review_when_ocr_is_unavailable(): void
    {
        $user = User::factory()->signer()->create([
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'name' => 'Ana Reyes',
            'onboarding_step' => OnboardingStep::Kyc,
            'email_verified_at' => now(),
            'mobile_verified_at' => now(),
        ]);

        $this->mock(IdDocumentTextExtractor::class)
            ->shouldReceive('extract')
            ->once()
            ->andThrow(new EkycOcrUnavailableException('OCR unavailable.'));

        $this->actingAs($user);

        Volt::test('auth.onboarding-kyc')
            ->set('kyc_id_type', 'national_id')
            ->set('id_document', UploadedFile::fake()->image('id.jpg', 200, 200))
            ->call('continue')
            ->assertHasNoErrors()
            ->assertRedirect(route('onboarding.mfa', absolute: false));

        $user->refresh();

        $this->assertSame(EkycStatus::Pending, $user->ekyc_status);
        $this->assertSame(OnboardingStep::Mfa, $user->onboarding_step);
        $this->assertSame(1, EkycRecord::query()->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('ekyc_records', [
            'user_id' => $user->id,
            'status' => EkycStatus::Pending->value,
            'rejection_reason' => 'OCR unavailable.',
        ]);
    }
}
