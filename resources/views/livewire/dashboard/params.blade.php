
<div class="w-full lg:w-1/2 mx-auto">
    <div class="mb-10 inline-flex">

        <livewire:dashboard.date-input :input="'start_date'" :title="'Start Date'" />
        <livewire:dashboard.date-input :input="'end_date'" :title="'End Date'"/>

        <x-button class="my-2 mx-2">
            {{ __('Filter Date') }}
        </x-button>

    </div>

</div>
