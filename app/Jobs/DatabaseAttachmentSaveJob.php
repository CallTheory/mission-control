<?php

namespace App\Jobs;

use App\Models\DataSource;
use App\Models\InboundEmail;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use League\Csv\InvalidArgument;
use League\Csv\Reader;
use League\Csv\SyntaxError;
use League\Csv\UnavailableStream;

class DatabaseAttachmentSaveJob implements ShouldQueue
{
    use Queueable;

    private InboundEmail $email;

    private string $database_actions;

    private array $database_commands;

    private DataSource $datasource;

    /**
     * Create a new job instance.
     */
    public function __construct(InboundEmail $email, $database_action)
    {
        $this->datasource = DataSource::firstOrFail();

        $this->database_actions = $database_action;
        $commands = explode(':', $this->database_actions);
        $this->database_commands['database'] = $commands[0] ?? 'fail';
        $this->database_commands['action'] = $commands[1] ?? 'fail';
        $this->database_commands['table_name'] = $commands[2] ?? 'fail';

        if (isset($commands[3])) {
            $this->database_commands['column_for_pk'] = $commands[3];
        }
        $this->email = $email;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // make sure we have a database and table
        $validator = Validator::make($this->database_commands, [
            'database' => 'required|string|in:database',
            'action' => 'required|string|in:replace',
            'table_name' => 'required|string',
            'column_for_pk' => 'required_if:action,merge|integer|min:1|max:255',
        ]);

        if (! $validator->errors()->any()) {
            if ($this->database_commands['action'] === 'merge') {
                try {
                    $this->mergeToClientDatabase();
                } catch (Exception $e) {
                    Log::error('Unable to merge database', ['database_commands' => $this->database_commands, 'e' => $e->getMessage()]);
                    $this->email->processed_at = Carbon::now();
                    $this->email->save();
                }
            } elseif ($this->database_commands['action'] === 'replace') {
                try {
                    $this->replaceClientDatabase();
                } catch (Exception $e) {
                    Log::error('Unable to replace database', ['database_commands' => $this->database_commands, 'e' => $e->getMessage()]);
                    $this->email->processed_at = Carbon::now();
                    $this->email->save();
                }
            } else {
                Log::error('Invalid action trying to parse database', ['database_commands' => $this->database_commands]);
                $this->email->processed_at = Carbon::now();
                $this->email->save();
            }
        } else {
            Log::error('Invalid action trying to parse database action', ['errors' => $validator->errors()->toArray()]);
            $this->email->processed_at = Carbon::now();
            $this->email->save();
        }
    }

