<?php

declare(strict_types=1);

namespace App\Services\Faxing\Spool;

use App\Services\Faxing\Spool\Exceptions\SpoolUnavailable;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * A spool source that is a directory on this host.
 *
 * That covers both the Samba share Amtelco writes into — where Mission Control is the SMB
 * *server*, so there is nothing to connect out to and an SMB client could never replace
 * this — and a kernel CIFS mount of a remote share.
 *
 * The kernel-mount case carries a hazard nothing in PHP can defend against: on a stale
 * mount, scandir() and even is_dir() block in uninterruptible sleep, where no signal is
 * delivered and no timeout fires. Queueing the work bounds the blast radius to one worker
 * but cannot cure it. Mounts must be soft; the real fix is moving such a source to the
 * SMB driver, whose timeouts are enforceable.
 */
class LocalSpoolFilesystem implements SpoolFilesystem
{
    public function __construct(private readonly string $root) {}

    public function list(string $folder): array
    {
        $path = $this->folderPath($folder);

        if (! is_dir($path)) {
            return [];
        }

        $names = @scandir($path);

        if ($names === false) {
            throw new SpoolUnavailable("Unable to read {$path}.");
        }

        $files = [];

        foreach ($names as $name) {
            if (SpoolName::isIgnored($name) || ! is_file($path.$name)) {
                continue;
            }

            $files[] = new SpoolFile(
                $name,
                @filesize($path.$name) ?: 0,
                Carbon::createFromTimestamp(@filemtime($path.$name) ?: 0),
            );
        }

        return $files;
    }

    public function read(string $folder, string $name): ?string
    {
        $target = $this->resolve($folder, $name);

        if ($target === null) {
            return null;
        }

        $contents = @file_get_contents($target);

        return $contents === false ? null : $contents;
    }

    public function write(string $folder, string $name, string $contents): void
    {
        $this->ensureFolder($folder);

        $path = $this->folderPath($folder).SpoolName::leaf($name);

        if (@file_put_contents($path, $contents) === false) {
            throw new SpoolUnavailable("Unable to write {$path}.");
        }
    }

    public function delete(string $folder, string $name): bool
    {
        $target = $this->resolve($folder, $name);

        if ($target === null) {
            return false;
        }

        if (! @unlink($target)) {
            throw new SpoolUnavailable("Unable to delete {$name}.");
        }

        return true;
    }

    public function move(string $fromFolder, string $fromName, string $toFolder, string $toName): bool
    {
        $source = $this->resolve($fromFolder, $fromName);

        if ($source === null) {
            return false;
        }

        $this->ensureFolder($toFolder);

        return @rename($source, $this->folderPath($toFolder).SpoolName::leaf($toName));
    }

    public function ensureFolder(string $folder): void
    {
        $path = $this->folderPath($folder);

        if (! is_dir($path)) {
            @mkdir($path, 0775, true);
        }
    }

    public function probe(): SpoolProbeResult
    {
        $start = hrtime(true);

        if (! is_dir($this->root)) {
            return new SpoolProbeResult(
                SpoolProbeResult::MISCONFIGURED,
                "{$this->root} does not exist. Check the source's root path, or the mount that should provide it.",
                $this->elapsed($start),
            );
        }

        $counts = [];

        foreach (['tosend', 'sent', 'fail', 'preproc'] as $folder) {
            try {
                $counts[$folder] = count($this->list($folder));
            } catch (SpoolUnavailable $e) {
                return new SpoolProbeResult(SpoolProbeResult::UNREACHABLE, $e->getMessage(), $this->elapsed($start));
            }
        }

        return new SpoolProbeResult(
            SpoolProbeResult::REACHABLE,
            "Read {$this->root}.",
            $this->elapsed($start),
            $counts,
            is_writable($this->root),
        );
    }

    /**
     * Resolve a caller-supplied name to a real path inside the folder, or null when it is
     * not there.
     *
     * SpoolName has already reduced it to a safe leaf; realpath then confirms the result
     * sits directly inside the intended directory, so a symlink planted in the spool
     * resolves outside it and is refused. That second check is local-only — there is no
     * remote equivalent — which is why the name itself has to be provably safe first.
     */
    private function resolve(string $folder, string $name): ?string
    {
        $path = $this->folderPath($folder);
        $leaf = SpoolName::leaf($name);

        $real = realpath($path.$leaf);
        $realDir = realpath($path);

        if ($real === false || $realDir === false) {
            return null;
        }

        if (dirname($real) !== $realDir || ! is_file($real) || is_link($path.$leaf)) {
            throw new InvalidArgumentException("Refusing to operate on [{$name}].");
        }

        return $real;
    }

    private function folderPath(string $folder): string
    {
        return rtrim($this->root, '/')."/{$folder}/";
    }

    private function elapsed(float $start): int
    {
        return (int) round((hrtime(true) - $start) / 1_000_000);
    }
}
