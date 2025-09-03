<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure clean database state for each test
        if (config('database.default') === 'sqlite') {
            // Force rollback any existing transactions before starting
            $this->rollbackAllTransactions();
            DB::statement('PRAGMA foreign_keys = ON;');
        }
    }

    protected function tearDown(): void
    {
        // Ensure any pending transactions are rolled back
        if (config('database.default') === 'sqlite') {
            $this->rollbackAllTransactions();
        }

        parent::tearDown();
    }

    private function rollbackAllTransactions(): void
    {
        try {
            // Check if there's an active connection
            if (DB::connection()->getPdo()) {
                // Force rollback all active transactions
                while (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
            }
        } catch (\Exception $e) {
            // If there's any error, try to reconnect
            DB::purge();
            DB::reconnect();
        }
    }
}