    /**
     * @throws Exception
     */
    protected function validateDatabaseConnection(): void
    {
        $validator = Validator::make([
            'host' => $this->datasource->client_db_host,
            'port' => $this->datasource->client_db_port,
            'database' => $this->datasource->client_db_data,
            'username' => $this->datasource->client_db_user,
            'password' => $this->datasource->client_db_pass,
        ], [
            'host' => 'required|string',
            'port' => 'required|string',
            'database' => 'required|string',
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            $this->email->processed_at = Carbon::now();
            $this->email->save();
            throw new Exception('Invalid datasource configuration');
        }
    }

    /**
     * @throws InvalidArgument
     * @throws SyntaxError
     * @throws \League\Csv\Exception
     * @throws Exception
     */
    public function mergeToClientDatabase(): void
    {

        $this->validateDatabaseConnection();

        // check to see if the table exists
        // if so, truncate the table
        // if the database/table does not exist, we create it,
        // then iterate through the records and save the files
        try {
            Config::set('database.connections.clientdb', [
                'driver' => 'sqlsrv',
                'host' => $this->datasource->client_db_host,
                'port' => $this->datasource->client_db_port,
                'database' => $this->datasource->client_db_data,
                'username' => $this->datasource->client_db_user,
                'password' => decrypt($this->datasource->client_db_pass),
                'encrypt' => true,
                'trust_server_certificate' => true,
            ]);
        } catch (Exception $e) {
            Log::error('Error decrypting datasource client_db_pass: '.$e->getMessage());
            $this->email->processed_at = Carbon::now();
            $this->email->save();

            return;
        }

        $file_details = null;

        $attachment_details = json_decode($this->email->attachment_info, true);

        foreach ($attachment_details as $k => $attachment_detail) {
            if ($attachment_detail['type'] === 'text/csv' || $attachment_detail['type'] === 'text/plain') {
                $file_details = pathinfo($attachment_detail['filename']);
            }
        }

        if (is_null($file_details)) {
            Log::error('No CSV file was found in attachments.');
            $this->email->processed_at = Carbon::now();
            $this->email->save();

            return;
        }

        $filename = Str::slug($file_details['filename']).'.'.$file_details['extension'];
        $csv_location = "app/inbound-email/{$this->email->id}/{$filename}";

        $csv = Reader::createFromString(file_get_contents(storage_path($csv_location)));
        $csv->setEscape(''); // required in PHP8.4+

        $csv->setHeaderOffset(0);
        $header = $csv->getHeader(); // Get the header row
        $records = $csv->getRecords(); // Get the records

        if (empty($header)) {
            // Generate basic column names if the header row doesn't exist
            $firstRecord = $csv->first();
            $header = array_map(function ($index) {
                return 'column_'.($index + 1);
            }, array_keys($firstRecord));
        }

        if (! Schema::connection('clientdb')->hasTable($this->database_commands['table_name'])) {
            // Create the table schema
            Schema::connection('clientdb')->create($this->database_commands['table_name'], function ($table) use ($header) {
                $table->increments('id');
                foreach ($header as $column) {
                    $column = Str::trim($column);
                    if ($this->database_commands['column_for_pk'] === $column) {
                        $table->string($column)->unique();
                    } else {
                        $table->string($column)->nullable();
                    }
                }
            });
        }

        $records_that_exist = [];

        // Update or Insert records based on the 'column_for_pk' variable
        foreach ($records as $record) {
            DB::connection('clientdb')
                ->table($this->database_commands['table_name'])
                ->updateOrInsert([$this->database_commands['column_for_pk'] => $record[$this->database_commands['column_for_pk']]], $record);

            $records_that_exist[] = $record['id'];
        }

        DB::connection('clientdb')->table($this->database_commands['table_name'])->whereNotIn($this->database_commands['column_for_pk'], $records_that_exist)->delete();

        $this->email->processed_at = Carbon::now();
        $this->email->save();
    }

    /**
     * @throws UnavailableStream
     * @throws SyntaxError
     * @throws \League\Csv\Exception
     * @throws Exception
     */
    public function replaceClientDatabase(): void
    {
        $this->validateDatabaseConnection();

        // check to see if the table exists
        // if so, truncate the table
        // if the database/table does not exist, we create it,
        // then iterate through the records and save the files
        try {
            Config::set('database.connections.clientdb', [
                'driver' => 'sqlsrv',
                'host' => $this->datasource->client_db_host,
                'port' => $this->datasource->client_db_port,
                'database' => $this->datasource->client_db_data,
                'username' => $this->datasource->client_db_user,
                'password' => decrypt($this->datasource->client_db_pass),
                'encrypt' => true,
                'trust_server_certificate' => true,
            ]);
        } catch (Exception $e) {
            Log::error('Error decrypting datasource client_db_pass: '.$e->getMessage());
            $this->email->processed_at = Carbon::now();
            $this->email->save();

            return;
        }

        $file_details = null;

        $attachment_details = json_decode($this->email->attachment_info, true);
        Log::info('Attachment details', ['attachment_details' => $attachment_details]);

        foreach ($attachment_details as $k => $attachment_detail) {
            // handle attachment types here (i.e., helpscout attachments vs. plain text attachments)
            if ($attachment_detail['type'] === 'text/csv' || $attachment_detail['type'] === 'text/plain') {
                $file_details = pathinfo($attachment_detail['filename']);
            }
        }

        if (is_null($file_details)) {
            Log::error('No CSV file was found in attachments.');
            $this->email->processed_at = Carbon::now();
            $this->email->save();

            return;
        }

        $filename = Str::slug($file_details['filename']).'.'.$file_details['extension'];
        $csv_location = "app/inbound-email/{$this->email->id}/{$filename}";

        $csv = Reader::createFromString(file_get_contents(storage_path($csv_location)));

        try {
            $csv->setHeaderOffset(0);
        } catch (Exception $e) {
            Log::error('Unable to set header offset', ['database_commands' => $this->database_commands, 'csv_location' => $csv_location, 'filename' => $filename, 'email' => $this->email->id]);

            return;
        }

        try {
            $header = $csv->getHeader(); // Get the header row
        } catch (Exception $e) {
            Log::error('Unable to get header row', ['database_commands' => $this->database_commands, 'csv_location' => $csv_location, 'filename' => $filename, 'email' => $this->email->id]);

            return;
        }

        try {
            $records = $csv->getRecords(); // Get the records
        } catch (Exception $e) {
            Log::error('No records found in CSV file', ['database_commands' => $this->database_commands, 'csv_location' => $csv_location, 'filename' => $filename, 'email' => $this->email->id]);

            return;
        }

        if (empty($header)) {
            // Generate basic column names if the header row doesn't exist
            try {
                $firstRecord = $csv->first();
            } catch (Exception $e) {
                Log::error('Unable to get first record', ['database_commands' => $this->database_commands, 'csv_location' => $csv_location, 'filename' => $filename, 'email' => $this->email->id]);

                return;
            }
            $header = array_map(function ($index) {
                return 'column_'.($index + 1);
            }, array_keys($firstRecord));
        }

        if (! Schema::connection('clientdb')->hasTable($this->database_commands['table_name'])) {
            // Create the table schema
            Schema::connection('clientdb')->create($this->database_commands['table_name'], function ($table) use ($header) {
                $table->increments('id');
                foreach ($header as $column) {
                    $column = Str::trim($column);
                    $table->string($column)->nullable();
                }
            });
        }

        $comparison = $this->compareCsvWithDatabase(
            storage_path($csv_location),
            $this->database_commands['table_name']);

        DB::connection('clientdb')->table($this->database_commands['table_name'])->truncate();
        Log::error('Truncating database table', ['database_commands' => $this->database_commands, 'csv_location' => $csv_location, 'filename' => $filename, 'email' => $this->email->id]);

        foreach ($records as $record) {
            DB::connection('clientdb')->table($this->database_commands['table_name'])->insert($record);
        }

        $this->email->processed_at = Carbon::now();
        $this->email->save();
    }

    /**
     * @throws UnavailableStream
     * @throws SyntaxError
     * @throws \League\Csv\Exception
     */
    protected function compareCsvWithDatabase($csvFilePath, $tableName): array
    {
        // Read the CSV file
        $csv = Reader::createFromPath($csvFilePath, 'r');
        $csv->setHeaderOffset(0); // Set the header offset to 0 if the header row exists

        $header = $csv->getHeader(); // Get the header row

        if (empty($header)) {
            // Generate basic column names if the header row doesn't exist
            $firstRecord = $csv->first();
            $header = array_map(function ($index) {
                return 'column_'.($index + 1);
            }, array_keys($firstRecord));
        }

        // Get the columns of the specified table from the database
        $columns = Schema::getColumnListing($tableName);

        // Compare the CSV header with the database columns
        $missingInDatabase = array_diff($header, $columns);
        $extraInDatabase = array_diff($columns, $header);
        unset($extraInDatabase['id']); // Ignore the 'id' column

        return [
            'success' => empty($missingInDatabase) && empty($extraInDatabase),
            'missing_in_database' => $missingInDatabase,
            'extra_in_database' => $extraInDatabase,
            'columns_from_table' => $columns,
            'header_from_csv' => $header,
        ];
    }
}
