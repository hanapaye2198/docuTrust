<?php

namespace Tests\Feature;

use App\Enums\UserWorkspace;
use App\Models\NotaryRequest;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt as LivewireVolt;
use Tests\TestCase;

class WorkspaceAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_signer_demo_accounts_are_seeded(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'docusigner1@docutrust.tech',
            'workspace' => UserWorkspace::Signing->value,
            'role' => 'client',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'docusigner2@docutrust.tech',
            'workspace' => UserWorkspace::Signing->value,
            'role' => 'client',
        ]);
    }

    public function test_enotary_signer_demo_accounts_are_seeded_with_requests(): void
    {
        $this->seed(DatabaseSeeder::class);

        $notary = User::query()->where('email', 'notaryatty@docutrust.tech')->first();
        $signer = User::query()->where('email', 'enotarysigner1@docutrust.tech')->first();

        $this->assertNotNull($notary);
        $this->assertNotNull($signer);
        $this->assertSame(UserWorkspace::Enotary, $signer->workspace);
        $this->assertSame($notary->id, NotaryRequest::query()
            ->where('user_id', $signer->id)
            ->value('notary_user_id'));

        $this->assertDatabaseHas('users', [
            'email' => 'enotarysigner2@docutrust.tech',
            'workspace' => UserWorkspace::Enotary->value,
        ]);
    }

    public function test_document_signer_can_login_via_signer_tab(): void
    {
        $user = User::factory()->signer()->create([
            'email' => 'signer-only@docutrust.test',
        ]);

        LivewireVolt::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'password')
            ->set('mode', 'standard')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('documents.index', absolute: false));
    }

    public function test_document_signer_cannot_login_via_enotary_tab(): void
    {
        $user = User::factory()->signer()->create([
            'email' => 'signer-blocked@docutrust.test',
        ]);

        LivewireVolt::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'password')
            ->set('mode', 'enotary')
            ->call('login')
            ->assertHasErrors('email');
    }

    public function test_enotary_signer_can_login_via_enotary_tab(): void
    {
        $user = User::factory()->enotarySigner()->create([
            'email' => 'enotary-only@docutrust.test',
        ]);

        LivewireVolt::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'password')
            ->set('mode', 'enotary')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('notary-requests.index', absolute: false));
    }

    public function test_enotary_signer_cannot_login_via_signer_tab(): void
    {
        $user = User::factory()->enotarySigner()->create([
            'email' => 'enotary-blocked@docutrust.test',
        ]);

        LivewireVolt::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'password')
            ->set('mode', 'standard')
            ->call('login')
            ->assertHasErrors('email');
    }

    public function test_document_signer_cannot_access_notary_requests_route(): void
    {
        $user = User::factory()->signer()->create();

        $this->actingAs($user)
            ->get(route('notary-requests.index'))
            ->assertRedirect(route('documents.index'));
    }

    public function test_enotary_signer_cannot_access_documents_route(): void
    {
        $user = User::factory()->enotarySigner()->create();

        $this->actingAs($user)
            ->get(route('documents.index'))
            ->assertRedirect(route('notary-requests.index'));
    }
}
