<div class="py-4 min-h-screen text-center">
    @if($unsubscribed)
        <div class="text-2xl text-center mx-auto text-success">
            You have been unsubscribed!
        </div>
        <div class="text-lg text-center mx-auto text-muted">
            You can safely close this window.
        </div>
    @else
        <h2 class="inline font-normal text-xl leading-tight ">
            <img src="{{$campaign->logo }}" alt="{{$campaign->logo_alt}}" class="max-h-20 mx-auto">
            <div class="my-2 mr-4 font-bold">Unsubscribe?</div>
        </h2>
        <div class="my-4">
            <h3 class="text-surface-fg font-semibold">{{ $campaign->title }}</h3>
            <p class="text-muted">{{ $campaign->description }}</p>
        </div>
        <div class="block bg-surface-3 py-8 lg:py-12 text-2xl font-semibold text-surface-fg-soft">
            {{ $email }}
        </div>
        <x-button class="my-4" wire:click="unsubscribe">Unsubscribe</x-button>
        <div class="text-center mx-auto my-2">
            <x-action-message on="unsubscribed" class="text-success">
                You have been unsubscribed.
            </x-action-message>
        </div>
    @endif

    <div class="text-center text-muted mt-8">
        <h3 class="text-md text-surface-fg-soft">{{ $company_details['company'] }}</h3>
        <p class="text-xs">{{ $company_details['address'] }}</p>
        <p class="text-xs">{{ $company_details['address2'] }}</p>
        <p class="text-xs">{{ $company_details['city'] }}, {{ $company_details['state'] }} {{ $company_details['postal'] }} {{ $company_details['country'] }}</p>
        <div class="my-2"></div>
        <p class="text-xs"><a class="text-info font-semibold hover:underline" href="tel:+{{ $company_details['phone'] }}">+{{ $company_details['phone'] }}</a></p>
        <p class="text-xs"><a class="text-info font-semibold hover:underline" href="mailto:{{ $company_details['email'] }}">{{ $company_details['email'] }}</a></p>
    </div>
</div>
