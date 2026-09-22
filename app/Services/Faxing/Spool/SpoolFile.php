<?php

declare(strict_types=1);

namespace App\Services\Faxing\Spool;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One file in a spool folder, with everything the callers need about it.
 *
 * Size and modification time are carried here rather than fetched on demand because over
 * SMB they come back with the listing for free, while a per-file filesize()/filemtime()
 * would be two extra round trips each — and the dashboards describe every file in four
 * folders, every minute.
 */
final readonly class SpoolFile
{
    public function __construct(
        public string $name,
        public int $size,
        public Carbon $modifiedAt,
    ) {}

    /**
     * 'cap' (the payload), 'fs' (the per-recipient metadata) or 'other'.
     */
    public function type(): string
    {
        return match (true) {
            Str::endsWith($this->name, '.cap') => 'cap',
            Str::endsWith($this->name, '.fs') => 'fs',
            default => 'other',
        };
    }

    /**
     * The descriptor shape the dashboards and alerts render.
     *
     * @return array<string, mixed>
     */
    public function toDescriptor(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type(),
            'size' => $this->size,
            'modified_at' => $this->modifiedAt->toIso8601String(),
            'job_id' => null,
            'account' => null,
        ];
    }
}
