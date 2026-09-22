<?php

declare(strict_types=1);

namespace App\Services\Faxing\Spool;

use App\Services\Faxing\Spool\Exceptions\SpoolUnavailable;
use Icewind\SMB\BasicAuth;
use Icewind\SMB\Exception\AlreadyExistsException;
use Icewind\SMB\Exception\ConnectException;
use Icewind\SMB\Exception\ForbiddenException;
use Icewind\SMB\Exception\InvalidHostException;
use Icewind\SMB\Exception\NotFoundException;
use Icewind\SMB\IShare;
use Icewind\SMB\Options;
use Icewind\SMB\ServerFactory;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * A spool source reached over SMB, with credentials held by Mission Control.
 *
 * This replaces a kernel CIFS mount. The difference that matters is containment: on a
 * stale kernel mount, scandir() blocks in uninterruptible sleep where no signal is
 * delivered and no timeout fires, so a dead Intelligent Series server can wedge a worker
 * indefinitely. Here the session is a child process with a wall-clock budget (see
 * GuardedSystem), so the same outage surfaces as an exception.
 *
 * The credentials also stop being an fstab entry only root can see and become a value the
 * admin screen can test — which is the difference between "faxing is broken" and "the
 * password for IS-2 expired".
 */
class SmbSpoolFilesystem implements SpoolFilesystem
{
    private ?IShare $share = null;

    /**
     * @param  string  $prefix  path within the share, '' for its root
     */
    public function __construct(
        private readonly string $host,
        private readonly string $shareName,
        private readonly string $prefix,
        private readonly string $username,
        private readonly string $password,
        private readonly string $domain = '',
        private readonly int $requestTimeout = 15,
        private readonly int $sessionSeconds = 40,
        private readonly ?string $minProtocol = 'SMB2',
        private readonly ?string $maxProtocol = null,
    ) {}

    public function list(string $folder): array
    {
        try {
            $entries = $this->share()->dir($this->path($folder));
        } catch (NotFoundException $e) {
            // The folder is absent, which is not the same as the server being down.
            return [];
        } catch (Throwable $e) {
            throw $this->wrap($e, "list {$folder}");
        }

        $files = [];

        foreach ($entries as $entry) {
            if ($entry->isDirectory() || SpoolName::isIgnored($entry->getName())) {
                continue;
            }

            // Size and mtime arrive with the listing; asking per file would be two extra
            // round trips each.
            $files[] = new SpoolFile(
                $entry->getName(),
                $entry->getSize(),
                Carbon::createFromTimestamp($entry->getMTime()),
            );
        }

        return $files;
    }

    public function read(string $folder, string $name): ?string
    {
        $leaf = SpoolName::leaf($name);

        try {
            $stream = $this->share()->read($this->path($folder, $leaf));
            $contents = stream_get_contents($stream);
            fclose($stream);

            return $contents === false ? null : $contents;
        } catch (NotFoundException $e) {
            return null;
        } catch (Throwable $e) {
            throw $this->wrap($e, "read {$folder}/{$leaf}");
        }
    }

    public function write(string $folder, string $name, string $contents): void
    {
        $leaf = SpoolName::leaf($name);

        try {
            $stream = $this->share()->write($this->path($folder, $leaf));
            fwrite($stream, $contents);
            fclose($stream);
        } catch (Throwable $e) {
            throw $this->wrap($e, "write {$folder}/{$leaf}");
        }
    }

    public function delete(string $folder, string $name): bool
    {
        $leaf = SpoolName::leaf($name);

        try {
            $this->share()->del($this->path($folder, $leaf));

            return true;
        } catch (NotFoundException $e) {
            return false;
        } catch (Throwable $e) {
            throw $this->wrap($e, "delete {$folder}/{$leaf}");
        }
    }

    public function move(string $fromFolder, string $fromName, string $toFolder, string $toName): bool
    {
        $from = SpoolName::leaf($fromName);
        $to = SpoolName::leaf($toName);

        try {
            return $this->share()->rename($this->path($fromFolder, $from), $this->path($toFolder, $to));
        } catch (NotFoundException $e) {
            return false;
        } catch (Throwable $e) {
            throw $this->wrap($e, "move {$fromFolder}/{$from}");
        }
    }

    public function ensureFolder(string $folder): void
    {
        try {
            $this->share()->mkdir($this->path($folder));
        } catch (AlreadyExistsException $e) {
            // Expected: the fax service owns these directories.
        } catch (Throwable $e) {
            // Creating folders on someone else's server is best effort; the operations
            // that matter will report their own failure.
        }
    }

