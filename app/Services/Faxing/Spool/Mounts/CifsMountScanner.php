<?php

declare(strict_types=1);

namespace App\Services\Faxing\Spool\Mounts;

/**
 * Finds the kernel CIFS mounts that the SMB driver is meant to replace.
 *
 * Reads /proc/mounts rather than shelling out to findmnt: it is the same data, and — more
 * importantly — procfs never touches the filesystem, so this is safe to run even when
 * every one of those mounts is hung. Anything that stat()ed them would block in
 * uninterruptible sleep, which is the failure we are trying to get the customer away from.
 */
class CifsMountScanner
{
    public function __construct(private readonly string $procMounts = '/proc/mounts') {}

    /**
     * CIFS mounts underneath the given directory.
     *
     * @return array<int, CifsMount>
     */
    public function under(string $directory): array
    {
        $contents = @file_get_contents($this->procMounts);

        if ($contents === false) {
            return [];
        }

        // Only the parent is resolved. realpath() on a hung mount point would block,
        // which is exactly the state we may be scanning for.
        $base = rtrim(realpath($directory) ?: $directory, '/');

        $mounts = [];

        foreach (explode("\n", $contents) as $line) {
            $mount = $this->parse($line);

            if ($mount === null) {
                continue;
            }

            if ($mount->mountPoint === $base || str_starts_with($mount->mountPoint, $base.'/')) {
                $mounts[] = $mount;
            }
        }

        return $mounts;
    }

    public function parse(string $line): ?CifsMount
    {
        $fields = preg_split('/\s+/', trim($line));

        if ($fields === false || count($fields) < 4 || ! in_array($fields[2], ['cifs', 'smb3'], true)) {
            return null;
        }

        [$device, $mountPoint] = [$this->unescape($fields[0]), $this->unescape($fields[1])];

        // //host/share — the hostname as it was typed, which addr= does not preserve.
        if (! preg_match('#^//([^/]+)/(.+)$#', $device, $matches)) {
            return null;
        }

        $options = $this->options($fields[3]);

        return new CifsMount(
            mountPoint: rtrim($mountPoint, '/'),
            host: $matches[1],
            share: rtrim($matches[2], '/'),
            address: $options['addr'] ?? null,
            username: $options['username'] ?? $options['user'] ?? null,
            domain: $options['domain'] ?? null,
            version: $options['vers'] ?? null,
            soft: array_key_exists('soft', $options),
            kerberos: str_starts_with($options['sec'] ?? '', 'krb5'),
        );
    }

    /**
     * @return array<string, string>
     */
    private function options(string $options): array
    {
        $parsed = [];

        foreach (explode(',', $options) as $option) {
            [$key, $value] = array_pad(explode('=', $option, 2), 2, '');
            $parsed[$key] = $value;
        }

        return $parsed;
    }

    /**
     * The device and mount point fields are octal-escaped for whitespace and backslashes.
     */
    private function unescape(string $value): string
    {
        return str_replace(['\040', '\011', '\012', '\134'], [' ', "\t", "\n", '\\'], $value);
    }
}
