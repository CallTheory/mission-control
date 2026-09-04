<div class="border-b border-border">
    <div class="sm:flex sm:items-baseline">
        <h3 class="text-base font-semibold leading-6 text-surface-fg">Cloud Faxing</h3>
        <div class="mt-4 sm:ml-10 sm:mt-0">
            <nav class="-mb-px flex space-x-8">
                @php
                    $current = "border-primary text-primary";
                    $default = "order-transparent text-muted hover:border-border hover:text-surface-fg-soft";

                    $aria_current = 'aria-current="page"';
                @endphp

                @if(request()->is('utilities/cloud-faxing'))
                    @if($mfaxEnabled)
                        <a href="/utilities/cloud-faxing" {{ $aria_current }} class="whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium {{ $current }}" >mFax</a>
                    @endif
                    @if($ringcentralEnabled)
                        <a href="/utilities/cloud-faxing/ringcentral" class="{{ $default }} whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium">RingCentral</a>
                    @endif
                @else
                    @if($mfaxEnabled)
                        <a href="/utilities/cloud-faxing" class="{{ $default }} whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium" >mFax</a>
                    @endif
                    @if($ringcentralEnabled)
                        <a href="/utilities/cloud-faxing/ringcentral" {{ $aria_current }} class="{{ $current }} whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium">RingCentral</a>
                    @endif
                @endif

            </nav>
        </div>
    </div>
</div>
