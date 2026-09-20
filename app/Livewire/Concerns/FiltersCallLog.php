<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Models\Stats\Agents\Listing;
use App\Models\Stats\Calls\CallLog as CallLogStats;
use App\Models\Stats\Helpers;
use App\Models\Stats\Messages\Keywords;
use App\Models\System\Settings;
use Carbon\Carbon;
use Exception;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

/**
 * The call log filter set, shared by the two screens that ask the same question of
 * the Amtelco data and do different things with the answer: Utilities\CallLog lists
 * the calls, Utilities\CsvExport counts and downloads them.
 *
 * They previously carried two copies of these fourteen filters, two copies of the
 * option loading, and two copies of the Session::put block that persisted them --
 * under different key prefixes, so the two screens could not agree on what you had
 * last searched for even though the filters were identical.
 */
trait FiltersCallLog
{
    /** Call types, keyed by id. */
    public array $ck = [];

    /** Agent listing for the filter select. */
    public array $agents = [];

    /** Message keywords for the filter select. */
    public array $keywords = [];

    /** Switch data timezone; the Amtelco timestamps are recorded in it. */
    public string $timezone = 'UTC';

    /**
     * Load the option lists the filter selects need. Each source is optional: an
     * unreachable Amtelco database leaves a select empty rather than breaking the
     * screen, which is how these screens have always behaved.
     */
    public function mountFiltersCallLog(): void
    {
        try {
            $this->agents = (new Listing)->results;
        } catch (Exception) {
            $this->agents = [];
        }

        try {
            $this->keywords = (new Keywords)->results;
        } catch (Exception) {
            $this->keywords = [];
        }

        $ck = Helpers::callTypes();
        asort($ck);
        $this->ck = $ck;

        $this->timezone = Settings::first()?->switch_data_timezone ?? 'UTC';
    }

    /**
     * @return array<int, mixed>
     */
    protected function callLogFilterSchema(): array
    {
        return [
            DateTimePicker::make('start_date')
                ->label('Start Date ('.$this->timezone.')')
                ->seconds(false)
                ->default(now($this->timezone)->subHour()),

            DateTimePicker::make('end_date')
                ->label('End Date ('.$this->timezone.')')
                ->seconds(false)
                ->default(now($this->timezone)),

            TextInput::make('client_number')->label('Client Number'),
            TextInput::make('ani')->label('ANI'),

            Select::make('call_type')->label('Call Type')->options(self::selectOptions($this->ck))->searchable(),

            Select::make('agent')
                ->label('Agent')
                ->options(self::selectOptions(collect($this->agents)->pluck('Name', 'Name')->all()))
                ->searchable(),

            TextInput::make('min_duration')->label('Min. Duration (seconds)')->numeric(),
            TextInput::make('max_duration')->label('Max. Duration (seconds)')->numeric(),

            Select::make('keyword')
                ->label('Keyword')
                ->options(self::selectOptions(collect($this->keywords)->pluck('Keyword', 'Keyword')->all()))
                ->searchable(),

            TextInput::make('keyword_search')->label('Keyword Contains'),

            Checkbox::make('has_messages')->label('Has messages'),
            Checkbox::make('has_recordings')->label('Has recordings'),
            Checkbox::make('has_video')->label('Has screen capture'),
        ];
    }

    /**
     * Amtelco rows carry NULL names and keywords -- an agent record with no name,
     * a keyword row with a null value. Plucking those straight into a Select gives
     * Filament a null label, and Select::isOptionDisabled() type-errors on it while
     * rendering the filter form, taking the whole screen down. Drop the unusable
     * entries and hand Filament strings.
     *
     * @param  array<array-key, mixed>  $options
     * @return array<string, string>
     */
    protected static function selectOptions(array $options): array
    {
        $normalised = [];

        foreach ($options as $value => $label) {
            if ($label === null || $label === '' || $value === null || $value === '') {
                continue;
            }

            $normalised[(string) $value] = (string) $label;
        }

        return $normalised;
    }

    /**
     * Build the stats query from submitted filter values.
     *
     * @param  array<string, mixed>  $f
     */
    protected function callLogQuery(array $f, ?string $sortColumn = null, ?string $sortDirection = null): CallLogStats
    {
        $hasMessages = (bool) ($f['has_messages'] ?? false);
        $hasRecordings = (bool) ($f['has_recordings'] ?? false);
        $hasVideo = (bool) ($f['has_video'] ?? false);

        $team = request()->user()->currentTeam;

        return new CallLogStats(
            Carbon::parse($f['start_date'] ?? now($this->timezone)->subHour())->format('Y-m-d H:i:s'),
            Carbon::parse($f['end_date'] ?? now($this->timezone))->format('Y-m-d H:i:s'),
            $this->timezone,
            $f['client_number'] ?? null,
            $f['ani'] ?? null,
            $f['call_type'] ?? null,
            $f['agent'] ?? null,
            $f['min_duration'] ?? null,
            $f['max_duration'] ?? null,
            $f['keyword'] ?? null,
            $f['keyword_search'] ?? null,
            // CallLogStats allow-lists the sort column and direction before
            // interpolating them into its ORDER BY.
            $sortColumn === 'CallDuration' ? 'CallDuration' : 'statCallStart.Stamp',
            $sortDirection,
            $hasMessages,
            $hasRecordings,
            $hasVideo,
            // "any" is the absence of a specific asset requirement.
            ! $hasMessages && ! $hasRecordings && ! $hasVideo,
            $team->allowed_accounts,
            $team->allowed_billing,
        );
    }
}
