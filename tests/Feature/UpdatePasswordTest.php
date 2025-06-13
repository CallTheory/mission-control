<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Jetstream\Http\Livewire\UpdatePasswordForm;
use Livewire\Livewire;
use Tests\TestCase;

class UpdatePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated()
    {
        $this->actingAs($user = User::factory()->create());

        Livewire::test(UpdatePasswordForm::class)
            ->set('state', [
                'current_password' => '0lq^V^g3CFk^',
                'password' => 'aR1Y8*ve8r^t',
                'password_confirmation' => 'aR1Y8*ve8r^t',
            ])
            ->call('updatePassword');

        $this->assertTrue(Hash::check('aR1Y8*ve8r^t', $user->fresh()->password));
    }

    public function test_current_password_must_be_correct()
    {
        $this->actingAs($user = User::factory()->create());

        Livewire::test(UpdatePasswordForm::class)
            ->set('state', [
                'current_password' => '9kp^V^g3CFk^',
                'password' => 'aR1Y8*ve8r^t',
                'password_confirmation' => 'aR1Y8*ve8r^t',
            ])
            ->call('updatePassword')
            ->assertHasErrors(['current_password']);

        $this->assertTrue(Hash::check('0lq^V^g3CFk^', $user->fresh()->password));
    }

    public function test_new_passwords_must_match()
    {
        $this->actingAs($user = User::factory()->create());

        Livewire::test(UpdatePasswordForm::class)
            ->set('state', [
                'current_password' => '0lq^V^g3CFk^',
                'password' => 'aR1Y8*ve8r^t',
                'password_confirmation' => '9kp^V^g3CFk^',
            ])
            ->call('updatePassword')
            ->assertHasErrors(['password']);

        $this->assertTrue(Hash::check('0lq^V^g3CFk^', $user->fresh()->password));
    }
}
