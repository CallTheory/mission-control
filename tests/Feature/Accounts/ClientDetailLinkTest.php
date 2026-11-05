<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Http\Controllers\Accounts\ClientDetailController;
use App\Livewire\Accounts\Clients;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The accounts list linked to /accounts/client/{n}, which no route serves, so opening
 * any account was a 404.
 */
final class ClientDetailLinkTest extends TestCase
{
    public function test_an_account_links_to_its_detail_page(): void
    {
        $url = Clients::detailUrl(['ClientNumber' => 1001]);

        $this->assertSame(url('/accounts/1001'), $url);

        $route = Route::getRoutes()->match(Request::create($url));

        $this->assertSame(ClientDetailController::class, $route->getActionName());
        $this->assertSame('1001', $route->parameter('client_number'));
    }
}
