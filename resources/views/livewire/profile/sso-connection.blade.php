<x-action-section>
    <x-slot name="title">
        {{ __('Single Sign-On') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Connect this account to your organisation\'s identity provider.') }}
    </x-slot>

    <x-slot name="content">
        @unless($enabled)
            <div class="text-sm text-muted">
                {{ __('Single sign-on is not enabled for this installation.') }}
            </div>
        @else
            @if($linkedId)
                <h3 class="text-lg font-medium text-surface-fg">
                    {{ __('This account is linked to your identity provider.') }}
                </h3>

                <div class="mt-3 max-w-xl text-sm text-muted">
                    {{ __('The identity provider identifies you as:') }}
                    <code class="mt-2 block break-all rounded bg-surface-2 px-2 py-1 text-xs text-surface-fg">{{ $linkedId }}</code>
                </div>

                <div class="mt-3 max-w-xl text-sm text-muted">
                    {{ __('Unlinking signs you out everywhere else and emails you a password reset link, because single sign-on leaves no password you would know.') }}
                </div>

                <div class="mt-5 flex items-center gap-3">
                    <form method="POST" action="{{ route('profile.sso.link') }}">
                        @csrf
                        <x-secondary-button type="submit">
                            {{ __('Re-link') }}
                        </x-secondary-button>
                    </form>

                    <x-danger-button wire:click="unlink" wire:loading.attr="disabled">
                        {{ __('Unlink') }}
                    </x-danger-button>

                    <x-action-message on="saved">
                        {{ __('Unlinked.') }}
                    </x-action-message>
                </div>
            @else
                <h3 class="text-lg font-medium text-surface-fg">
                    {{ __('This account is not linked yet.') }}
                </h3>

                <div class="mt-3 max-w-xl text-sm text-muted">
                    {{ __('You will be sent to your identity provider to sign in. The assertion it returns must carry the same email address as this account.') }}
                </div>

                <div class="mt-5">
                    <form method="POST" action="{{ route('profile.sso.link') }}">
                        @csrf
                        <x-button type="submit">
                            {{ __('Link Account') }}
                        </x-button>
                    </form>
                </div>
            @endif
        @endunless
    </x-slot>
</x-action-section>
