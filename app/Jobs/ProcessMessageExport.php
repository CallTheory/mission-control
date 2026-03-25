<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\MessageExportMailable;
use App\Models\MessageExport;
use App\Models\MessageExportLog;
use App\Models\Stats\Helpers;
use App\Models\Stats\Messages\MessagesByAccount;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ProcessMessageExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public MessageExport $export;

    public Carbon $startDate;

    public Carbon $endDate;

    public ?int $userId;

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    public function __construct(MessageExport $export, Carbon $startDate, Carbon $endDate, ?int $userId = null)
    {
        $this->export = $export;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->userId = $userId;
        $this->queue = 'message-export';
    }

    public function handle(): void
    {
        $log = MessageExportLog::create([
            'message_export_id' => $this->export->id,
            'team_id' => $this->export->team_id,
            'user_id' => $this->userId,
            'export_name' => $this->export->name,
            'client_number' => $this->export->client_number,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'status' => 'queued',
        ]);

        try {
            $team = $this->export->team;

            $messageStats = new MessagesByAccount(
                $this->export->client_number,
                $this->startDate->format('Y-m-d H:i:s'),
                $this->endDate->format('Y-m-d H:i:s'),
                $team->allowed_accounts ?? '',
                $team->allowed_billing ?? '',
            );

            $results = $messageStats->results ?? [];

            if (empty($results)) {
                Log::info("No messages found for export {$this->export->id} between {$this->startDate} and {$this->endDate}");
                $log->markAsNoMessages();

                return;
            }

            $selectedFields = $this->export->selected_fields;
            $filterField = $this->export->filter_field;
            $filterValue = $this->export->filter_value;
            $includeCallInfo = $this->export->include_call_info;

            // Build CSV content
            $csvContent = $this->generateCsv($results, $selectedFields, $filterField, $filterValue, $includeCallInfo);

            if ($csvContent['count'] === 0) {
                Log::info("No messages matched filter for export {$this->export->id}");
                $log->markAsNoMessages();

                return;
            }

            // Store encrypted CSV for on-demand download
            $filePath = "message-exports/{$log->id}.csv";
            Storage::put($filePath, encrypt($csvContent['csv']));

            // Send email if recipients are configured
            $recipients = $this->export->recipients;
            if (! empty($recipients)) {
                Mail::send(new MessageExportMailable(
                    $this->export,
                    $csvContent['csv'],
                    $this->startDate,
                    $this->endDate,
                    $csvContent['count'],
                ));

                $log->markAsSent($csvContent['count']);
                Log::info("Message export emailed for export {$this->export->id} with {$csvContent['count']} messages");
            } else {
                $log->markAsCompleted($csvContent['count'], $filePath);
                Log::info("Message export generated for export {$this->export->id} with {$csvContent['count']} messages");
            }
        } catch (Exception $e) {
            Log::error("Failed to process message export {$this->export->id}: " . $e->getMessage());
            $log->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate CSV content from message results.
     */
    private function generateCsv(array $results, array $selectedFields, ?string $filterField, ?string $filterValue, bool $includeCallInfo): array
    {
        $handle = fopen('php://temp', 'r+');
        $count = 0;

        // Build header row
        $headers = ['Message ID'];
        if ($includeCallInfo) {
            $headers = array_merge($headers, [
                'Call ID',
                'Client Number',
                'Client Name',
                'Billing Code',
                'Agent Name',
                'Agent Initials',
                'Message Timestamp',
            ]);
        }
        $headers = array_merge($headers, $selectedFields);

        fputcsv($handle, $headers);

        foreach ($results as $row) {
            // Parse XML to get revision fields
            $fields = $this->extractLastRevisionFields($row);

            if ($fields === null) {
                continue;
            }

            // Apply filter if configured
            if ($filterField && $filterValue !== null) {
                $fieldVal = $fields[$filterField] ?? null;
                if (is_array($fieldVal) || (string) $fieldVal !== $filterValue) {
                    continue;
                }
            }

            // Build CSV row
            $csvRow = [$row->msgId ?? ''];

            if ($includeCallInfo) {
                $csvRow = array_merge($csvRow, [
                    $row->callid ?? $row->savedCallId ?? '',
                    $row->ClientNumber ?? '',
                    $row->ClientName ?? '',
                    $row->BillingCode ?? '',
                    $row->AgentName ?? '',
                    $row->AgentInitials ?? '',
                    $row->Stamp ?? '',
                ]);
            }

            // Add selected fields
            foreach ($selectedFields as $fieldName) {
                $value = $fields[$fieldName] ?? '';
                // Skip array values (nested field groups)
                $csvRow[] = is_array($value) ? '' : (string) $value;
            }

            fputcsv($handle, $csvRow);
            $count++;
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return [
            'csv' => $csv,
            'count' => $count,
        ];
    }

    /**
     * Extract fields from the last revision of a message's XmlMessage.
     */
    private function extractLastRevisionFields(object $row): ?array
    {
        if (empty($row->XmlMessage)) {
            return [];
        }

        try {
            $parsed = Helpers::parseXmlMessage($row->XmlMessage);

            if (empty($parsed['fields'])) {
                return [];
            }

            // Get the last revision's fields
            $lastRevisionFields = end($parsed['fields']);

            return is_array($lastRevisionFields) ? $lastRevisionFields : [];
        } catch (Exception $e) {
            return null;
        }
    }
}
