<?php

namespace Tests\Feature;

use App\Support\MarketingFeatures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_page_links_to_each_feature_detail_page(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();

        foreach (MarketingFeatures::all() as $feature) {
            $response->assertSee(route('features.show', $feature['slug']), false);
            $response->assertSee($feature['title'], false);
        }
    }

    public function test_each_feature_detail_page_renders(): void
    {
        foreach (MarketingFeatures::all() as $feature) {
            $this->get(route('features.show', $feature['slug']))
                ->assertOk()
                ->assertSee($feature['title'], false)
                ->assertSee($feature['description'], false)
                ->assertSee($feature['highlights'][0], false)
                ->assertSee($feature['use_cases'][0], false)
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
}
