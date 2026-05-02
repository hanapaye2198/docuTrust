<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt as LivewireVolt;
use Tests\TestCase;

class ContactManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_contacts(): void
    {
        $this->get(route('contacts.index'))
            ->assertRedirect();
    }

    public function test_authenticated_user_can_create_contact(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        LivewireVolt::test('contacts.index')
            ->call('openCreateModal')
            ->set('name', 'Jane Doe')
            ->set('email', 'jane@example.com')
            ->call('saveContact')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('contacts', [
            'user_id' => $user->id,
            'email' => 'jane@example.com',
            'name' => 'Jane Doe',
        ]);
    }

    public function test_search_filters_contacts_by_name_or_email(): void
    {
        $user = User::factory()->create();
        Contact::factory()->for($user)->create([
            'name' => 'Alice Wonder',
            'email' => 'alice@example.com',
        ]);
        Contact::factory()->for($user)->create([
            'name' => 'Bob Builder',
            'email' => 'bob@example.com',
        ]);

        $this->actingAs($user);

        LivewireVolt::test('contacts.index')
            ->set('searchInput', 'alice')
            ->call('applySearch')
            ->assertSee('Alice Wonder')
            ->assertDontSee('Bob Builder');
    }

    public function test_user_can_edit_contact(): void
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->for($user)->create([
            'name' => 'Original',
            'email' => 'orig@example.com',
        ]);

        $this->actingAs($user);

        LivewireVolt::test('contacts.index')
            ->call('openEditModal', $contact->id)
            ->set('name', 'Updated Name')
            ->call('saveContact')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'name' => 'Updated Name',
            'email' => 'orig@example.com',
        ]);
    }

    public function test_user_can_delete_contact(): void
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->for($user)->create();

        $this->actingAs($user);

        LivewireVolt::test('contacts.index')
            ->call('deleteContact', $contact->id);

        $this->assertDatabaseMissing('contacts', [
            'id' => $contact->id,
        ]);
    }
}
