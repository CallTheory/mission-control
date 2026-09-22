<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Faxing\Spool;

use App\Services\Faxing\Spool\SpoolName;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Filenames reach us from the browser and from `.fs` file contents. The local driver can
 * still confirm containment with realpath, but a remote share has no equivalent, so the
 * name itself has to be provably safe before any driver sees it.
 */
class SpoolNameTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function rejected(): array
    {
        return [
            'traversal' => ['../../etc/passwd'],
            'forward slash' => ['sub/IS20.fs'],
            'backslash' => ['sub\\IS20.fs'],
            'empty' => [''],
            'dot' => ['.'],
            'dot dot' => ['..'],
            'gitignore' => ['.gitignore'],
            'hidden' => ['.secret'],
            // Windows silently strips this, so the name validated is not the file acted on.
            'trailing dot' => ['IS20.fs.'],
            // Resolve to a device rather than a file, whatever extension is appended.
            'reserved device' => ['CON'],
            'reserved device with extension' => ['NUL.fs'],
            'reserved com port' => ['COM1.cap'],
            'wildcard' => ['IS2*.fs'],
            'pipe' => ['IS20|fs'],
            'colon' => ['IS20:fs'],
            'null byte' => ["IS20\0.fs"],
        ];
    }

    #[DataProvider('rejected')]
    public function test_it_refuses_an_unsafe_name(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        SpoolName::leaf($name);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function accepted(): array
    {
        return [
            'fs file' => ['IS20.fs', 'IS20.fs'],
            'cap file' => ['IS20.cap', 'IS20.cap'],
            'underscores and dashes' => ['IS_20-b.cap', 'IS_20-b.cap'],
            'surrounding whitespace is trimmed' => ['  IS20.fs  ', 'IS20.fs'],
            // A trailing space would alias to the same file on Windows; trimming makes
            // the name we validate and the name we act on identical.
            'trailing space is trimmed' => ['IS20.fs ', 'IS20.fs'],
        ];
    }

    #[DataProvider('accepted')]
    public function test_it_accepts_a_real_spool_name(string $input, string $expected): void
    {
        $this->assertSame($expected, SpoolName::leaf($input));
    }

    public function test_it_refuses_a_name_longer_than_a_filesystem_allows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SpoolName::leaf(str_repeat('a', 256).'.fs');
    }
}
