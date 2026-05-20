<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Services\Admin\UserDeletionImpactService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt as LivewireVolt;
use Tests\TestCase;

class PlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_global_documents_from_other_organization(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $superAdmin = User::factory()->superAdmin()->for($orgA)->create();
        $owner = User::factory()->client()->for($orgB)->create();

        $document = Document::factory()->for($owner)->create([
            'organization_id' => $orgB->id,
            'status' => DocumentStatus::Pending,
        ]);

        $this->actingAs($superAdmin)
            ->get(route('documents.show', $document))
            ->assertOk();
    }

    public function test_super_admin_documents_index_lists_cross_organization_documents(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $superAdmin = User::factory()->superAdmin()->for($orgA)->create();
        $owner = User::factory()->client()->for($orgB)->create();

        Document::factory()->for($owner)->create([
            'organization_id' => $orgB->id,
            'title' => 'Cross Org Agreement',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertSee('Cross Org Agreement');
    }

    public function test_super_admin_can_access_users_admin(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee(__('Platform users'));
    }

    public function test_client_cannot_access_users_admin(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->get(route('admin.users.index'))
            ->assertRedirect(route('documents.index'));
    }

    public function test_client_with_completed_documents_cannot_be_hard_deleted(): void
    {
        $client = User::factory()->client()->create();
        Document::factory()->for($client)->create(['status' => DocumentStatus::Completed]);

        $impact = app(UserDeletionImpactService::class)->for($client);

        $this->assertFalse($impact['can_hard_delete']);
        $this->assertNotNull($impact['block_reason']);
    }

    public function test_super_admin_seeder_account_exists(): void
    {
        $this->seed(\Database\Seeders\SuperAdminSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'superadmin@docutrust.tech',
            'role' => UserRole::SuperAdmin->value,
        ]);
    }

    public function test_deactivated_user_cannot_login(): void
    {
        $user = User::factory()->client()->create([
            'email' => 'deactivated@example.test',
            'password' => 'password',
            'deactivated_at' => now(),
        ]);

        LivewireVolt::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('email');
    }
}
