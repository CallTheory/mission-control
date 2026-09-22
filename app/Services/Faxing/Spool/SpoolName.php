<?php

declare(strict_types=1);

namespace App\Services\Faxing\Spool;

use InvalidArgumentException;

/**
 * Validates a spool filename before it reaches any driver.
 *
 * Filenames arrive from the browser (the delete buttons) and from `.fs` file contents, so
 * they are never trusted. This is the one place the rules live, because the local driver's
 * realpath containment check has no remote equivalent — over SMB there is nothing to
 * resolve against, so the name itself has to be provably safe.
 */
class SpoolName
{
    /**
     * Never operate on these: repository scaffolding, not fax traffic.
     *
     * @var array<int, string>
     */
    public const IGNORED = ['.', '..', '.gitignore'];

    /**
     * Windows device names, which resolve to a device rather than a file however they are
     * spelled and whatever extension is appended.
     */
    private const RESERVED = '/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(\..*)?$/i';

    /**
     * Reduce a caller-supplied name to a safe leaf, or throw.
     */
    public static function leaf(string $filename): string
    {
        $trimmed = trim($filename);

        // Refuse anything carrying a path separator outright rather than quietly reducing
        // it to a basename. A spool filename never legitimately contains one, and
        // silently turning a delete of "../../etc/passwd" into a delete of "passwd"
        // inside the folder is a surprising thing for an audited operation to do.
        if (str_contains($trimmed, '/') || str_contains($trimmed, '\\')) {
            throw new InvalidArgumentException("Refusing a filename containing a path separator [{$filename}].");
        }

        $name = basename($trimmed);

        if ($name === '' || in_array($name, self::IGNORED, true) || str_starts_with($name, '.')) {
            throw new InvalidArgumentException('Refusing to operate on that filename.');
        }

        if (strlen($name) > 255) {
            throw new InvalidArgumentException("Filename is too long [{$filename}].");
        }

        // Excludes '/' and '\', so a validated name cannot escape its folder on either
        // driver, and excludes the characters SMB itself rejects.
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $name)) {
            throw new InvalidArgumentException("Unexpected characters in filename [{$filename}].");
        }

        // Windows silently strips a trailing dot, so "IS20.fs." resolves to IS20.fs on
        // the server — a name could be validated as one file and acted on as another. The
        // pattern above permits a trailing dot, so this is a real gap rather than a
        // theoretical one. (A trailing space is already removed by the trim above.)
        if (str_ends_with($name, '.')) {
            throw new InvalidArgumentException("Refusing a filename with a trailing dot [{$filename}].");
        }

        if (preg_match(self::RESERVED, $name)) {
            throw new InvalidArgumentException("Refusing a reserved device name [{$filename}].");
        }

        return $name;
    }

    /**
     * Whether a listed name should be shown or acted on at all.
     */
    public static function isIgnored(string $name): bool
    {
        return in_array($name, self::IGNORED, true);
    }
}
