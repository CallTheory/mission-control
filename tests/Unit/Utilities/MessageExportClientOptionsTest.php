<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Livewire\Utilities\MessageExport;
use Tests\TestCase;

/**
 * Account rows come back from the Intelligent Series database as stdClass objects. The
 * form read them as arrays and returned a 500 the moment it opened. No feature test
 * reached that code, because without a SQL Server the account list is always empty.
 */
class MessageExportClientOptionsTest extends TestCase
{
    public function test_object_rows_become_account_options(): void
    {
        $options = MessageExport::clientOptions([
            (object) ['ClientNumber' => 1001, 'ClientName' => 'Acme Answering'],
            (object) ['ClientNumber' => '2002', 'ClientName' => null],
        ]);

        $this->assertSame([
            '1001' => '1001 — Acme Answering',
            '2002' => '2002 —',
        ], $options);
    }

    public function test_no_rows_means_no_options(): void
    {
        $this->assertSame([], MessageExport::clientOptions([]));
    }
}
