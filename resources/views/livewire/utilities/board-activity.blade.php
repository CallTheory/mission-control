@php
    use Illuminate\Support\Facades\Auth;
    use App\Models\User;
@endphp

<div class="w-full "
     wire:poll.10000ms.visible wire:id="{{ uniqid('widget') }}" >
    <div class="block px-2 py-4 mx-2">
        @include('utilities.board-nav')
    </div>

    <div class="p-2 bg-gray-50 rounded shadow flex">

        <div class="mr-4 pr-4">
            <x-label for="msgId">Filter by MsgId</x-label>
            <x-input value="{{ $msgId }}" id="msgId" type="text" class="mt-1 " wire:model.live="msgId" />
            <x-input-error for="msgId" class="mt-2" />
        </div>

        <div>
            <x-label for="user_id">Or Filter by User</x-label>
            <select id="user_id" name="user_id" class="mt-1  border-gray-300     focus:border-indigo-300 focus:ring focus:ring-indigo-200 rounded-md shadow "
                    wire:model.live="user_id">
                <option value="">All Users</option>
                @foreach( User::all() as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </select>
            <x-input-error for="user_id" class="mt-2" />
        </div>


    </div>

    @if($boardCheckActivity->count())
        <div class="my-2">
            {{ $boardCheckActivity->links() }}
        </div>
        <table class="min-w-full divide-y divide-gray-200  text-left">
            <thead class="">
            <tr class="sticky top-0">
                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Date</th>
                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">User</th>
                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">msgId</th>
                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Activity</th>
            </tr>
            </thead>
            <tbody class="bg-white  divide-y divide-gray-200 ">
            @foreach($boardCheckActivity as $row)
                <tr class="group  transform transition duration-700 text-gray-800 ease-in-out py-2">
                    <td class="text-ellipsis pl-4">
                        <small>{{ $row->created_at->timezone(Auth::user()->timezone ?? 'UTC')->format('m/d/Y g:i:s A T') }} &middot; {{ $row->created_at->timezone(Auth::user()->timezone ?? 'UTC')->diffForHumans() }}</small>
                    </td>
                    <td class="font-bold">
                        @php
                        $userToShow = User::find($row->user_id);
                        @endphp
                        {{ $userToShow->name ?? 'Unknown User' }}
                    </td>
                    <td class="text-ellipsis">
                        @if($row->msgId)
                            <small class="bg-gray-100  rounded px-1 py-0.5 my-1">{{ $row->msgId }}</small>
                        @endif
                    </td>
                    <td class="text-ellipsis">
                        <small>{{ $row->activity_type ?? 'Unknown Activity' }}</small>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="my-2">
            {{ $boardCheckActivity->links() }}
        </div>


    @else
        <div class="my-2">
            <x-alert-success title="No data!" description="There are no board check activity items." />
        </div>
    @endif
</div>
