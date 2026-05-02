<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Organization;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt as LivewireVolt;
use Tests\TestCase;

class DocumentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_documents(): void
    {
        $this->get(route('documents.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_documents_index(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['title' => 'Lease agreement']);

        $this->actingAs($user)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertSee('Lease agreement');
    }

    public function test_user_cannot_view_another_users_document(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $document = Document::factory()->for($owner)->create();

        $this->actingAs($other)
            ->get(route('documents.show', $document))
            ->assertForbidden();
    }

    public function test_org_admin_can_view_documents_from_same_organization(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->for($organization)->create();
        $admin = User::factory()->for($organization)->create();
        $document = Document::factory()->for($owner)->create();

        $this->actingAs($admin)
            ->get(route('documents.show', $document))
            ->assertOk();
    }

    public function test_user_can_upload_a_pdf_document(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('contract.pdf', 120, 'application/pdf');

        $this->actingAs($user);

        LivewireVolt::test('documents.create')
            ->set('title', 'Service contract')
            ->set('file', $file)
            ->call('save')
            ->assertHasNoErrors();

        $document = Document::query()->first();
        $this->assertNotNull($document);
        $this->assertSame('Service contract', $document->title);
        $this->assertSame($user->id, $document->user_id);
        $this->assertSame(DocumentStatus::Draft, $document->status);

        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_dashboard_shows_document_counts(): void
    {
        $user = User::factory()->create();
        Document::factory()->count(2)->for($user)->create(['status' => DocumentStatus::Draft]);
        Document::factory()->for($user)->create(['status' => DocumentStatus::Pending]);
        Document::factory()->for($user)->create(['status' => DocumentStatus::Completed]);

        $this->actingAs($user);

        LivewireVolt::test('pages.dashboard')
            ->assertSuccessful()
            ->assertSee('Completion health')
            ->assertSee('4')
            ->assertSee('1');
    }

    public function test_user_can_upload_a_document_with_tags(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $tag = Tag::factory()->for($user)->create(['name' => 'Legal']);
        $file = UploadedFile::fake()->create('contract.pdf', 120, 'application/pdf');

        $this->actingAs($user);

        LivewireVolt::test('documents.create')
            ->set('title', 'Tagged contract')
            ->set('tagIds', [$tag->id])
            ->set('file', $file)
            ->call('save')
            ->assertHasNoErrors();

        $document = Document::query()->where('title', 'Tagged contract')->first();
        $this->assertNotNull($document);
        $this->assertTrue($document->tags()->whereKey($tag->id)->exists());
    }

    public function test_documents_index_searches_by_filename_and_tag_name(): void
    {
        $user = User::factory()->create();
        $tagHr = Tag::factory()->for($user)->create(['name' => 'HR']);

        $alpha = Document::factory()->for($user)->create([
            'title' => 'Alpha Agreement',
            'file_path' => 'documents/alpha-contract.pdf',
            'status' => DocumentStatus::Draft,
        ]);
        $alpha->tags()->attach($tagHr);

        Document::factory()->for($user)->create([
            'title' => 'Beta Policy',
            'file_path' => 'documents/beta-policy.pdf',
            'status' => DocumentStatus::Draft,
        ]);

        $this->actingAs($user);

        LivewireVolt::test('documents.index')
            ->set('search', 'alpha-contract.pdf')
            ->assertSee('Alpha Agreement')
            ->assertDontSee('Beta Policy');

        LivewireVolt::test('documents.index')
            ->set('search', 'HR')
            ->assertSee('Alpha Agreement')
            ->assertDontSee('Beta Policy');
    }

    public function test_documents_index_filters_by_tag_status_and_date(): void
    {
        $user = User::factory()->create();
        $tagLegal = Tag::factory()->for($user)->create(['name' => 'Legal']);
        $tagOps = Tag::factory()->for($user)->create(['name' => 'Ops']);

        $matching = Document::factory()->for($user)->create([
            'title' => 'Legal Pending Today',
            'status' => DocumentStatus::Pending,
            'created_at' => now()->subDay(),
        ]);
        $matching->tags()->attach($tagLegal);

        $wrongStatus = Document::factory()->for($user)->create([
            'title' => 'Legal Draft Today',
            'status' => DocumentStatus::Draft,
            'created_at' => now()->subDay(),
        ]);
        $wrongStatus->tags()->attach($tagLegal);

        $wrongTag = Document::factory()->for($user)->create([
            'title' => 'Ops Pending Today',
            'status' => DocumentStatus::Pending,
            'created_at' => now()->subDay(),
        ]);
        $wrongTag->tags()->attach($tagOps);

        $tooOld = Document::factory()->for($user)->create([
            'title' => 'Legal Pending Old',
            'status' => DocumentStatus::Pending,
            'created_at' => now()->subMonths(2),
        ]);
        $tooOld->tags()->attach($tagLegal);

        $this->actingAs($user);

        LivewireVolt::test('documents.index')
            ->set('tagFilter', (string) $tagLegal->id)
            ->set('statusFilter', DocumentStatus::Pending->value)
            ->set('dateFrom', now()->subWeek()->toDateString())
            ->set('dateTo', now()->toDateString())
            ->assertSee('Legal Pending Today')
            ->assertDontSee('Legal Draft Today')
            ->assertDontSee('Ops Pending Today')
            ->assertDontSee('Legal Pending Old');
    }
}
