<div class="border-b border-gray-300">
    <div class="sm:flex sm:items-baseline">
        <h3 class="text-base font-semibold leading-6 text-gray-900">Cloud Faxing</h3>
        <div class="mt-4 sm:ml-10 sm:mt-0">
            <nav class="-mb-px flex space-x-8">
                @php
                    $current = "border-indigo-500 text-indigo-600";
                    $default = "order-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700";

                    $aria_current = 'aria-current="page"';
                @endphp

                @if(request()->is('utilities/cloud-faxing'))
                    <a href="/utilities/cloud-faxing" {{ $aria_current }} class="whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium {{ $current }}" >mFax</a>
                    <a href="/utilities/cloud-faxing/ringcentral" class="{{ $default }} whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium">RingCentral</a>
                @else
                    <a href="/utilities/cloud-faxing" class="{{ $default }} whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium" >mFax</a>
                    <a href="/utilities/cloud-faxing/ringcentral" {{ $aria_current }} class="{{ $current }} whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium">RingCentral</a>
                @endif

            </nav>
        </div>
    </div>
</div>
