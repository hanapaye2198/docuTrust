<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TemplateTagTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_tag_from_sidebar(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('templates.index')
            ->set('creatingTag', true)
            ->set('newTagName', 'HR')
            ->call('saveNewTag')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tags', [
            'user_id' => $user->id,
            'name' => 'HR',
        ]);
    }

    public function test_templates_index_filters_by_selected_tag(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $path = 'templates/x.pdf';
        Storage::disk('public')->put($path, 'x');

        $tagHr = Tag::factory()->for($user)->create(['name' => 'HR']);
        $tagLegal = Tag::factory()->for($user)->create(['name' => 'Legal']);

        $contractA = Template::factory()->for($user)->create(['name' => 'Contract A', 'files' => [$path]]);
        $contractA->tags()->attach($tagHr);

        $contractB = Template::factory()->for($user)->create(['name' => 'Contract B', 'files' => [$path]]);
        $contractB->tags()->attach($tagLegal);

        $this->actingAs($user);

        Volt::test('templates.index')
            ->set('selectedTagId', $tagHr->id)
            ->assertSee('Contract A')
            ->assertDontSee('Contract B');
    }

    public function test_templates_index_combines_search_and_tag(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $path = 'templates/x.pdf';
        Storage::disk('public')->put($path, 'x');

        $tag = Tag::factory()->for($user)->create(['name' => 'HR']);

        $alpha = Template::factory()->for($user)->create(['name' => 'Alpha NDA', 'files' => [$path]]);
        $alpha->tags()->attach($tag);

        $beta = Template::factory()->for($user)->create(['name' => 'Beta lease', 'files' => [$path]]);
        $beta->tags()->attach($tag);

        $this->actingAs($user);

        Volt::test('templates.index')
            ->set('selectedTagId', $tag->id)
            ->set('search', 'Alpha')
            ->assertSee('Alpha NDA')
            ->assertDontSee('Beta lease');
    }
}
