# Fax Failure Notification

We had an issue submitting a fax through the mFax API.

> This fax will show in Infinity as failed with error <strong>261</strong>. Please contact support for help.

**Error Details:** {{ $details }}

Failed-fax details:

@foreach($fax as $key => $value)
- {{ $key }}: {{ $value }}\n
@endforeach

Thanks,<br>
{{ config('app.name') }}

**Server**: {{ secure_url('/') }}
