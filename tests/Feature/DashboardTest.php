<?php

namespace Tests\Feature;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
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
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/dashboard');
        $response->assertStatus(200)
            ->assertSee(route('documents.index', absolute: false), false)
            ->assertSee('data-flux-sidebar-collapse', false)
            ->assertSee(__('Notifications'), false)
            ->assertSee(__('No notifications yet.'), false);
    }

    public function test_dashboard_shows_analytics_sections_and_counts(): void
    {
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

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Draft')
            ->assertSee('Pending')
            ->assertSee('Completed')
            ->assertSee('Rejected')
            ->assertSee('Activity trend')
            ->assertSee('Recent activity')
            ->assertSee('Most active signers')
            ->assertSee('data-chart=', false);
    }

    public function test_documents_page_includes_breadcrumb_in_layout(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('documents.index'))
            ->assertOk()
            ->assertSee('data-flux-breadcrumbs', false);
    }

    public function test_authenticated_users_can_visit_the_verify_placeholder(): void
    {
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
