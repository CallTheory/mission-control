<div>
    <div class="block w-full min-w-full">
        <div class="block bg-white rounded border border-gray-300 shadow space-y-2 w-full my-4 py-4">
            {{ $users->links() }}
            <ul role="list" class="divide-y divide-gray-100">
                @forelse($users as $user)
                    <li class="relative flex justify-between gap-x-6 px-4 py-5 hover:bg-gray-50 sm:px-6 lg:px-8">
                        <div class="flex min-w-0 gap-x-4">
                            <img class="h-12 w-12 flex-none rounded-full bg-gray-50" src="{{ $user->profile_photo_url ?? '/images/call-theory.svg' }}" alt="{{ $user->name }}">
                            <div class="min-w-0 flex-auto">
                                <p class="text-sm font-semibold leading-6 text-gray-900">
                                    <a href="/system/users/{{ $user->id }}">
                                        <span class="absolute inset-x-0 -top-px bottom-0"></span>
                                        {{ $user->name }}
                                    </a>
                                </p>
                                <p class="flex text-xs leading-5 text-gray-500">
                                    {{ $user->email }}
                                </p>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center">
                            @foreach($user->allTeams() as $team)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border
                                @if($team->personal_team)  border-gray-300 bg-gray-200 text-gray-800 @else border-indigo-400 bg-indigo-100 text-indigo-800 @endif mr-1">
                                    {{ $team->name }}
                                </span>
                            @endforeach

                            <a href="/system/users/{{ $user->id }}">
                                <svg class="h-5 w-5 flex-none text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        </div>
                    </li>
                @empty
                    <li></li>
                @endforelse
            </ul>
            {{ $users->links() }}
        </div>
    </div>
</div>
