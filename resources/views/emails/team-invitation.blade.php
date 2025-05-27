@component('mail::message')
{{ __('You have been invited to join the :team team!', ['team' => $invitation->team->name]) }}

@if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::registration()))
{{ __('An account has been created for you. Please use the Reset Password option below:') }}

@component('mail::button', ['url' => route('password.email')])
    {{ __('Reset Password') }}
@endcomponent


{{ __('Once you have changed your password and logged in, you may accept this invitation by clicking the button below:') }}

@else
{{ __('You may accept this invitation by clicking the button below:') }}
@endif


@component('mail::button', ['url' => $acceptUrl])
{{ __('Accept Invitation') }}
@endcomponent

{{ __('If you did not expect to receive an invitation to this team, you may discard this email.') }}
@endcomponent