    public function probe(): SpoolProbeResult
    {
        $start = hrtime(true);
        $counts = [];
        $warnings = [];

        foreach (['tosend', 'sent', 'fail', 'preproc'] as $folder) {
            try {
                $counts[$folder] = count($this->list($folder));
            } catch (Throwable $e) {
                return new SpoolProbeResult(
                    $this->statusFor($e),
                    $this->explain($e),
                    $this->elapsed($start),
                );
            }
        }

        $writable = $this->testWrite($warnings);

        if (GuardedSystem::timeoutBinary() === null) {
            $warnings[] = 'coreutils `timeout` is not installed, so a wedged SMB session has no wall-clock limit. '
                .'Only smbclient\'s own per-request timeout applies.';
        }

        return new SpoolProbeResult(
            SpoolProbeResult::REACHABLE,
            "Connected to //{$this->host}/{$this->shareName}.",
            $this->elapsed($start),
            $counts,
            $writable,
            $warnings,
        );
    }

    /**
     * Confirm we can write as well as read.
     *
     * Reporting the outcome back to Intelligent Series means rewriting the `.fs` into
     * sent/ or fail/, so a read-only share would look healthy and then silently strand
     * every fax at the last step.
     *
     * @param  array<int, string>  $warnings
     */
    private function testWrite(array &$warnings): bool
    {
        $probe = 'mcprobe'.now()->timestamp.'.tmp';

        try {
            $this->write('tosend', $probe, 'mission-control write probe');
            $this->delete('tosend', $probe);

            return true;
        } catch (Throwable $e) {
            $warnings[] = 'The share is readable but not writable. Delivery results are reported back to '
                .'Intelligent Series by rewriting the .fs file into sent/ or fail/, so faxes would send '
                .'but never be confirmed.';

            return false;
        }
    }

    private function share(): IShare
    {
        if ($this->share !== null) {
            return $this->share;
        }

        $options = new Options;
        $options->setTimeout($this->requestTimeout);
        $options->setMinProtocol($this->minProtocol);
        $options->setMaxProtocol($this->maxProtocol);

        $factory = new ServerFactory($options, new GuardedSystem($this->sessionSeconds));

        try {
            // BasicAuth passes the password to smbclient over a file descriptor rather
            // than in argv, so it never appears in /proc/<pid>/cmdline.
            $server = $factory->createServer(
                $this->host,
                new BasicAuth($this->username, $this->domain === '' ? 'WORKGROUP' : $this->domain, $this->password),
            );

            return $this->share = $server->getShare($this->shareName);
        } catch (Throwable $e) {
            throw $this->wrap($e, 'connect');
        }
    }

    private function path(string $folder, ?string $name = null): string
    {
        $prefix = trim($this->prefix, '/');
        $path = ($prefix === '' ? '' : $prefix.'/').$folder;

        return $name === null ? $path : "{$path}/{$name}";
    }

    private function wrap(Throwable $e, string $operation): SpoolUnavailable
    {
        return new SpoolUnavailable("SMB {$operation} on //{$this->host}/{$this->shareName} failed: ".$e->getMessage(), 0, $e);
    }

    private function statusFor(Throwable $e): string
    {
        $cause = $e->getPrevious() ?? $e;

        return match (true) {
            $cause instanceof ForbiddenException => SpoolProbeResult::UNAUTHORIZED,
            $cause instanceof InvalidHostException => SpoolProbeResult::MISCONFIGURED,
            $cause instanceof ConnectException => SpoolProbeResult::UNREACHABLE,
            default => SpoolProbeResult::UNREACHABLE,
        };
    }

    private function explain(Throwable $e): string
    {
        $cause = $e->getPrevious() ?? $e;

        return match (true) {
            $cause instanceof ForbiddenException => "//{$this->host}/{$this->shareName} rejected the credentials. "
                .'Check the username, password and domain, and that the account may reach this share.',
            $cause instanceof InvalidHostException => "{$this->host} could not be resolved. Use an IP address if DNS is unreliable from this server.",
            $cause instanceof ConnectException => "Nothing answered on {$this->host}. Check the server is up and that port 445 is reachable.",
            default => $e->getMessage(),
        };
    }

    private function elapsed(float $start): int
    {
        return (int) round((hrtime(true) - $start) / 1_000_000);
    }
}
