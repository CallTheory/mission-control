<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class RedirectHomeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_redirects_to_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/dashboard');
    }
} 