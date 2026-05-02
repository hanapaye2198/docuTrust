<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TemplatePrepareTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_stream_template_pdf(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $path = 'templates/test.pdf';
        Storage::disk('public')->put($path, 'fake-pdf');
        $template = Template::factory()->for($user)->create(['files' => [$path]]);

        $this->get(route('templates.file', $template))->assertRedirect(route('login'));
    }

    public function test_owner_can_stream_template_pdf(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $path = 'templates/test.pdf';
        Storage::disk('public')->put($path, 'fake-pdf');
        $template = Template::factory()->for($user)->create(['files' => [$path]]);

        $this->actingAs($user)
            ->get(route('templates.file', $template))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_owner_gets_not_found_when_template_file_missing(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $template = Template::factory()->for($user)->create(['files' => ['templates/missing.pdf']]);

        $this->actingAs($user)
            ->get(route('templates.file', $template))
            ->assertNotFound();
    }

    public function test_owner_cannot_stream_another_users_template_pdf(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $path = 'templates/test.pdf';
        Storage::disk('public')->put($path, 'fake-pdf');
        $template = Template::factory()->for($owner)->create(['files' => [$path]]);

        $this->actingAs($other)
            ->get(route('templates.file', $template))
            ->assertForbidden();
    }

    public function test_owner_can_visit_prepare_page(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $path = 'templates/test.pdf';
        Storage::disk('public')->put($path, 'fake-pdf');
        $template = Template::factory()->for($user)->create(['files' => [$path]]);

        $this->actingAs($user)
            ->get(route('templates.prepare', $template))
            ->assertOk()
            ->assertSee(__('Prepare template'))
            ->assertSee($template->name, escape: false);
    }
}
