@component('mail::message')
# Fax Buildup Warning: {{ $sourceName }}

One or more of the monitored fax processing folders on **{{ $sourceName }}** has a buildup of files older than 15 minutes.

@component('mail::panel')
This typically means the Amtelco Intelligent Series Fax Service or the Mission Control cloud faxing integration are not processing faxes.
@endcomponent

@if(count($stuckFiles))
## Stuck files

| Account | File | Folder | Waiting since |
|:--------|:-----|:-------|:--------------|
@foreach($stuckFiles as $file)
| {{ $file['account'] ?? 'Unknown' }} | **{{ $file['name'] }}** | {{ $file['folder'] }} | {{ \Carbon\Carbon::parse($file['modified_at'])->diffForHumans() }} |
@endforeach

Files that should not be there can be removed from the fax utility page without an SSH session, if you hold the fax spool maintenance permission.
@endif

| Folders with file buildup |
|-------:|
@foreach($paths as $path)
|  **{{ $path }}** |
@endforeach

@component('mail::button', ['url' => $spoolUrl])
Review the Fax Spool
@endcomponent

Thanks,<br>
{{ config('app.name') }}

**Server**: {{ secure_url('/') }}
@endcomponent
