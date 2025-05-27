<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;
use App\Providers\JetstreamServiceProvider;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->register(JetstreamServiceProvider::class)->boot();
    }

    public function test_registration_screen_can_be_rendered_when_no_users_exist()
    {
        $response = $this->get('/register');
        $response->assertStatus(200);
    }

    public function test_registration_screen_returns_404_when_users_exist()
    {
        // Create a user and ensure it's in the database
        User::factory()->create();

        // Force a fresh boot of the JetstreamServiceProvider
        $this->app->register(JetstreamServiceProvider::class)->boot();

        $response = $this->get('/register');
        $response->assertStatus(404);
    }

    public function test_new_users_can_register_when_no_users_exist()
    {
        $response = $this->post('/register', [
            'name' => 'Admin User',
            'email' => 'admin@yourdomain.tld',
            'password' => '0lq^V^g3CFk^',
            'password_confirmation' => '0lq^V^g3CFk^',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_new_users_cannot_register_when_users_exist()
    {
        // Create a user and ensure it's in the database
        User::factory()->create();

        // Force a fresh boot of the JetstreamServiceProvider
        $this->app->register(JetstreamServiceProvider::class)->boot();

        $response = $this->post('/register', [
            'name' => 'Admin User',
            'email' => 'admin@yourdomain.tld',
            'password' => '0lq^V^g3CFk^',
            'password_confirmation' => '0lq^V^g3CFk^',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        $response->assertStatus(404);
        $this->assertGuest();
    }
}
