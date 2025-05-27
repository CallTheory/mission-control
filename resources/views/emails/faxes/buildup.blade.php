@component('mail::message')
# Fax Buildup Warning

One or more of the monitored fax processing folders has a buildup of files older than 15 minutes.

@component('mail::panel')
This typically means the Amtelco Intelligent Series Fax Service or the Mission Control mFax integration are not processing faxes.
@endcomponent

| Folders with file buildup |
|-------:|
@foreach($paths as $path)
|  **{{ $path }}** |
@endforeach

@component('mail::button', ['url' => secure_url('/')])
Mission Control Login
@endcomponent

Thanks,<br>
{{ config('app.name') }}

**Server**: {{ secure_url('/') }}
@endcomponent
