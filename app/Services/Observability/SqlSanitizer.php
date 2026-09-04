<?php

declare(strict_types=1);

namespace App\Services\Observability;

/**
 * Strips literal values out of SQL before it becomes a span attribute.
 *
 * Query bindings are NEVER recorded — they carry caller phone numbers, DOBs and
 * patient names. But `whereRaw()` and `DB::statement()` inline literals
 * directly into the statement text, so the statement itself must be sanitized
 * too. This matters most for the `clientdb` and `intelligent` connections,
 * which hold client PHI.
 */
final class SqlSanitizer
{
    public static function sanitize(string $sql, int $maxLength = 2048): string
    {
        // Single-quoted string literals, handling '' and \' escapes.
        $sql = (string) preg_replace("/'(?:[^']|'')*'/", '?', $sql);
        $sql = (string) preg_replace('/"(?:[^"\\\\]|\\\\.)*"/', '?', $sql);

        // Bare numeric literals (but not identifiers like col1).
        $sql = (string) preg_replace('/(?<![\w.])\d+(?:\.\d+)?(?![\w.])/', '?', $sql);

        // Collapse long IN lists to a single placeholder.
        $sql = (string) preg_replace('/\bIN\s*\(\s*\?(?:\s*,\s*\?)+\s*\)/i', 'IN (?)', $sql);

        $sql = trim((string) preg_replace('/\s+/', ' ', $sql));

        return mb_strlen($sql) > $maxLength ? mb_substr($sql, 0, $maxLength).'…' : $sql;
    }

    /**
     * The leading SQL verb, used for the span name.
     */
    public static function operation(string $sql): string
    {
        return preg_match('/^\s*(\w+)/', $sql, $m) === 1
            ? strtoupper($m[1])
            : 'QUERY';
    }
}
