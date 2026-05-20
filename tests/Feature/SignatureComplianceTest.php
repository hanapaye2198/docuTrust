<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Compliance\SignatureComplianceService;
use App\Support\SignatureFeatures;
use App\Support\TrustLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignatureComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'signature.features.hsm.enabled' => false,
            'signature.features.aws_kms.enabled' => false,
            'signature.features.pkcs11.enabled' => false,
            'docutrust.pki.signing_backend' => 'app_managed',
        ]);
    }

    public function test_compliance_assessment_excludes_disabled_hsm_from_score(): void
    {
        $report = app(SignatureComplianceService::class)->assess();

        $hsm = collect($report['categories'])->firstWhere('key', 'hsm_integration');
        $kms = collect($report['categories'])->firstWhere('key', 'aws_kms');
        $pkcs11 = collect($report['categories'])->firstWhere('key', 'pkcs11');

        $this->assertSame('DISABLED', $hsm['status']);
        $this->assertNull($hsm['score_percentage']);
        $this->assertSame('DISABLED', $kms['status']);
        $this->assertSame('DISABLED', $pkcs11['status']);
        $this->assertGreaterThan(0, $report['overall_score']);
        $this->assertArrayHasKey('trust_level', $report);
    }

    public function test_trust_level_capped_at_three_for_app_managed_without_hsm(): void
    {
        config([
            'docutrust.pki.signing_backend' => 'app_managed',
            'signature.features.hsm.enabled' => false,
        ]);

        $trust = TrustLevel::evaluate();

        $this->assertLessThanOrEqual(TrustLevel::LEVEL_ADVANCED_DIGITAL, $trust['level']);
        $this->assertFalse(SignatureFeatures::hsmEnabled());
    }

    public function test_compliance_dashboard_requires_admin_role(): void
    {
        $user = User::factory()->client()->create();

        $this->actingAs($user)
            ->get(route('admin.compliance.dashboard'))
            ->assertRedirect(route($user->homeRouteName()));
    }

    public function test_compliance_json_export_for_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.compliance.report.json'))
            ->assertOk()
            ->assertJsonStructure([
                'overall_score',
                'trust_level',
                'categories',
                'standards_supported',
                'standards_missing',
                'recommendations',
            ]);
    }
}
