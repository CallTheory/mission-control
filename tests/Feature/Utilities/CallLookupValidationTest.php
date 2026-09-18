<?php

namespace Tests\Feature\Utilities;

use App\Livewire\Utilities\CallLookup;
use Tests\TestCase;

/**
 * CallLookup validates on every render, and Livewire reads a component's `$messages`
 * property as its custom validation messages. A public property by that name shadows
 * that lookup with null, and every render of the page dies inside Livewire's validator
 * on array_merge(null, ...). The call messages therefore live on $callMessages.
 */
class CallLookupValidationTest extends TestCase
{
    public function test_it_does_not_shadow_livewire_reserved_validation_properties(): void
    {
        foreach (['messages', 'rules', 'validationAttributes'] as $reserved) {
            $this->assertFalse(
                property_exists(CallLookup::class, $reserved),
                "CallLookup must not declare \${$reserved}; Livewire reserves it for validation."
            );
        }
    }

    public function test_livewire_can_resolve_validation_messages(): void
    {
        $this->assertIsArray(invade(new CallLookup)->getMessages());
    }

    public function test_call_messages_are_exposed_under_their_own_name(): void
    {
        $this->assertTrue(property_exists(CallLookup::class, 'callMessages'));
    }
}
