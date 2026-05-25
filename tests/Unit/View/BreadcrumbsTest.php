<?php

namespace Tests\Unit\View;

use App\Models\User;
use App\View\Breadcrumbs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BreadcrumbsTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_label_returns_last_breadcrumb_item(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('documents.index'));

        $this->assertSame(__('Documents'), Breadcrumbs::currentLabel());
    }

    public function test_current_label_returns_null_when_no_breadcrumbs(): void
    {
        $this->get('/login');

        $this->assertNull(Breadcrumbs::currentLabel());
    }
}
