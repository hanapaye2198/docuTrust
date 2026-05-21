<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\NotaryCredential;
use App\Models\Template;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_populates_demo_ready_content(): void
    {
        $this->seed(DatabaseSeeder::class);

        $demoAdmin = User::query()->where('email', 'adminsigner@docutrust.tech')->first();
        $documentSigner = User::query()->where('email', 'docusigner1@docutrust.tech')->first();
        $eNotaryUser = User::query()->where('email', 'notaryatty@docutrust.tech')->first();
        $eNotaryAdminUser = User::query()->where('email', 'notaryadmin@docutrust.tech')->first();
        $eNotaryClientUser = User::query()->where('email', 'client@docutrust.tech')->first();
        $eNotarySigner = User::query()->where('email', 'enotarysigner1@docutrust.tech')->first();

        $this->assertNotNull($demoAdmin);
        $this->assertNotNull($documentSigner);
        $this->assertNotNull($eNotaryUser);
        $this->assertNotNull($eNotaryAdminUser);
        $this->assertNotNull($eNotaryClientUser);
        $this->assertNotNull($eNotarySigner);
        $this->assertTrue(Hash::check('password', (string) $documentSigner->password));
        $this->assertTrue(Hash::check('password', (string) $eNotaryUser->password));
        $this->assertTrue(Hash::check('password', (string) $eNotaryAdminUser->password));
        $this->assertTrue(Hash::check('password', (string) $eNotaryClientUser->password));
        $this->assertSame('signing', $documentSigner->workspace?->value);
        $this->assertSame('enotary', $eNotarySigner->workspace?->value);
        $this->assertSame(8, Document::query()->where('user_id', $documentSigner->id)->count());
        $this->assertGreaterThanOrEqual(16, DocumentSigner::query()->count());
        $this->assertSame(3, Template::query()->where('user_id', $documentSigner->id)->count());
        $this->assertGreaterThan(0, Document::query()->where('user_id', $documentSigner->id)->where('status', DocumentStatus::Pending)->count());
        $this->assertGreaterThan(0, Document::query()->where('user_id', $documentSigner->id)->where('status', DocumentStatus::Completed)->count());
        $this->assertDatabaseHas('users', [
            'email' => 'notaryatty@docutrust.tech',
            'role' => 'notary',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'notaryadmin@docutrust.tech',
            'role' => 'notary_admin',
            'organization_id' => $eNotaryUser->organization_id,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'client@docutrust.tech',
            'role' => 'client',
            'workspace' => 'enotary',
            'organization_id' => $eNotaryUser->organization_id,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'enotarysigner1@docutrust.tech',
            'workspace' => 'enotary',
        ]);
        $this->assertDatabaseHas('notary_credentials', [
            'user_id' => $eNotaryUser->id,
            'commission_number' => 'CN-2026-0001',
            'status' => 'active',
        ]);
        $this->assertSame(1, NotaryCredential::query()->where('user_id', $eNotaryUser->id)->count());

        $this->assertDatabaseHas('templates', ['name' => 'CV Template']);
        $this->assertDatabaseHas('templates', ['name' => 'Contract Template']);
        $this->assertDatabaseHas('templates', ['name' => 'Agreement Template']);
    }
}
