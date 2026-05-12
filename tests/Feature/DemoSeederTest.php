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

        $demoUser = User::query()->where('email', 'demo@docutrust.com')->first();
        $eNotaryUser = User::query()->where('email', 'enotary@docutrust.com')->first();
        $eNotaryAdminUser = User::query()->where('email', 'notaryadmin@docutrust.com')->first();
        $eNotaryClientUser = User::query()->where('email', 'client@docutrust.com')->first();

        $this->assertNotNull($demoUser);
        $this->assertNotNull($eNotaryUser);
        $this->assertNotNull($eNotaryAdminUser);
        $this->assertNotNull($eNotaryClientUser);
        $this->assertTrue(Hash::check('password', (string) $demoUser->password));
        $this->assertTrue(Hash::check('password', (string) $eNotaryUser->password));
        $this->assertTrue(Hash::check('password', (string) $eNotaryAdminUser->password));
        $this->assertTrue(Hash::check('password', (string) $eNotaryClientUser->password));
        $this->assertSame(8, Document::query()->where('user_id', $demoUser->id)->count());
        $this->assertGreaterThanOrEqual(16, DocumentSigner::query()->count());
        $this->assertSame(3, Template::query()->where('user_id', $demoUser->id)->count());
        $this->assertGreaterThan(0, Document::query()->where('user_id', $demoUser->id)->where('status', DocumentStatus::Pending)->count());
        $this->assertGreaterThan(0, Document::query()->where('user_id', $demoUser->id)->where('status', DocumentStatus::Completed)->count());
        $this->assertDatabaseHas('users', [
            'email' => 'enotary@docutrust.com',
            'role' => 'notary',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'notaryadmin@docutrust.com',
            'role' => 'notary_admin',
            'organization_id' => $eNotaryUser->organization_id,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'client@docutrust.com',
            'role' => 'client',
            'organization_id' => $eNotaryUser->organization_id,
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
