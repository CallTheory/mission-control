<div>
    @if($isCallID)
        <livewire:utilities.call-lookup lazy="lazy" :isCallID="$isCallID" :key="'board-review-call-'.$isCallID" />
    @else
        <x-alert-warning title="No call linked"
            description="This message has no call ID, so the call detail cannot be shown." />
    @endif
</div>
