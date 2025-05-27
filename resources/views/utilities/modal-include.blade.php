@if(!is_null($state['details'] ?? null))
    @include('utilities.widgets.player-setup')
    <div class="mx-2 my-4 p-6" x-data="{ openTab: 1 }">
        <ul class="flex">
            <li @click="openTab = 1" class="-mb-px mr-1">
                <a class="bg-white inline-block py-2 px-4 rounded shadow  font-semibold" :class="openTab === 1 ? 'text-white ':'text-indigo-400 '" href="#">General</a>
            </li>
            <li @click="openTab = 2" class="mr-1">
                <a class="bg-white  inline-block py-2 px-4 rounded shadow  hover:text-indigo-200 font-semibold" :class="openTab === 2 ? 'text-white ':'text-indigo-400 '" href="#">Message</a>
            </li>
            <li @click="openTab = 3" class="mr-1">
                <a class="bg-white inline-block py-2 px-4 rounded shadow  hover:text-indigo-200 font-semibold" :class="openTab === 3 ? 'text-white ':'text-indigo-400 '" href="#">Tracker</a>
            </li>
            <li @click="openTab = 4" class="mr-1">
                <a class="bg-white  inline-block py-2 px-4 rounded shadow  hover:text-indigo-200 font-semibold" :class="openTab === 4 ?'text-white ':'text-indigo-400 '" href="#">Statistics</a>
            </li>
        </ul>
        <div class="w-full pt-4">
            <div class="w-full transition transform duration-700 ease-in-out" x-show="openTab === 1">
                <div class="">
                    @include('utilities.widgets.info')
                </div>
            </div>
            <div  class="w-full transition transform duration-700 ease-in-out" x-show="openTab === 2">
                <div class="flex">
                    @include('utilities.widgets.messages')
                    @include('utilities.widgets.history')
                </div>
            </div>
            <div  class="w-full transition transform duration-700 ease-in-out" x-show="openTab === 3">
                @include('utilities.widgets.tracker')
            </div>
            <div  class="w-full transition transform duration-700 ease-in-out" x-show="openTab === 4">
                <div class="justify-content-center">
                    @include('utilities.widgets.details')
                    @include('utilities.widgets.call-metrics')
                </div>
            </div>
        </div>
    </div>
@else
    <div class="m-4">
        We are having trouble loading this call's information. Try again or verify the <strong>isCallID</strong>.
    </div>
@endif
