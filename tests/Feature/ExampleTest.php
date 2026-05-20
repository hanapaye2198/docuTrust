<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee(route('login'), false)
            ->assertSee(route('register'), false)
            ->assertSee(route('home'), false)
            ->assertSee(asset('images/docutrust-logo.png'), false)
            ->assertSee(asset('images/CSC logo light theme.png'), false)
            ->assertSee('Cloud Signature Consortium', false)
            ->assertDontSee(asset('images/CSC logo dark theme.png'), false);
    }
}
