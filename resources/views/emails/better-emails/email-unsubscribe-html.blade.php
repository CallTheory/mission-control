@component('mail::message')
# Better Emails - Unsubscribe Notification

## {{ $accountNumber }} - {{ $unsubscribeTitle }}

The following email address unsubscribed:

**Email**: {{ $unsubscribeEmail }}

@if(strlen($additionalNotes))
> {{ $additionalNotes }}
@endif

@endcomponent
