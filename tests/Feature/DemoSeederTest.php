<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentSigner;
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

        $this->assertNotNull($demoUser);
        $this->assertTrue(Hash::check('password', (string) $demoUser->password));
        $this->assertSame(8, Document::query()->where('user_id', $demoUser->id)->count());
        $this->assertGreaterThanOrEqual(16, DocumentSigner::query()->count());
        $this->assertSame(3, Template::query()->where('user_id', $demoUser->id)->count());
        $this->assertGreaterThan(0, Document::query()->where('user_id', $demoUser->id)->where('status', DocumentStatus::Pending)->count());
        $this->assertGreaterThan(0, Document::query()->where('user_id', $demoUser->id)->where('status', DocumentStatus::Completed)->count());

        $this->assertDatabaseHas('templates', ['name' => 'CV Template']);
        $this->assertDatabaseHas('templates', ['name' => 'Contract Template']);
        $this->assertDatabaseHas('templates', ['name' => 'Agreement Template']);
    }
}
