<?php

namespace Tests\Feature;

use App\Models\NotaryRequest;
use App\Models\NotarySigner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnotaryPortalAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_enotary_signer_cannot_browse_notary_requests_index(): void
    {
        $signer = User::factory()->enotarySigner()->create();

        $this->actingAs($signer)
            ->get(route('notary-requests.index'))
            ->assertForbidden();
    }

    public function test_enotary_signer_cannot_create_notary_requests(): void
    {
        $signer = User::factory()->enotarySigner()->create();
        $notary = User::factory()->notary()->for($signer->organization)->create();

        $this->actingAs($signer)
            ->get(route('notary-requests.create'))
            ->assertForbidden();

        $this->actingAs($signer)
            ->get(route('notary-requests.index'))
            ->assertForbidden();
    }

    public function test_enotary_signer_home_route_is_trust_profile(): void
    {
        $signer = User::factory()->enotarySigner()->create();

        $this->assertSame('settings.trust-profile', $signer->homeRouteName());
    }

    public function test_enotary_signer_can_view_assigned_case_show_page(): void
    {
        $notary = User::factory()->notary()->create();
        $signerUser = User::factory()->enotarySigner()->for($notary->organization)->create([
            'email' => 'party@docutrust.test',
        ]);

        $request = NotaryRequest::factory()->create([
            'organization_id' => $notary->organization_id,
            'notary_user_id' => $notary->id,
            'user_id' => $notary->id,
        ]);

        NotarySigner::factory()->for($request)->create([
            'email' => 'party@docutrust.test',
            'full_name' => 'Party Signer',
        ]);

        $this->assertTrue($signerUser->isNotarySignerOn($request));

        $this->actingAs($signerUser)
            ->get(route('notary-requests.show', $request))
            ->assertOk();
    }

    public function test_enotary_signer_cannot_view_unrelated_case(): void
    {
        $notary = User::factory()->notary()->create();
        $signerUser = User::factory()->enotarySigner()->for($notary->organization)->create([
            'email' => 'party@docutrust.test',
        ]);

        $otherRequest = NotaryRequest::factory()->create([
            'organization_id' => $notary->organization_id,
            'notary_user_id' => $notary->id,
            'user_id' => $notary->id,
        ]);

        NotarySigner::factory()->for($otherRequest)->create([
            'email' => 'someone-else@docutrust.test',
        ]);

        $this->actingAs($signerUser)
            ->get(route('notary-requests.show', $otherRequest))
            ->assertForbidden();
    }

    public function test_notary_admin_can_still_browse_notary_requests(): void
    {
        $admin = User::factory()->notaryAdmin()->create();

        $this->actingAs($admin)
            ->get(route('notary-requests.index'))
            ->assertOk();
    }
}
