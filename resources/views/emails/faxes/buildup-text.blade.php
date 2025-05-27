# Fax Buildup Warning

One or more of the monitored fax processing folders has a buildup of files older than 15 minutes.

> This typically means the Amtelco Intelligent Series Fax Service or the Mission Control mFax integration are not processing faxes.

Folders with file buildup:

@foreach($paths as $path)
- {{ $path }}\n
@endforeach

Thanks,<br>
{{ config('app.name') }}

**Server**: {{ secure_url('/') }}
@endcomponent
