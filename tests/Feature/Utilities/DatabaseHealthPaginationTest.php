<?php

namespace Tests\Feature\Utilities;

use App\Livewire\Utilities\DatabaseHealth;
use Livewire\WithPagination;
use Tests\TestCase;

/**
 * database-health.blade.php builds its Intelligent database listing with a
 * LengthAwarePaginator seeded from $this->getPage(), and the partial renders
 * $results->links(). Both come from WithPagination. Dropping the trait because
 * the PHP never calls paginate() broke the page at render time in production.
 */
class DatabaseHealthPaginationTest extends TestCase
{
    public function test_the_component_provides_the_pagination_api_its_view_calls(): void
    {
        $this->assertContains(
            WithPagination::class,
            class_uses_recursive(DatabaseHealth::class),
            'DatabaseHealth must use WithPagination; its Blade view calls getPage().'
        );

        foreach (['getPage', 'setPage', 'nextPage', 'previousPage'] as $method) {
            $this->assertTrue(
                method_exists(DatabaseHealth::class, $method),
                "DatabaseHealth::{$method}() is required by its paginated view."
            );
        }
    }

    public function test_get_page_defaults_to_the_first_page(): void
    {
        $this->assertSame(1, invade(new DatabaseHealth)->getPage());
    }
}
