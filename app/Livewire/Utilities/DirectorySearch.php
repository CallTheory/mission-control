<?php

declare(strict_types=1);

namespace App\Livewire\Utilities;

use App\Models\Stats\Subjects\Listings\ContactCisco;
use App\Models\Stats\Subjects\Listings\ContactEmail;
use App\Models\Stats\Subjects\Listings\ContactFax;
use App\Models\Stats\Subjects\Listings\ContactPhone;
use App\Models\Stats\Subjects\Listings\ContactSecureMessaging;
use App\Models\Stats\Subjects\Listings\ContactSms;
use App\Models\Stats\Subjects\Listings\ContactTapPager;
use App\Models\Stats\Subjects\Listings\ContactVocera;
use App\Models\Stats\Subjects\Listings\ContactWctp;
use App\Support\Tables\StatRecords;
use Exception;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\View\View;
use Livewire\Component;

class DirectorySearch extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /**
     * Contact method to search, mapped to the stats class that queries it. Every one
     * of them returns the same four columns, which is what lets a single table serve
     * all nine.
     *
     * @var array<string, class-string>
     */
    private const CONTACT_TYPES = [
        'phone' => ContactPhone::class,
        'email' => ContactEmail::class,
        'fax' => ContactFax::class,
        'vocera' => ContactVocera::class,
        'cisco' => ContactCisco::class,
        'wctp' => ContactWctp::class,
        'tap' => ContactTapPager::class,
        'msm' => ContactSecureMessaging::class,
        'sms' => ContactSms::class,
    ];

    /** @var array<string, string> */
    private const CONTACT_LABELS = [
        'phone' => 'Phone', 'email' => 'Email', 'fax' => 'Fax', 'vocera' => 'Vocera',
        'cisco' => 'Cisco', 'wctp' => 'WCTP', 'tap' => 'TAP Pager',
        'msm' => 'Secure Messaging', 'sms' => 'SMS',
    ];

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (int $page, int $recordsPerPage, ?string $sortColumn, ?string $sortDirection, ?string $search, array $filters) => StatRecords::paginate(
                rows: $this->contactRows($search, $filters['contact_type']['value'] ?? 'phone'),
                page: $page,
                perPage: $recordsPerPage,
                sortColumn: $sortColumn,
                sortDirection: $sortDirection,
            ))
            ->columns([
                TextColumn::make('MethodName')->label('Method Name')->sortable(),
                TextColumn::make('DirectorySubject')->label('Directory Subject')->sortable(),
                TextColumn::make('View')->label('View')->sortable(),
                TextColumn::make('Result')->label('Match')->wrap(),
            ])
            ->filters([
                SelectFilter::make('contact_type')
                    ->label('Contact Method')
                    ->options(self::CONTACT_LABELS)
                    ->default('phone')
                    ->selectablePlaceholder(false),
            ])
            ->searchable()
            ->searchPlaceholder('Search the directory (3 characters minimum)')
            ->defaultSort('MethodName')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Search the directory')
            ->emptyStateDescription('Enter at least three characters to search the selected contact method.');
    }

    /**
     * @return array<int, object>
     */
    private function contactRows(?string $search, string $type): array
    {
        // The underlying queries take the search term as their only argument, so there
        // is nothing to list until one is entered. The old form enforced the same
        // three-character minimum through validation before it would query.
        if ($search === null || mb_strlen(trim($search)) < 3) {
            return [];
        }

        $class = self::CONTACT_TYPES[$type] ?? ContactPhone::class;

        try {
            return (new $class([0 => trim($search)]))->results ?? [];
        } catch (Exception) {
            return [];
        }
    }

    public function render(): View
    {
        return view('livewire.utilities.directory-search');
    }
}
