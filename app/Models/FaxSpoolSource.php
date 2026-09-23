<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedSerialized;
use App\Enums\FaxProvider;
use App\Services\Faxing\FaxSpool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One place faxes arrive from — an Intelligent Series fax service writing into a share we
 * serve, or a share on an IS server that we read.
 *
 * Sites run several IS servers but only ever one processes faxes at a time; the others sit
 * reachable with an empty tosend/. We therefore never work out which is active. Every
 * enabled source is scanned independently and whichever is producing files is the live
 * one. There is deliberately no notion of a primary, a substitute, or a failover target
 * anywhere in this model — that decision belongs to IS, not to us.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property bool $enabled
 * @property string $driver
 * @property FaxProvider|null $pinned_provider
 * @property string|null $root_path
 * @property int $consecutive_failures
 * @property string|null $last_error
 * @property Carbon|null $last_error_at
 * @property Carbon|null $last_healthy_at
 * @property Carbon|null $health_synced_at
 * @property string|null $smb_host
 * @property array<string, array<string, string>>|null $share_map
 * @property string|null $smb_username
 * @property string|null $smb_password
 * @property string|null $smb_domain
 * @property string|null $min_protocol
 * @property string|null $max_protocol
 * @property int $timeout_seconds
 * @property string|null $legacy_mount_path
 */
class FaxSpoolSource extends Model
{
    use HasFactory;

    public const DRIVER_LOCAL = 'local';

    public const DRIVER_SMB = 'smb';

    protected $fillable = [
        'key',
        'name',
        'enabled',
        'driver',
        'pinned_provider',
        'root_path',
        'smb_host',
        'share_map',
        'smb_username',
        'smb_password',
        'smb_domain',
        'min_protocol',
        'max_protocol',
        'timeout_seconds',
        'legacy_mount_path',
    ];

    /**
     * Keep the share credentials out of toArray()/toJson() and out of any model dump, so
     * a stray log line can never carry them. Mirrors DataSource.
     */
    protected $hidden = [
        'smb_username',
        'smb_password',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'pinned_provider' => FaxProvider::class,
            'consecutive_failures' => 'integer',
            'last_error_at' => 'datetime',
            'last_healthy_at' => 'datetime',
            'health_synced_at' => 'datetime',
            'share_map' => 'array',
            'timeout_seconds' => 'integer',

            // Credentials: encrypted at rest, transparent to callers. Read and write
            // PLAINTEXT — do NOT wrap these in encrypt()/decrypt().
            'smb_username' => EncryptedSerialized::class,
            'smb_password' => EncryptedSerialized::class,
        ];
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    /**
     * Where this source's spool folders live.
     *
     * Stored null on the seeded rows and resolved here, so `mfax` keeps resolving to
     * storage/app/mfax wherever the application is deployed rather than to whatever
     * storage_path() happened to return when the migration ran.
     */
    public function rootPath(): string
    {
        return rtrim($this->root_path ?: storage_path("app/{$this->key}"), '/');
    }

    /**
     * Whether this source predates per-fax provider routing.
     *
     * The two seeded sources are named after the provider they are pinned to, which is
     * what the spool directories were called before a source existed. That equality is
     * the definition of "legacy" and is used to keep their queue lock keys on the
     * original bare format — see FaxLockKey.
     */
    public function isLegacy(): bool
    {
        return $this->pinned_provider?->value === $this->key;
    }

    /**
     * Resolve a source key that arrived from a URL or an email link.
     *
     * Falls back to a sensible source rather than 404ing, because these keys reach us
     * from bookmarks and from alert emails that outlive the source they named. Never
     * returns a key that is not an enabled row, so the result is safe to use as a spool
     * directory segment.
     */
    /**
     * Which SMB share (and path within it) serves a given provider's folders.
     *
     * Defaults to a share named after the provider at its root, which is the existing
     * convention on every deployment: \\mission-control\mfax, //isserver/ringcentral.
     *
     * @return array{share: string, prefix: string}
     */
    public function shareFor(string $provider): array
    {
        $entry = $this->share_map[$provider] ?? [];

        return [
            'share' => (string) ($entry['share'] ?? $provider),
            'prefix' => (string) ($entry['prefix'] ?? ''),
        ];
    }

    /**
     * Create this source's spool folders if they are missing.
     *
     * Only meaningful for a local source, and only called when one is saved. Amtelco's
     * fax service writes into these directories over Samba, and a share pointing at a
     * path that does not exist fails in a way that looks like a fax problem rather than a
     * setup one. A remote source's folders belong to the Intelligent Series server and
     * are not ours to create.
     *
     * @return array<int, string> the folders that had to be created
     */
    public function ensureFolders(): array
    {
        if ($this->usesSmb()) {
            return [];
        }

        $created = [];

        foreach (FaxSpool::allFolders() as $folder) {
            $path = $this->rootPath()."/{$folder}";

            if (is_dir($path)) {
                continue;
            }

            if (@mkdir($path, 0775, true) || is_dir($path)) {
                $created[] = $folder;
            }
        }

        return $created;
    }

    public function usesSmb(): bool
    {
        return $this->driver === self::DRIVER_SMB;
    }

    /**
     * @param  array<int, string>|null  $allowed  restrict to these keys, e.g. the servers
     *                                            that can feed the provider being viewed
     */
    public static function resolveKey(?string $requested, string $preferred, ?array $allowed = null): string
    {
        $enabled = $allowed ?? static::query()->enabled()->orderBy('key')->pluck('key')->all();

        if ($requested !== null && in_array($requested, $enabled, true)) {
            return $requested;
        }

        if (in_array($preferred, $enabled, true)) {
            return $preferred;
        }

        return $enabled[0] ?? $preferred;
    }

    public static function findByKey(?string $key): ?self
    {
        return $key === null || $key === '' ? null : static::query()->where('key', $key)->first();
    }
}
