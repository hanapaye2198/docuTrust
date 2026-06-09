<?php

namespace Tests\Feature;

use App\Enums\SignatureFieldType;
use App\Models\Template;
use App\Models\TemplateSigner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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

    public function test_owner_cannot_save_removed_toggle_template_fields(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $path = 'templates/test.pdf';
        Storage::disk('public')->put($path, 'fake-pdf');
        $template = Template::factory()->for($user)->create(['files' => [$path]]);
        $signer = TemplateSigner::factory()->for($template)->create([
            'role_name' => 'Client',
        ]);

        $response = $this->actingAs($user)
            ->from(route('templates.prepare', $template))
            ->post(route('templates.fields.store', $template), [
                'fields' => [
                    [
                        'role_name' => $signer->role_name,
                        'type' => SignatureFieldType::Radio->value,
                        'position_data' => [
                            'x' => 0.15,
                            'y' => 0.25,
                            'width' => 0.1,
                            'height' => 0.05,
                        ],
                    ],
                ],
            ]);

        $this->assertInstanceOf(ValidationException::class, $response->exception);
        $this->assertArrayHasKey('fields.0.type', $response->exception->errors());
    }
}
