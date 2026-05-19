<?php

namespace Tests\Unit\Ekyc;

use App\Models\User;
use App\Services\Ekyc\EkycNameMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EkycNameMatcherTest extends TestCase
{
    use RefreshDatabase;

    private EkycNameMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->matcher = new EkycNameMatcher;
    }

    public function test_matches_when_ocr_contains_full_name(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Juan',
            'middle_name' => 'Dela',
            'last_name' => 'Cruz',
            'name' => 'Juan Dela Cruz',
        ]);

        $result = $this->matcher->match($user, "REPUBLIC OF THE PHILIPPINES\nJUAN DELA CRUZ\nMANILA");

        $this->assertTrue($result->matched);
    }

    public function test_rejects_when_last_name_missing_from_ocr(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Juan',
            'last_name' => 'Cruz',
            'name' => 'Juan Cruz',
        ]);

        $result = $this->matcher->match($user, 'JUAN SANTOS PASSPORT');

        $this->assertFalse($result->matched);
    }

    public function test_middle_name_initial_on_id_is_accepted(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Maria',
            'middle_name' => 'Clara',
            'last_name' => 'Santos',
            'name' => 'Maria Clara Santos',
        ]);

        $result = $this->matcher->match($user, 'MARIA C SANTOS NATIONAL ID');

        $this->assertTrue($result->matched);
    }

    public function test_skips_middle_name_check_when_user_has_no_middle_name(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Ana',
            'middle_name' => null,
            'last_name' => 'Reyes',
            'name' => 'Ana Reyes',
        ]);

        $result = $this->matcher->match($user, 'ANA MARIA REYES ID');

        $this->assertTrue($result->matched);
    }
}
