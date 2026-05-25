<?php

namespace Tests\Feature;

use App\Enums\NotaryCredentialStatus;
use App\Enums\NotaryRequestStatus;
use App\Enums\UserRole;
use App\Models\NotaryCredential;
use App\Models\NotaryRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_platform_dashboard_at_home(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('Platform Dashboard'))
            ->assertSee(__('Action queue'))
            ->assertSee(__('Top organizations'));
    }

    public function test_notary_admin_still_sees_enotary_dashboard_at_home(): void
    {
        $notaryAdmin = User::factory()->create([
            'role' => UserRole::NotaryAdmin,
        ]);

        $this->actingAs($notaryAdmin)
            ->get(route('admin.enotary.dashboard'))
            ->assertOk()
            ->assertSee(__('Notary Admin Dashboard'))
            ->assertDontSee(__('Platform Dashboard'));

        $this->actingAs($notaryAdmin)
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.enotary.dashboard'));
    }

    public function test_super_admin_can_access_enotary_operations_dashboard(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->get(route('admin.enotary.dashboard'))
            ->assertOk()
            ->assertSee(__('Notary Admin Dashboard'));
    }

    public function test_action_queue_includes_pending_attorney_application(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $notary = User::factory()->notary()->create();

        $credential = NotaryCredential::factory()->create([
            'user_id' => $notary->id,
            'status' => NotaryCredentialStatus::Pending->value,
            'submitted_at' => now(),
        ]);

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('Attorney application pending'))
            ->assertSee(route('admin.attorney-applications.show', $credential, absolute: false), false);
    }

    public function test_action_queue_includes_request_awaiting_finalization(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $organization = Organization::factory()->create();
        $requester = User::factory()->client()->for($organization)->create();

        $request = NotaryRequest::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $requester->id,
            'status' => NotaryRequestStatus::Digitalized,
            'title' => 'Lease Agreement Finalization',
            'approved_at' => now(),
        ]);

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('Awaiting finalization'))
            ->assertSee('Lease Agreement Finalization');
    }

    public function test_notary_admin_cannot_access_platform_users_admin(): void
    {
        $notaryAdmin = User::factory()->create([
            'role' => UserRole::NotaryAdmin,
        ]);

        $this->actingAs($notaryAdmin)
            ->get(route('admin.users.index'))
            ->assertRedirect(route('admin.enotary.dashboard'));
    }
}
