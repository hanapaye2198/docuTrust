<?php

namespace Tests\Feature;

use App\Enums\NotaryRequestStatus;
use App\Models\Contact;
use App\Models\NotaryClientNote;
use App\Models\NotaryRequest;
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
        /** @var User $user */
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
        /** @var User $user */
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
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Contact $contact */
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
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Contact $contact */
        $contact = Contact::factory()->for($user)->create();

        $this->actingAs($user);

        LivewireVolt::test('contacts.index')
            ->call('deleteContact', $contact->id);

        $this->assertDatabaseMissing('contacts', [
            'id' => $contact->id,
        ]);
    }

    public function test_notary_clients_page_shows_clients_for_assigned_requests(): void
    {
        /** @var User $notary */
        $notary = User::factory()->notary()->create();
        /** @var User $otherNotary */
        $otherNotary = User::factory()->notary()->create();
        /** @var User $client */
        $client = User::factory()->enotarySigner()->create([
            'first_name' => 'Alice',
            'last_name' => 'Client',
            'email' => 'alice@example.com',
        ]);
        /** @var User $otherClient */
        $otherClient = User::factory()->enotarySigner()->create([
            'first_name' => 'Bob',
            'last_name' => 'Client',
            'email' => 'bob@example.com',
        ]);

        NotaryRequest::factory()->create([
            'user_id' => $client->id,
            'notary_user_id' => $notary->id,
            'title' => 'Affidavit Package',
            'status' => NotaryRequestStatus::Submitted,
            'updated_at' => now(),
        ]);
        NotaryRequest::factory()->create([
            'user_id' => $client->id,
            'notary_user_id' => $notary->id,
            'title' => 'Completed Deed',
            'status' => NotaryRequestStatus::Notarized,
            'updated_at' => now()->subMinute(),
        ]);
        NotaryRequest::factory()->create([
            'user_id' => $otherClient->id,
            'notary_user_id' => $otherNotary->id,
            'title' => 'Other Attorney Case',
        ]);

        $this->actingAs($notary)
            ->get(route('notary.clients'))
            ->assertOk()
            ->assertSee('Alice Client')
            ->assertSee('Completed Deed')
            ->assertDontSee('Bob Client');
    }

    public function test_notary_clients_search_filters_by_name_or_email(): void
    {
        /** @var User $notary */
        $notary = User::factory()->notary()->create();
        /** @var User $alice */
        $alice = User::factory()->enotarySigner()->create([
            'first_name' => 'Alice',
            'last_name' => 'Wonder',
            'email' => 'alice@example.com',
        ]);
        /** @var User $bob */
        $bob = User::factory()->enotarySigner()->create([
            'first_name' => 'Bob',
            'last_name' => 'Builder',
            'email' => 'bob@example.com',
        ]);

        NotaryRequest::factory()->create([
            'user_id' => $alice->id,
            'notary_user_id' => $notary->id,
        ]);
        NotaryRequest::factory()->create([
            'user_id' => $bob->id,
            'notary_user_id' => $notary->id,
        ]);

        $this->actingAs($notary);

        LivewireVolt::test('notary.clients')
            ->set('search', 'alice')
            ->assertSee('Alice Wonder')
            ->assertDontSee('Bob Builder');
    }

    public function test_notary_can_view_client_detail_and_manage_private_notes(): void
    {
        /** @var User $notary */
        $notary = User::factory()->notary()->create();
        /** @var User $client */
        $client = User::factory()->enotarySigner()->create([
            'first_name' => 'Case',
            'last_name' => 'Client',
            'email' => 'case-client@example.com',
            'mobile_number' => '+15551234567',
        ]);
        $notaryRequest = NotaryRequest::factory()->create([
            'user_id' => $client->id,
            'notary_user_id' => $notary->id,
            'title' => 'Special Power of Attorney',
            'status' => NotaryRequestStatus::AttorneySigning,
        ]);

        $this->actingAs($notary)
            ->get(route('notary.client.show', $client))
            ->assertOk()
            ->assertSee('Case Client')
            ->assertSee('Special Power of Attorney')
            ->assertSee($notaryRequest->status->label());

        $this->actingAs($notary);

        LivewireVolt::test('notary.client-detail', ['clientUser' => $client])
            ->set('newNote', 'Prefers morning appointments.')
            ->call('addNote')
            ->assertHasNoErrors()
            ->assertSee('Prefers morning appointments.');

        $note = NotaryClientNote::query()
            ->where('notary_user_id', $notary->id)
            ->where('client_user_id', $client->id)
            ->firstOrFail();

        LivewireVolt::test('notary.client-detail', ['clientUser' => $client])
            ->call('deleteNote', $note->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('notary_client_notes', [
            'id' => $note->id,
        ]);
    }

    public function test_notary_cannot_view_unrelated_client_detail(): void
    {
        /** @var User $notary */
        $notary = User::factory()->notary()->create();
        /** @var User $otherNotary */
        $otherNotary = User::factory()->notary()->create();
        /** @var User $client */
        $client = User::factory()->enotarySigner()->create();

        NotaryRequest::factory()->create([
            'user_id' => $client->id,
            'notary_user_id' => $otherNotary->id,
        ]);

        $this->actingAs($notary)
            ->get(route('notary.client.show', $client))
            ->assertNotFound();
    }
}
