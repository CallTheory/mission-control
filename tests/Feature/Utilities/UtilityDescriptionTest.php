<?php

declare(strict_types=1);

namespace Tests\Feature\Utilities;

use App\Enums\Utility;
use Tests\TestCase;

/**
 * Every utility tile explains itself on hover.
 *
 * The grid used to put the card's own label in its title attribute -- hovering
 * "Board Check" told you "Board Check" -- and because those were hand-written
 * per card, one had drifted: the Better Emails tile was titled "Board Check".
 * Sourcing them from the enum removes the duplication that allowed that.
 */
class UtilityDescriptionTest extends TestCase
{
    public function test_every_utility_has_a_description(): void
    {
        foreach (Utility::cases() as $utility) {
            $this->assertNotSame('', trim($utility->description()), $utility->name.' has no description.');
        }
    }

    public function test_no_description_merely_repeats_its_label(): void
    {
        foreach (Utility::cases() as $utility) {
            $this->assertNotSame(
                strtolower($utility->label()),
                strtolower(trim($utility->description())),
                $utility->name.' describes itself with its own label.'
            );
        }
    }

    public function test_descriptions_are_unique(): void
    {
        // Guards the copy-paste that gave Better Emails the Board Check title.
        $descriptions = array_map(fn (Utility $u): string => $u->description(), Utility::cases());

        $this->assertSame(
            count($descriptions),
            count(array_unique($descriptions)),
            'Two utilities share a description.'
        );
    }

    public function test_descriptions_stay_short(): void
    {
        foreach (Utility::cases() as $utility) {
            $description = $utility->description();

            $this->assertLessThanOrEqual(
                140,
                strlen($description),
                $utility->name.' is too long for a hover title.'
            );

            $this->assertLessThanOrEqual(
                2,
                preg_match_all('/[.!?](\s|$)/', $description),
                $utility->name.' runs to more than two sentences.'
            );
        }
    }

    public function test_the_grid_renders_descriptions_rather_than_labels(): void
    {
        $view = file_get_contents(resource_path('views/utilities.blade.php'));

        $this->assertSame(
            count(Utility::cases()),
            substr_count($view, '->description()'),
            'Every tile should take its title from Utility::description().'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/<a class="group" title="[A-Za-z]/',
            $view,
            'A tile still has a hand-written title attribute.'
        );
    }
}
