<?php

namespace Tests\Feature;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\SigningMethod;
use App\Enums\TemplateRoleType;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('admin.enotary.dashboard'));
        $response->assertStatus(200)
            ->assertSee(route('documents.index', absolute: false), false)
            ->assertSee('data-flux-sidebar-collapse', false)
            ->assertSee(__('Notifications'), false)
            ->assertSee(__('No notifications yet.'), false);
    }

    public function test_dashboard_shows_analytics_sections_and_counts(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $draft = Document::factory()->for($user)->create(['status' => DocumentStatus::Draft]);
        $pending = Document::factory()->for($user)->create(['status' => DocumentStatus::Pending]);
        $completed = Document::factory()->for($user)->create(['status' => DocumentStatus::Completed]);
        $rejected = Document::factory()->for($user)->create(['status' => DocumentStatus::Declined]);

        DocumentSigner::factory()->for($pending)->create(['status' => DocumentSignerStatus::Pending]);
        DocumentSigner::factory()->for($completed)->create(['status' => DocumentSignerStatus::Signed]);
        DocumentSigner::factory()->for($rejected)->create(['status' => DocumentSignerStatus::Signed]);
        DocumentSigner::factory()->for($draft)->create(['status' => DocumentSignerStatus::Pending]);

        $this->get(route('admin.signing.dashboard'))
            ->assertOk()
            ->assertSee('Draft')
            ->assertSee('Pending')
            ->assertSee('Completed')
            ->assertSee('Rejected')
            ->assertSee('Active certs')
            ->assertSee('Revoked')
            ->assertSee('Needs signature')
            ->assertSee('Pending approvers')
            ->assertSee('Links expiring')
            ->assertSee('Signer completion trend')
            ->assertSee('Signing methods')
            ->assertSee('Expiring signer links')
            ->assertSee('Drafts missing fields')
            ->assertSee('Activity trend')
            ->assertSee('Recent documents')
            ->assertSee('Most active signers')
            ->assertSee('data-chart=', false);
    }

    public function test_signing_workspace_sidebar_links_to_the_signer_dashboard(): void
    {
        /** @var User $user */
        $user = User::factory()->signer()->create();
        $this->actingAs($user);

        $this->get(route('documents.index'))
            ->assertOk()
            ->assertSee(route('signing.dashboard', absolute: false), false)
            ->assertSee(__('Dashboard'));

        $this->get(route('signing.dashboard'))
            ->assertOk()
            ->assertSee(__('Signer completion trend'))
            ->assertSee(__('Signing methods'));
    }

    public function test_signing_dashboard_scopes_signer_action_queue_to_the_users_organization(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var User $otherUser */
        $otherUser = User::factory()->create();
        $this->actingAs($user);

        $ownExpiringDocument = Document::factory()->for($user)->create([
            'title' => 'Tenant Contract',
            'status' => DocumentStatus::Pending,
        ]);

        DocumentSigner::factory()->for($ownExpiringDocument)->create([
            'name' => 'Alice Tenant',
            'status' => DocumentSignerStatus::Pending,
            'expires_at' => now()->addDay(),
            'signing_method' => SigningMethod::AccountVerified,
        ]);

        $ownApprovalDocument = Document::factory()->for($user)->create([
            'title' => 'Board Approval Packet',
            'status' => DocumentStatus::Pending,
        ]);

        DocumentSigner::factory()->for($ownApprovalDocument)->create([
            'name' => 'Approver One',
            'role_type' => TemplateRoleType::Approver,
            'status' => DocumentSignerStatus::Notified,
        ]);

        $ownDraft = Document::factory()->for($user)->create([
            'title' => 'Missing Fields Draft',
            'status' => DocumentStatus::Draft,
        ]);

        DocumentSigner::factory()->for($ownDraft)->create([
            'role_type' => TemplateRoleType::Signer,
        ]);

        $otherDocument = Document::factory()->for($otherUser)->create([
            'title' => 'Other Tenant Contract',
            'status' => DocumentStatus::Pending,
        ]);

        DocumentSigner::factory()->for($otherDocument)->create([
            'name' => 'Outside Signer',
            'status' => DocumentSignerStatus::Pending,
            'expires_at' => now()->addDay(),
        ]);

        $this->get(route('admin.signing.dashboard'))
            ->assertOk()
            ->assertSee('Tenant Contract')
            ->assertSee('Alice Tenant')
            ->assertSee('Board Approval Packet')
            ->assertSee('Approver One')
            ->assertSee('Missing Fields Draft')
            ->assertDontSee('Other Tenant Contract')
            ->assertDontSee('Outside Signer');
    }

    public function test_documents_page_includes_breadcrumb_in_layout(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('documents.index'))
            ->assertOk()
            ->assertSee('data-flux-breadcrumbs', false);
    }

    public function test_authenticated_users_can_visit_the_verify_placeholder(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('verify.index'))
            ->assertOk()
            ->assertSee('Verify document');
    }

    public function test_signer_is_redirected_away_from_admin_dashboard(): void
    {
        $user = User::factory()->signer()->create();
        $this->actingAs($user);

        $this->get(route('dashboard'))->assertRedirect(route('documents.index'));
    }

    public function test_notary_is_redirected_away_from_admin_dashboard(): void
    {
        $user = User::factory()->notary()->create();
        $this->actingAs($user);

        $this->get(route('dashboard'))->assertRedirect(route('notary.dashboard'));
    }

    public function test_notary_dashboard_includes_compliance_certificate_section(): void
    {
        $notary = User::factory()->notary()->create();

        $this->actingAs($notary)
            ->get(route('notary.dashboard'))
            ->assertOk()
            ->assertSee(__('Compliance · signer certificates'), false)
            ->assertSee(__('Attorney workspace'), false);
    }

    public function test_welcome_dashboard_link_points_to_signer_home_route(): void
    {
        $user = User::factory()->signer()->create();
        $this->actingAs($user);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('href="'.route('documents.index').'"', false)
            ->assertDontSee('href="'.route('dashboard').'"', false);
    }
}
