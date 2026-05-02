<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Use real console output for Artisan commands (e.g. migrate:fresh) so migrations never hit Mockery
     * when the migrator prompts (e.g. missing SQLite file).
     *
     * @var bool
     */
    public $mockConsoleOutput = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }
}
