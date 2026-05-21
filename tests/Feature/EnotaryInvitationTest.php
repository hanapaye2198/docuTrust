<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserWorkspace;
use App\Mail\EnotarySignerInvitationMail;
use App\Models\EnotaryInvitation;
use App\Models\NotaryRequest;
use App\Models\NotarySigner;
use App\Models\User;
use App\Services\EnotaryInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use Tests\TestCase;

class EnotaryInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_attorney_invite_creates_token_and_sends_mail(): void
    {
        Mail::fake();

        [$attorney, $request, $signer] = $this->seedAttorneyCase();

        $invitation = app(EnotaryInvitationService::class)->inviteSignerFromAttorney($attorney, $request, $signer);

        $this->assertInstanceOf(EnotaryInvitation::class, $invitation);
        $this->assertDatabaseHas('enotary_invitations', [
            'email' => 'invitee@docutrust.test',
            'notary_signer_id' => $signer->id,
        ]);

        Mail::assertQueued(EnotarySignerInvitationMail::class, function (EnotarySignerInvitationMail $mail): bool {
            return str_contains($mail->acceptUrl, '/enotary/invite/');
        });
    }

    public function test_invite_accept_page_renders_for_valid_token(): void
    {
        $invitation = $this->createPendingInvitation();

        $this->get(route('enotary.invite.accept', ['token' => $invitation->token]))
            ->assertOk()
            ->assertSee('Accept invitation', escape: false)
            ->assertSee($invitation->email, escape: false);
    }

    public function test_new_user_can_accept_invitation_and_start_onboarding(): void
    {
        Mail::fake();

        $invitation = $this->createPendingInvitation();

        Volt::test('auth.enotary-invite-accept', ['token' => $invitation->token])
            ->set('first_name', 'Invite')
            ->set('last_name', 'Signer')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('agreed_to_terms', true)
            ->call('acceptAsNewUser')
            ->assertHasNoErrors()
            ->assertRedirect(route('onboarding.email.verify', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'invitee@docutrust.test',
            'workspace' => UserWorkspace::Enotary->value,
            'role' => UserRole::Client->value,
        ]);

        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_document_signer_cannot_accept_enotary_invitation(): void
    {
        $invitation = $this->createPendingInvitation();

        User::factory()->signer()->create([
            'email' => 'invitee@docutrust.test',
        ]);

        Volt::test('auth.enotary-invite-accept', ['token' => $invitation->token])
            ->set('first_name', 'Invite')
            ->set('last_name', 'Signer')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('agreed_to_terms', true)
            ->call('acceptAsNewUser')
            ->assertHasErrors('email');
    }

    public function test_authenticated_enotary_user_can_accept_invitation(): void
    {
        $invitation = $this->createPendingInvitation();

        $user = User::factory()->enotarySigner()->create([
            'email' => 'invitee@docutrust.test',
            'organization_id' => $invitation->organization_id,
        ]);

        $this->actingAs($user);

        Volt::test('auth.enotary-invite-accept', ['token' => $invitation->token])
            ->call('acceptWhileSignedIn')
            ->assertHasNoErrors()
            ->assertRedirect(route('settings.trust-profile', absolute: false));

        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    /**
     * @return array{0: User, 1: NotaryRequest, 2: NotarySigner}
     */
    private function seedAttorneyCase(): array
    {
        $attorney = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->create([
            'organization_id' => $attorney->organization_id,
            'notary_user_id' => $attorney->id,
        ]);
        $signer = NotarySigner::factory()->create([
            'notary_request_id' => $request->id,
            'email' => 'invitee@docutrust.test',
            'full_name' => 'Invitee Signer',
        ]);

        return [$attorney, $request, $signer];
    }

    private function createPendingInvitation(): EnotaryInvitation
    {
        [$attorney, $request, $signer] = $this->seedAttorneyCase();

        return app(EnotaryInvitationService::class)->inviteSignerFromAttorney($attorney, $request, $signer);
    }
}
