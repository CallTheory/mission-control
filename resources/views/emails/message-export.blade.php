@component('mail::message')
# {{ $export->name }}

Message export for account **{{ $export->client_number }}**{{ $export->client_name ? ' - ' . $export->client_name : '' }} for the period from **{{ $startDate->format('M j, Y g:i A') }}** to **{{ $endDate->format('M j, Y g:i A') }}** ({{ $export->timezone }}).

This export contains **{{ $messageCount }}** message(s) attached as a CSV file.

@if($export->filter_field)
**Filter applied:** {{ $export->filter_field }} = {{ $export->filter_value }}
@endif

Thanks,<br>
{{ config('app.name') }}
@endcomponent
