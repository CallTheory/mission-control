<?php

declare(strict_types=1);

namespace App\Support\Tables;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Adapts an `App\Models\Stats\*` result set to a Filament table's `records()` callback.
 *
 * The Stats layer runs hand-written T-SQL against the Amtelco databases and returns the
 * whole result set as stdClass rows -- there is no ORDER BY, OFFSET or WHERE that Filament
 * could push a sort, page or search into. So searching, sorting and paging all happen here,
 * in PHP, over rows already in memory. That is the same thing the screens did before, except
 * they only did the paging part and offered no sorting or searching at all.
 *
 * Rows are returned as arrays because Filament keys non-Eloquent records by
 * `ArrayRecord::getKeyName()`, which it can only read from an array.
 *
 * The trade-off is deliberate and worth stating: this pulls the full result set into memory.
 * That matches the existing behaviour, and these queries are already date-bounded. A screen
 * whose result set outgrows that wants a paginated stored procedure, not a bigger sort here.
 */
class StatRecords
{
    /**
     * @param  array<int, object|array<string, mixed>>  $rows  raw Stat results
     * @param  array<int, string>  $searchable  keys `$search` is matched against
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public static function paginate(
        array $rows,
        int $page = 1,
        int $perPage = 25,
        ?string $sortColumn = null,
        ?string $sortDirection = null,
        ?string $search = null,
        array $searchable = [],
    ): LengthAwarePaginator {
        $records = self::toArrays($rows);

        if (filled($search) && $searchable !== []) {
            $records = self::search($records, $search, $searchable);
        }

        if (filled($sortColumn)) {
            $records = self::sort($records, $sortColumn, $sortDirection);
        }

        $page = max(1, $page);
        $perPage = max(1, $perPage);

        return new LengthAwarePaginator(
            $records->forPage($page, $perPage)->values()->all(),
            $records->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    /**
     * Stat rows arrive as stdClass; give each one a stable key so Filament can
     * identify a row across requests and so row actions resolve the right record.
     *
     * @param  array<int, object|array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private static function toArrays(array $rows): Collection
    {
        return collect($rows)->values()->map(function ($row, int $index): array {
            $record = is_object($row) ? get_object_vars($row) : $row;
            $record['__key'] ??= (string) $index;

            return $record;
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $records
     * @param  array<int, string>  $searchable
     * @return Collection<int, array<string, mixed>>
     */
    private static function search(Collection $records, string $search, array $searchable): Collection
    {
        $needle = mb_strtolower(trim($search));

        return $records->filter(function (array $record) use ($needle, $searchable): bool {
            foreach ($searchable as $key) {
                $value = $record[$key] ?? null;

                if ($value === null || is_array($value)) {
                    continue;
                }

                if (str_contains(mb_strtolower((string) $value), $needle)) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $records
     * @return Collection<int, array<string, mixed>>
     */
    private static function sort(Collection $records, string $column, ?string $direction): Collection
    {
        $descending = strtolower((string) $direction) === 'desc';

        return $records
            ->sortBy(
                // Numeric columns must compare as numbers: string comparison puts
                // "100" before "9", which is exactly the sort a call-volume column
                // would get wrong.
                function (array $record) use ($column) {
                    $value = $record[$column] ?? null;

                    return is_numeric($value) ? (float) $value : mb_strtolower((string) $value);
                },
                SORT_REGULAR,
                $descending,
            )
            ->values();
    }
}
