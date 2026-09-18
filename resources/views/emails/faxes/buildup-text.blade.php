# Fax Buildup Warning

One or more of the monitored fax processing folders has a buildup of files older than 15 minutes.

> This typically means the Amtelco Intelligent Series Fax Service or the Mission Control cloud faxing integration are not processing faxes.

@if(count($stuckFiles))
Stuck files:

@foreach($stuckFiles as $file)
- {{ $file['name'] }} ({{ $file['folder'] }}) — account {{ $file['account'] ?? 'Unknown' }}, waiting since {{ \Carbon\Carbon::parse($file['modified_at'])->diffForHumans() }}
@endforeach

@endif
Folders with file buildup:

@foreach($paths as $path)
- {{ $path }}
@endforeach

Review the fax spool: {{ $spoolUrl }}

Thanks,
{{ config('app.name') }}

**Server**: {{ secure_url('/') }}
