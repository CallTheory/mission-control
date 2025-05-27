@if(isset($email_details['title']))
# {{ $email_details['title'] }}
@endif

@if(isset($email_details['description']))
{{ $email_details['description'] }}
@endif

@if(isset($email_details['include']['report_metadata']) && $email_details['include']['report_metadata'])
@if(isset($email_details['log']['account']))
**Account** {{ $email_details['log']['account'] }}
@endif
@if(isset($email_details['log']['type']))
**Type** {{ ucwords($email_details['log']['type']) }}
@endif
@if(isset($email_details['log']['date']) &&  isset($email_details['log']['time']))
**Generated** {{ $email_details['log']['date'] }} {{ $email_details['log']['time'] }}
@endif
@endif

@if(isset($emails_details['log']['raw']))
{!! $email_details['log']['raw'] ?? '' !!}
@endif

@if(isset($email_details['button']['link']) && isset($email_details['button']['text']))
[{{ $email_details['button']['text'] }}]({{ $email_details['button']['link'] }})
@endif

[Unsubscribe]({{ $email_details['unsubscribe_link'] }})

{{ $email_details['canspam']['company'] }}

{{ $email_details['canspam']['address'] }}
{{ $email_details['canspam']['address2'] }}
{{ $email_details['canspam']['city'] }}
{{ $email_details['canspam']['state'] }}
{{ $email_details['canspam']['postal'] }}
{{ $email_details['canspam']['country'] }}

[{{ $email_details['canspam']['phone'] }}](tel:+{{ $email_details['canspam']['phone'] }})
[{{ $email_details['canspam']['email']}}](mailto:{{ $email_details['canspam']['email'] }})

