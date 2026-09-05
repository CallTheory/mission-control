<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Tables\StatRecords;
use Tests\TestCase;

/**
 * The Stats screens run T-SQL against Amtelco that no test environment has, so the
 * component wiring around them is thin by design and the logic that can actually be
 * wrong -- searching, sorting and paging a raw result set -- lives here and is tested
 * directly.
 */
class StatRecordsTest extends TestCase
{
    /** @return array<int, object> */
    private function rows(): array
    {
        return [
            (object) ['Name' => 'Zoe Yang', 'Calls' => 9, 'Initials' => 'ZY'],
            (object) ['Name' => 'aaron beck', 'Calls' => 100, 'Initials' => 'AB'],
            (object) ['Name' => 'Dana Whitfield', 'Calls' => 42, 'Initials' => 'DW'],
        ];
    }

    public function test_it_converts_std_class_rows_to_keyed_arrays(): void
    {
        $page = StatRecords::paginate($this->rows());

        foreach ($page->items() as $record) {
            $this->assertIsArray($record);
            $this->assertArrayHasKey('__key', $record);
        }

        $this->assertSame(['0', '1', '2'], array_column($page->items(), '__key'));
    }

    public function test_it_preserves_an_existing_key(): void
    {
        $page = StatRecords::paginate([
            ['agtId' => 7, '__key' => 'agent-7'],
        ]);

        $this->assertSame('agent-7', $page->items()[0]['__key']);
    }

    public function test_it_sorts_numeric_columns_numerically(): void
    {
        // The bug this guards: string comparison orders "100" before "9".
        $page = StatRecords::paginate($this->rows(), sortColumn: 'Calls', sortDirection: 'asc');

        $this->assertSame([9, 42, 100], array_column($page->items(), 'Calls'));
    }

    public function test_it_sorts_numeric_columns_descending(): void
    {
        $page = StatRecords::paginate($this->rows(), sortColumn: 'Calls', sortDirection: 'desc');

        $this->assertSame([100, 42, 9], array_column($page->items(), 'Calls'));
    }

    public function test_it_sorts_text_case_insensitively(): void
    {
        $page = StatRecords::paginate($this->rows(), sortColumn: 'Name', sortDirection: 'asc');

        $this->assertSame(['aaron beck', 'Dana Whitfield', 'Zoe Yang'], array_column($page->items(), 'Name'));
    }

    public function test_it_searches_only_the_named_columns(): void
    {
        $page = StatRecords::paginate($this->rows(), search: 'dana', searchable: ['Name']);

        $this->assertCount(1, $page->items());
        $this->assertSame('Dana Whitfield', $page->items()[0]['Name']);
    }

    public function test_search_is_case_insensitive_and_matches_substrings(): void
    {
        $page = StatRecords::paginate($this->rows(), search: 'WHITFIELD', searchable: ['Name']);

        $this->assertCount(1, $page->items());
    }

    public function test_search_ignores_columns_that_were_not_listed(): void
    {
        $page = StatRecords::paginate($this->rows(), search: 'ZY', searchable: ['Name']);

        $this->assertCount(0, $page->items());
    }

    public function test_search_without_searchable_columns_is_a_no_op(): void
    {
        $page = StatRecords::paginate($this->rows(), search: 'nothing matches this');

        $this->assertCount(3, $page->items());
    }

    public function test_it_pages_and_reports_the_full_total(): void
    {
        $page = StatRecords::paginate($this->rows(), page: 2, perPage: 2);

        $this->assertSame(3, $page->total());
        $this->assertSame(2, $page->currentPage());
        $this->assertCount(1, $page->items());
    }

    public function test_search_narrows_the_total_before_paging(): void
    {
        $page = StatRecords::paginate($this->rows(), perPage: 25, search: 'a', searchable: ['Name']);

        // "Zoe Yang", "aaron beck" and "Dana Whitfield" all contain an "a".
        $this->assertSame(3, $page->total());

        $page = StatRecords::paginate($this->rows(), perPage: 25, search: 'beck', searchable: ['Name']);
        $this->assertSame(1, $page->total());
    }

    public function test_it_handles_an_empty_result_set(): void
    {
        $page = StatRecords::paginate([], sortColumn: 'Calls', search: 'x', searchable: ['Name']);

        $this->assertSame(0, $page->total());
        $this->assertSame([], $page->items());
    }

    public function test_it_clamps_nonsensical_paging_arguments(): void
    {
        $page = StatRecords::paginate($this->rows(), page: 0, perPage: 0);

        $this->assertSame(1, $page->currentPage());
        $this->assertSame(1, $page->perPage());
    }

    public function test_missing_sort_column_does_not_throw(): void
    {
        $page = StatRecords::paginate($this->rows(), sortColumn: 'NotAColumn');

        $this->assertCount(3, $page->items());
    }
}
