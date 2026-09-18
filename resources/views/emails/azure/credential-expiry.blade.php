@component('mail::message')
# Azure Credential Expiry

The following Entra ID credentials have crossed an expiry warning threshold since the
last alert. Nothing has been changed in Azure -- Mission Control only reads.

| App | Credential | Type | Expires (UTC) | Days left |
|:----|:-----------|:-----|:--------------|----------:|
@foreach($credentials as $credential)
| **{{ $credential['app_name'] }}** | {{ $credential['credential'] }} | {{ $credential['type'] }} | {{ $credential['expires_at'] }} | {{ $credential['days_remaining'] < 0 ? 'expired' : $credential['days_remaining'] }} |
@endforeach

@php($expired = collect($credentials)->where('days_remaining', '<', 0))
@if($expired->isNotEmpty())
@component('mail::panel')
{{ $expired->count() }} of these {{ $expired->count() === 1 ? 'has' : 'have' }} already expired.
Anything authenticating with {{ $expired->count() === 1 ? 'it' : 'them' }} is failing now.
Expired credentials are not removed by Azure, so they stay listed until someone deletes them.
@endcomponent
@endif

## Where to renew

@foreach($credentials as $credential)
- [{{ $credential['app_name'] }}]({{ $credential['portal_url'] }}) &mdash; {{ $credential['source'] }}, client ID `{{ $credential['app_client_id'] }}`
@endforeach

@component('mail::button', ['url' => $dashboardUrl])
Open the Token Dashboard
@endcomponent

Alerts repeat only when a credential crosses the next threshold ({{ implode(', ', \App\Services\Azure\ExpiryAlerter::THRESHOLDS) }} days).
Acknowledge a credential on the dashboard to silence it entirely.

Thanks,<br>
{{ config('app.name') }}

**Server**: {{ secure_url('/') }}
@endcomponent
