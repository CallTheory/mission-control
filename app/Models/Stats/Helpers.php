<?php

namespace App\Models\Stats;

use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Helpers
{
    public static function ticksToSeconds($ticks): int
    {
        if ($ticks == 0) {
            return 0;
        }
        // 1 tick = 100 nanoseconds
        // 1 second = 10,000,000 ticks
        $ticksPerSecond = 10000000;

        return $ticks / $ticksPerSecond;
    }

    public static array $knownMessageLabels = [
        'First Name:', 'For:', 'From:',
        'Last Name:', 'Ptn:', 'Driver:',
        'Name:', 'DOB:',
        'IsCompany:',
        'Company:', 'PO Number:',
        'Provider:', 'Primary:',
        'eMail:', 'Unit #:',
        'Email:', 'Address:',
        'Memo:', 'Msg:',
        'Phone:', 'Dept/Flr:', 'Hosp/Ofc:',
        'Regarding:', 'Consult?:',
        'Date / Time:', 'Room #:',
        'Taken By:', 'Taken:',
        'Caller ID:', 'Clr ID:',
        'ACD ANI:', 'Source', 'Call Reason:',
        'Message History:',
        'No messages.',
        'IS Rec #:', 'Msg ID:',
    ];

    public static array $removeFromMessages = [
        '|',
        '`',
    ];

    public static function parseMessageLog($file_name): array
    {
        $file_content = Storage::get($file_name);
        $stripped = str_replace(['.txt'], '', basename($file_name));

        $parts = explode(' ', $stripped);
        $dateParts = str_split($parts[2]);

        $log['type'] = ucwords($parts[0]);
        $log['account'] = $parts[1];
        $log['date'] = "{$dateParts[4]}{$dateParts[5]}{$dateParts[6]}{$dateParts[7]}-{$dateParts[0]}{$dateParts[1]}-{$dateParts[2]}{$dateParts[3]}";
        $log['time'] = "{$dateParts[9]}{$dateParts[10]}:{$dateParts[11]}{$dateParts[12]}:{$dateParts[13]}{$dateParts[14]}";
        $log['raw'] = $file_content;

        $messages = explode('======================================', $log['raw']);
        $messageLines = [];
        $historyLines = [];

        if (count($messages) === 1) {
            $messageLines[1] = Helpers::messageFiltering(explode("\n", str_replace(["\n\n\n\n\n", "\n\n\n\n", "\n\n\n"], "\n\n", $messages[0])));
            $historyLines[1] = [];

            foreach ($messageLines[1] as $i => $ml) {
                if (Str::startsWith($ml, '===========')) {
                    unset($messageLines[1][$i]);
                }
            }
        } elseif (count($messages) > 1) {
            foreach ($messages as $key => $message) {
                if ($key !== 0) { // ignore the header row of the file
                    $details = explode("Message History:\n", trim($message));

                    $msgLines = explode("\n", str_replace(["\n\n\n\n\n", "\n\n\n\n", "\n\n\n"], "\n\n", $details[0]));

                    if (isset($details[1])) {
                        $hisLines = explode("\n", str_replace(["\n\n\n\n\n", "\n\n\n\n", "\n\n\n"], "\n\n", $details[1]));
                    } else {
                        $hisLines = [];
                    }

                    foreach ($hisLines as $j => $hl) {
                        if (Str::startsWith($hl, '=======') && Str::endsWith($hl, '=======')) {
                            unset($hisLines[$j]);
                        }
                    }

                    $messageLines[$key] = Helpers::messageFiltering($msgLines);
                    if (count($hisLines)) {
                        $historyLines[$key] = Helpers::messageFiltering($hisLines);
                    } else {
                        $historyLines[$key] = [];
                    }
                }
            }
        }

        $envelope = [
            'messages' => $messageLines,
            'history' => $historyLines,
        ];

        return ['log' => $log, 'envelope' => $envelope];
    }

    public static function messageFiltering(array $arr): array
    {
        $reassemble = null;

        foreach ($arr as $i => $line) {
            $reassemble[$i] = $line;

            // remove labels
            foreach (Helpers::$removeFromMessages as $item) {
                $reassemble[$i] = str_replace($item, '', $reassemble[$i]);
            }

            // format our labels
            foreach (Helpers::$knownMessageLabels as $label) {
                if (Str::startsWith($line, $label)) {
                    $reassemble[$i] = str_replace($label, "<strong>{$label}</strong>", $reassemble[$i]);
                }
            }
        }

        return $reassemble ?? [];
    }

    public static function isSystemFeatureEnabled(string $feature): bool
    {
        $featureFlagLocation = "feature-flags/{$feature}.flag";

        // our feature flag file must exist
        if (Storage::fileExists($featureFlagLocation)) {
            // the file contents must be encrypted using our key
            try {
                $contents = Storage::get($featureFlagLocation);
                $decrypted = decrypt($contents);

                return $decrypted === $feature;
            } catch (Exception $e) {
                return false;
            }
        }

        return false;
    }

    public static function voiceFormats(): array
    {
        $vf[-1] = 'NotApplicable';
        $vf[0] = 'Ulaw';
        $vf[1] = 'Wav';
        $vf[2] = 'Gsm';
        $vf[3] = 'Wav16';

        return $vf;
    }

    public static function scheduleRecurrenceTypes(): array
    {
        $rt[0] = 'None';
        $rt[1] = 'Hourly';
        $rt[2] = 'Daily';
        $rt[3] = 'Weekly';
        $rt[4] = 'Monthly';

        return $rt;
    }

    public static function scheduleTypes(): array
    {
        $st[-1] = 'None';
        $st[0] = 'Archive';
        $st[1] = 'Purge';
        $st[2] = 'ExportMessages';
        $st[3] = 'Report';
        $st[4] = 'MessageSummaryReport';
        $st[5] = 'Holiday';
        $st[6] = 'WakeUpCall_Unused';
        $st[7] = 'DataTransform';
        $st[8] = 'PurgeStats';
        $st[9] = 'ArchiveVoiceLogs';
        $st[10] = 'PurgeVoiceLogs';
        $st[11] = 'ArchiveOnCall';
        $st[12] = 'PurgeOnCall';
        $st[13] = 'BackupDatabase';
        $st[14] = 'SwitchBackup';
        $st[15] = 'MergeComm';
        $st[16] = 'SendMessages';
        $st[17] = 'TzoChange';
        $st[18] = 'Cue';
        $st[19] = 'Repeat';

        return $st;
    }

    public static function boardCheckCategories(): array
    {
        $categories[0] = 'Appointment scheduled incorrectly';
        $categories[1] = 'Documenting - Message slip or scheduling recorded incorrectly';
        $categories[2] = 'Contact instructions not followed';
        $categories[3] = 'Phone number entered incorrectly';
        $categories[4] = 'Standard procedures not followed';
        $categories[5] = 'General Error - Not Defined';
        $categories[6] = 'On-call change not entered or entered improperly';
        $categories[7] = 'Spelling';
        $categories[8] = 'Wrong On-Call / Client contacted';
        $categories[9] = 'Documenting incorrect or incomplete';

        return $categories;
    }

    public static function callTypes(): array
    {
        $ck[0] = 'Unknown'; // does not appear to be used?
        $ck[1] = 'Secretarial';
        $ck[2] = 'Checkin';
        $ck[3] = 'Fetch';
        $ck[4] = 'Scheduled';
        $ck[5] = 'IVR';
        $ck[6] = 'Web Script';
        $ck[7] = 'Voice Mail';
        $ck[8] = 'Auto Call';
        $ck[9] = 'Announcement';
        $ck[10] = 'MergeComm';
        $ck[11] = 'Page Confirmation';
        $ck[12] = 'Smart Paging';
        $ck[13] = 'Change Client';
        $ck[14] = 'Agent Audio';
        $ck[15] = 'Rauland';
        $ck[16] = 'Auto Attendant';
        $ck[17] = 'Listing Lookup';
        $ck[18] = 'Dispatch';
        $ck[19] = 'Park Orbit';
        $ck[20] = 'VM Callback';
        $ck[21] = 'Cue';
        $ck[22] = 'Repeat';
        $ck[23] = 'Conf Bridge';
        $ck[24] = 'Orbit';
        $ck[25] = 'Elevator';
        $ck[26] = 'Pers';

        return $ck;
    }

    public static function callStates(): array
    {
        $cs[0] = 'Unknown';
        $cs[1] = 'Disposed';
        $cs[2] = 'Disc';
        $cs[3] = 'Ring';
        $cs[4] = 'Talk';
        $cs[5] = 'Talk1';
        $cs[6] = 'Talk2';
        $cs[7] = 'Conference';
        $cs[8] = 'Hold';
        $cs[9] = 'In Progress';
        $cs[10] = 'Voice Mail';
        $cs[11] = 'Outbound Queue';
        $cs[12] = 'Auto';
        $cs[13] = 'Auto Hold';
        $cs[14] = 'Patch';
        $cs[15] = 'Bridge';
        $cs[16] = 'Max States';

        return $cs;
    }

    public static function scriptTrackerTypes(): array
    {
        $stt[0] = 'None';
        $stt[1] = 'Script Started';
        $stt[2] = 'Script Completed';
        $stt[3] = 'Script Canceled';
        $stt[4] = 'Script Resumed';
        $stt[5] = 'Script Edited';
        $stt[6] = 'Screen';
        $stt[7] = 'Screen Mode';
        $stt[8] = 'Branch';
        $stt[9] = 'Input Select';
        $stt[10] = 'Input Value';
        $stt[11] = 'Contact Select';
        $stt[12] = 'Prompt';
        $stt[13] = 'Prompt Response';
        $stt[14] = 'Action';
        // $stt[15] = ''; // Does not exist in ScriptTrackerTypes enum
        $stt[16] = 'Future1';
        $stt[17] = 'Future2';
        $stt[18] = 'Future3';
        $stt[19] = 'Future4';
        $stt[20] = 'Future5';

        return $stt;
    }

    public static function trackerTypes(): array
    {
        $ct[0] = 'None';
        $ct[1] = 'New Call';
        $ct[2] = 'End Call';
        $ct[3] = 'Distribute';
        $ct[4] = 'Answered';
        $ct[5] = 'Hold';
        $ct[6] = 'Auto Answer';
        $ct[7] = 'Dial Start';
        $ct[8] = 'Dial End';
        $ct[9] = 'Over Dial';
        $ct[10] = 'Hang Up';
        $ct[11] = 'Hung Up';
        $ct[12] = 'Change';
        $ct[13] = 'Reassign';
        $ct[14] = 'Park';
        $ct[15] = 'Conference';
        $ct[16] = 'Patch';
        $ct[17] = 'Transfer';
        $ct[18] = 'Digit Menu Entry';
        $ct[19] = 'Speech Rec';
        $ct[20] = 'Send To OP';
        $ct[21] = 'Smart Page';
        $ct[22] = 'Send To vm';
        $ct[23] = 'Script Action';
        $ct[24] = 'Abandon';
        $ct[25] = 'Switch Error';
        $ct[26] = 'Orbit';
        $ct[27] = 'Bridge Add';
        $ct[28] = 'Bridge Del';
        $ct[29] = 'Voci';
        $ct[30] = 'AI Insight';
        $ct[31] = 'Limited';
        $ct[32] = 'Disc Alert';
        $ct[33] = 'Logger Recording';
        $ct[34] = 'Video Recording';
        $ct[35] = 'Invalid Source';
        $ct[36] = 'Hold Alert';
        $ct[37] = 'Future6';
        $ct[38] = 'Future7';
        $ct[39] = 'Future8';

        return $ct;
    }

    public static function compCodes(): array
    {
        $cc[0] = 'Normal';
        $cc[1] = 'ScriptTimeout';
        $cc[2] = 'ParkAbandon';
        $cc[3] = 'Abandoned';

        return $cc;
    }

    public static function agentTrackerTypes(): array
    {
        $at[0] = 'None';
        $at[1] = 'Login';
        $at[2] = 'Logout';
        $at[3] = 'Ready';
        $at[4] = 'Not Ready';
        $at[5] = 'Temp ACD Change';
        $at[6] = 'Audio Established';
        $at[7] = 'Audio Lost';
        $at[8] = 'Future4';
        $at[9] = 'Future5';
        $at[10] = 'Future6';

        return $at;
    }

    public static function stationTypes(): array
    {
        $st[0] = 'Agent';
        $st[1] = 'Supervisor';
        $st[2] = 'WebDirectory';
        $st[3] = 'Applications';
        $st[4] = 'ConfigTest';
        $st[6] = 'ListingAdmin';
        $st[7] = 'Reporting';
        $st[8] = 'Importer';
        $st[9] = 'Sdk';
        $st[10] = 'Soft Agent';
        $st[11] = 'Ivr';
        $st[12] = 'InfinityBridge';
        $st[13] = 'HL7feed';
        $st[14] = 'MonitorStation';
        $st[15] = 'MiteyMite';
        $st[16] = 'LinkedAgent';
        $st[17] = 'LinkedOneCall';
        $st[18] = 'WebMessaging';
        $st[19] = 'SmsServer';
        $st[20] = 'TapServer';
        $st[21] = 'SecureMessaging';
        $st[22] = 'FaxServer';
        $st[23] = 'MergeComm';
        $st[24] = 'Mobile';
        $st[25] = 'TapTerminal';
        $st[26] = 'MiTeamWeb';
        $st[27] = 'OneCallAcd';
        $st[29] = 'Web Agent';
        $st[30] = 'MaxStation';

        return $st;
    }

    public static function formatDuration($numberOfSeconds, $format = 'H:i:s'): string
    {
        if ($format === 'H:i:s') {
            $hours = floor($numberOfSeconds / 3600);
            $minutes = floor(($numberOfSeconds / 60) % 60);
            $seconds = $numberOfSeconds % 60;

            // The normal gmdate/date/DateTime methods don't handle $numberOfSeconds larger than 86,400
            // So we do this manually

            return str_pad((string) $hours, 2, '0', STR_PAD_LEFT).':'.
                str_pad((string) $minutes, 2, '0', STR_PAD_LEFT).':'.
                str_pad((string) $seconds, 2, '0', STR_PAD_LEFT);
        } else {
            return number_format($numberOfSeconds / 60, 2).' minute(s)'; // minutes
        }
    }

    public static function formatMessageSummary(string $summary): string
    {
        $summary = str_replace(['`|', '|'], '', $summary);
        $filteredLabeled = Helpers::messageFiltering(explode("\n", $summary));
        $summary = implode("\n", $filteredLabeled);
        $summary = str_replace("\r\n", "\n", $summary);

        // $summary = str_replace("\n\n", "\n", $summary);
        return str_replace("\n", '<br>', $summary);
    }

    public static function allowedAccountAccess(
        string $account,
        string $billing = '',
        string $system_allowed_accounts = '',
        string $system_allowed_billings = '',

    ): bool {

        $allowed_account = false;
        $allowed_billing = false;

        if (strlen($system_allowed_accounts) === 0 && strlen($system_allowed_billings) === 0) {
            // no restrictions, this is only called by trusted code,
            // so checking a user's personal team happens before items are submitted...
            $allowed_account = true;
            $allowed_billing = true;
        } elseif (strlen($system_allowed_accounts) === 0 && strlen($system_allowed_billings) > 0) {

            // any account is fine, but it will also have to match the billing code
            $allowed_account = true;

            $billing_items = explode(',', implode(',', explode("\n", trim($system_allowed_billings))));

            foreach ($billing_items as $item) {
                if (Str::contains($item, '-')) {
                    $parts = explode('-', $item);
                    if ($billing >= $parts[0] && $billing <= $parts[1]) {
                        $allowed_billing = true;
                    }
                } else {
                    if ($billing == $item) {
                        $allowed_billing = true;
                    }
                }
            }

        } elseif (strlen($system_allowed_accounts) > 0 && strlen($system_allowed_billings) === 0) {

            $allowed_billing = true;

            $account_items = explode(',', implode(',', explode("\n", trim($system_allowed_accounts))));

            foreach ($account_items as $item) {
                if (Str::contains($item, '-')) {
                    $parts = explode('-', $item);
                    if ($account >= $parts[0] && $account <= $parts[1]) {
                        $allowed_account = true;
                    }
                } else {
                    if ($account == $item) {
                        $allowed_account = true;
                    }
                }
            }
        } else {

            $account_items = explode(',', implode(',', explode("\n", trim($system_allowed_accounts))));

            foreach ($account_items as $item) {
                if (Str::contains($item, '-')) {
                    $parts = explode('-', $item);
                    if ($account >= $parts[0] && $account <= $parts[1]) {
                        $allowed_account = true;
                    }
                } else {
                    if ($account == $item) {
                        $allowed_account = true;
                    }
                }
            }

            $billing_items = explode(',', implode(',', explode("\n", trim($system_allowed_billings))));

            foreach ($billing_items as $item) {
                if (Str::contains($item, '-')) {
                    $parts = explode('-', $item);
                    if ($billing >= $parts[0] && $billing <= $parts[1]) {
                        $allowed_billing = true;
                    }
                } else {
                    if ($billing == $item) {
                        $allowed_billing = true;
                    }
                }
            }
        }

        return $allowed_account && $allowed_billing;
    }

    public static function parseXmlMessage(string $xml): array
    {
        $totalXml = simplexml_load_string($xml);

        $annotations = [];
        $messages = [];
        $fields = [];

        foreach ($totalXml->revision as $revision) {
            $annotations[] = $revision->annotations->annotation;
            $messages[] = $revision->attributes();
            $fields[] = $revision->fields;
        }

        return [
            'annotations' => json_decode(json_encode($annotations), true),
            'messages' => json_decode(json_encode($messages), true),
            'fields' => json_decode(json_encode($fields), true),
        ];
    }
}
