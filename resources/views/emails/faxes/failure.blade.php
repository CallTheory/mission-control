@component('mail::message')
# Fax Failure Notification

We had an issue submitting a fax through the mFax API.

@component('mail::panel')
This fax will show in Infinity as failed with error <strong>261</strong>. Please contact support for help.
@endcomponent

**Error Details:** {{ $details }}

| Detail | Value |
|-------:|:------|
@foreach($fax as $key => $value)
|  **{{ $key }}** | {{ $value }} |
@endforeach

@component('mail::button', ['url' => secure_url('/')])
Mission Control Login
@endcomponent

Thanks,<br>
{{ config('app.name') }}

**Server**: {{ secure_url('/') }}
@endcomponent
