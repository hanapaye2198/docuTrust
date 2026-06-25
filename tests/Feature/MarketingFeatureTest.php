<?php

namespace Tests\Feature;

use App\Support\MarketingFeatures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class MarketingFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_page_renders_expanded_feature_section(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSeeLivewire('landing.features-section');
    }

    public function test_landing_feature_section_renders_categories_cards_and_default_detail(): void
    {
        Volt::test('landing.features-section')
            ->assertSet('activeFeature', 'signing')
            ->assertSee('Platform capabilities')
            ->assertSee('class="features-section"', false)
            ->assertSee('class="container"', false)
            ->assertSee('features-expanded-grid', false)
            ->assertSee('feature-card active', false)
            ->assertSee('Document workflows')
            ->assertSee('Attorney &amp; notary portal', false)
            ->assertSee('Compliance &amp; security', false)
            ->assertSee('Infrastructure &amp; PKI', false)
            ->assertSee('Document signing workspace')
            ->assertSee('Templates &amp; contacts', false)
            ->assertSee('e-Notary workflows')
            ->assertSee('Multi-signer workflow')
            ->assertSee('Notary / attorney portal')
            ->assertSee('Trust profile &amp; onboarding', false)
            ->assertSee('Real-time tracking')
            ->assertSee('Audit trail logs')
            ->assertSee('Verification &amp; compliance', false)
            ->assertSee('Admin &amp; billing', false)
            ->assertSee('Payments &amp; billing', false)
            ->assertSee('Blockchain anchoring')
            ->assertSee('PKI / remote signing')
            ->assertSee('Smart document management')
            ->assertSee('Core')
            ->assertSee('Infra')
            ->assertSee('SignDocumentController.php')
            ->assertSee('href="/features/secure-digital-signing"', false)
            ->assertSee('href="/features/pki"', false)
            ->assertSee('View full specification');
    }

    public function test_landing_feature_section_updates_detail_panel_when_card_is_selected(): void
    {
        Volt::test('landing.features-section')
            ->call('selectFeature', 'pki')
            ->assertSet('activeFeature', 'pki')
            ->assertSee('Full CSC API v2 pipeline')
            ->assertSee('routes/scep.php')
            ->call('selectFeature', 'not-a-feature')
            ->assertSet('activeFeature', 'pki');
    }

    public function test_each_feature_detail_page_renders(): void
    {
        foreach (MarketingFeatures::all() as $feature) {
            $this->get(route('features.show', $feature['slug']))
                ->assertOk()
                ->assertSee($feature['title'])
                ->assertSee($feature['description'])
                ->assertSee($feature['highlights'][0])
                ->assertSee($feature['use_cases'][0])
                ->assertSee('feature-hero-illustration', false)
                ->assertSee('id="docutrustThemeToggle"', false)
                ->assertSee('id="mobileNavToggle"', false)
                ->assertSee(route('home').'#features', false)
                ->assertSee(route('home').'#ai', false);
        }
    }

    public function test_unknown_feature_returns_not_found(): void
    {
        $this->get('/features/not-a-real-feature')->assertNotFound();
    }

    public function test_legacy_short_feature_slugs_return_not_found(): void
    {
        $this->assertCount(14, MarketingFeatures::slugs());

        foreach (['signing', 'multi-signer', 'tracking', 'audit-logs', 'blockchain', 'document-management'] as $slug) {
            $this->get("/features/{$slug}")->assertNotFound();
        }
    }
}
