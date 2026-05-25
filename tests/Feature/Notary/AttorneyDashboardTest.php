<?php

namespace Tests\Feature\Notary;

use App\Enums\NotaryRequestStatus;
use App\Models\NotaryRequest;
use App\Models\User;
use App\Services\Attorney\AttorneyDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttorneyDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_attorney_dashboard_shows_command_center_sections(): void
    {
        $notary = User::factory()->notary()->create();

        $this->actingAs($notary)
            ->get(route('notary.dashboard'))
            ->assertOk()
            ->assertSee(__('Attorney workspace'), false)
            ->assertSee(__('Continue work'), false)
            ->assertSee(__('eNOTARY readiness'), false)
            ->assertSee(__('Compliance · signer certificates'), false);
    }

    public function test_attorney_dashboard_metrics_reflect_assigned_requests(): void
    {
        $notary = User::factory()->notary()->create();

        NotaryRequest::factory()->create([
            'notary_user_id' => $notary->id,
            'organization_id' => $notary->organization_id,
            'status' => NotaryRequestStatus::Submitted,
        ]);

        NotaryRequest::factory()->create([
            'notary_user_id' => $notary->id,
            'organization_id' => $notary->organization_id,
            'status' => NotaryRequestStatus::Notarized,
        ]);

        $metrics = app(AttorneyDashboardService::class)->dashboardData($notary)['metrics'];

        $this->assertSame(2, $metrics['total']);
        $this->assertSame(1, $metrics['open']);
        $this->assertSame(1, $metrics['notarized']);
    }

    public function test_continue_work_only_includes_requests_assigned_to_attorney(): void
    {
        $notary = User::factory()->notary()->create();
        $otherNotary = User::factory()->notary()->create([
            'organization_id' => $notary->organization_id,
        ]);

        $mine = NotaryRequest::factory()->create([
            'notary_user_id' => $notary->id,
            'organization_id' => $notary->organization_id,
            'title' => 'My assigned request',
            'status' => NotaryRequestStatus::AttorneySigning,
        ]);

        NotaryRequest::factory()->create([
            'notary_user_id' => $otherNotary->id,
            'organization_id' => $notary->organization_id,
            'title' => 'Other attorney request',
            'status' => NotaryRequestStatus::AttorneySigning,
        ]);

        $continueWork = app(AttorneyDashboardService::class)->continueWorkRequests($notary);

        $this->assertCount(1, $continueWork);
        $this->assertTrue($continueWork->first()->is($mine));
    }
}
