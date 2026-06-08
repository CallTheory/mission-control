<?php

namespace Tests\Feature\Jobs;

use App\Jobs\MoveSuccessfulFaxFiles;
use Tests\TestCase;

class MoveFaxFilesFanoutTest extends TestCase
{
    private string $tosend;

    private string $sent;

    private string $cap = 'ee45c2cd-d9e0-4e37-967e-27fc769b8840.cap';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.switch_engine' => 'genesis']);

        $this->tosend = storage_path('app/ringcentral/tosend/');
        $this->sent = storage_path('app/ringcentral/sent/');

        foreach ([$this->tosend, $this->sent] as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        }

        // Two .fs files (a fan-out) pointing at the same .cap payload.
        file_put_contents($this->tosend.'IS20.fs', "\$var_def DATA6 c:\\copia\\tosend\\{$this->cap}\n\$fax_phone 9139069098\n\$fax_status1 2\n");
        file_put_contents($this->tosend.'IS21.fs', "\$var_def DATA6 c:\\copia\\tosend\\{$this->cap}\n\$fax_phone 9133597692\n\$fax_status1 2\n");
        file_put_contents($this->tosend.$this->cap, "No messages.\n");
    }

    protected function tearDown(): void
    {
        foreach ([$this->tosend, $this->sent] as $dir) {
            foreach (['IS20.fs', 'IS21.fs', $this->cap] as $file) {
                @unlink($dir.$file);
            }
        }

        parent::tearDown();
    }

    private function detailsFor(string $fs, string $phone): array
    {
        return [
            'jobID' => 23,
            'capfile' => $this->cap,
            'filename' => $this->cap,
            'phone' => $phone,
            'status' => '2',
            'fsFileName' => $fs,
        ];
    }

    public function test_shared_cap_survives_until_the_last_recipient_is_moved(): void
    {
        // First recipient resolves — the .cap is still referenced by IS21.fs and must stay.
        (new MoveSuccessfulFaxFiles($this->detailsFor('IS20.fs', '9139069098'), 'ringcentral'))->handle();

        $this->assertFileDoesNotExist($this->tosend.'IS20.fs', 'first .fs should be moved out of tosend');
        $this->assertFileExists($this->sent.'IS20.fs');
        $this->assertFileExists($this->tosend.$this->cap, 'shared .cap must survive while IS21.fs still references it');

        // Second (last) recipient resolves — now the .cap may be removed.
        (new MoveSuccessfulFaxFiles($this->detailsFor('IS21.fs', '9133597692'), 'ringcentral'))->handle();

        $this->assertFileDoesNotExist($this->tosend.'IS21.fs');
        $this->assertFileExists($this->sent.'IS21.fs');
        $this->assertFileDoesNotExist($this->tosend.$this->cap, 'shared .cap should be removed once no .fs references it');
        $this->assertFileExists($this->sent.$this->cap);
    }
}
