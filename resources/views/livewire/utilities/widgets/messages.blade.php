@php
use App\Models\Stats\Helpers;
use Illuminate\Support\Str;
@endphp

@if(count($messages) && is_object($messages[0]))
<div class="w-1/2 mb-2 min-h-full block border border-gray-300 rounded">
    @foreach($messages as $message)
        <div>
        @if(!is_null($message->msgId))

            <div x-data="{ currentMessageRevision: -1 }">
                <div class="flex px-2  py-2 my-2 align-middle border-b border-gray-300">
                    <svg class="w-5 h-5 mx-0 inline-flex text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <strong>Message <code class="font-normal ml-2 border rounded px-1 py-0.5 bg-gray-100 text-xs">msgId&middot;{{ $message->msgId }}</code></strong>

                    @if(count($revisions) > 0)
                        <select x-model="currentMessageRevision" class="border rounded border-gray-300 shadow text-xs my-0 ml-2">
                            <option value="-1" selected>Summary</option>
                            @foreach(new ArrayIterator(array_reverse($revisions, true)) as $order => $revision)
                                <option value="{{ $order }}">Revision {{ $order+1 }} - {{ $revision['timestamp'] }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>

                <div x-show="currentMessageRevision == -1" class="block text-black rounded px-2 py-2">

                    @if(count($revisions) > 0)
                        <h3 class="text-sm font-semibold text-indigo-500 text-left mb-4">Viewing &rarr; Summary</h3>
                    @endif

                    @if(strlen(trim($message->Summary)) > 0)
                        <code class="block p-4 border rounded shadow bg-gray-100 text-sans break-words">{!! Helpers::formatMessageSummary($message->Summary) !!}</code>
                    @else
                        <code class="block p-4 border rounded shadow bg-gray-100 text-sans break-words">{{ $message->Index }}</code>
                    @endif

                    <div class="flex flex-wrap mt-4">

                        @if($message->Urgent)
                            <div class="flex rounded text-red-900 bg-red-400 px-2 py-1 text-xs my-1 mr-2 whitespace-nowrap">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>&nbsp;Urgent
                            </div>
                        @endif
                        @if($message->Delivered)
                            <div class="flex rounded text-green-900 bg-green-400 px-2 py-1 text-xs my-1 mr-2 whitespace-nowrap">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>&nbsp;Delivered
                            </div>
                        @endif
                        @if($message->Voice)
                            <div class="flex rounded text-indigo-200 bg-indigo-500 px-2 py-1 text-xs my-1 mr-2 whitespace-nowrap">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"></path></svg>&nbsp;Voice
                            </div>
                        @endif
                        @if($message->Played)
                            <div class="flex rounded text-blue-900 bg-blue-400 px-2 py-1 text-xs my-1 mr-2 whitespace-nowrap">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>&nbsp;Played
                            </div>
                        @endif
                        @if($message->Discarded)
                            <div class="flex rounded text-gray-200 bg-gray-800 px-2 py-1 text-xs my-1 mr-2 whitespace-nowrap">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M6.707 4.879A3 3 0 018.828 4H15a3 3 0 013 3v6a3 3 0 01-3 3H8.828a3 3 0 01-2.12-.879l-4.415-4.414a1 1 0 010-1.414l4.414-4.414zm4 2.414a1 1 0 00-1.414 1.414L10.586 10l-1.293 1.293a1 1 0 101.414 1.414L12 11.414l1.293 1.293a1 1 0 001.414-1.414L13.414 10l1.293-1.293a1 1 0 00-1.414-1.414L12 8.586l-1.293-1.293z" clip-rule="evenodd"></path></svg>&nbsp;Discarded
                            </div>
                        @endif
                        @if($message->Exported)
                            <div class="flex rounded text-purple-900 bg-purple-400 px-2 py-1 text-xs my-1 mr-2 whitespace-nowrap">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>&nbsp;Exported
                            </div>
                        @endif
                        @if($message->Archived)
                            <div class="flex rounded text-cyan-900 bg-cyan-400 px-2 py-1 text-xs my-1 mr-2 whitespace-nowrap" >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>&nbsp;Archived
                            </div>
                        @endif
                        @if($message->Sent)
                            <div class="flex rounded text-blue-700 bg-blue-200 px-2 py-1 text-xs my-1 mr-2 whitespace-nowrap ">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>&nbsp;Sent
                            </div>
                        @endif
                        @if($message->Held)
                            <div class="flex rounded text-yellow-900 bg-yellow-400 px-2 py-1 text-xs my-1 mr-2 whitespace-nowrap">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>&nbsp;Held
                            </div>
                        @endif
                        @if($message->Special)
                            <div class="flex rounded px-2 py-1 text-cyan-700 bg-cyan-200 text-xs my-1 mr-2 whitespace-nowrap">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"></path></svg>&nbsp;Special
                            </div>
                        @endif
                        @if($message->copiedFrom)
                            <div class="flex rounded text-gray-700 bg-gray-300 px-2 py-1 text-xs my-1 mr-2 whitespace-nowrap">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>&nbsp;Copied From
                            </div>
                        @endif
                        @if($message->forwardedFrom)
                            <div class="flex rounded text-gray-700 bg-gray-300 px-2 py-1 text-xs my-1 mr-2 whitespace-nowrap">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>&nbsp;Forwarded From
                            </div>
                        @endif

                        @if($messageKeywords)
                            @foreach($messageKeywords as $kws)
                                @if($kws->msgId === $message->msgId)
                                    <div class="flex rounded text-gray-800 bg-white border border-gray-300 px-2 py-1 text-xs my-1 mr-2 whitespace-nowrap">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
                                        </svg>
                                        &nbsp; {{ $kws->Keyword }} : {{ $kws->Value }}

                                    </div>
                                @endif
                            @endforeach
                        @endif
                    </div>

                </div>


                @foreach($revisions as $order => $revision)
                    <div x-show="currentMessageRevision == {{ $order }}" class="block text-black rounded px-2 py-2 mx-2">

                        <div x-data="{ currentTab: 1}">
                            <!-- Tabs -->
                            <ul class="flex space-x-2">
                                <li class="mr-2">
                                    <h3 class="text-sm font-semibold text-indigo-500 text-left mb-2">Viewing &rarr; Revision {{ $order+1 }}</h3>
                                </li>
                                <li  @click="currentTab = 1"
                                     class="px-2"
                                     :class="{ 'border-b border-indigo-300': currentTab === 1  }">
                                    <button href="#">Summary</button>
                                </li>
                                @if(strlen(trim($revision['diff'])) > 0)
                                    <li @click="currentTab = 2"
                                        class="px-2"
                                        :class="{ 'border-b border-indigo-300': currentTab === 2  }">
                                        <button href="#">Diff</button>
                                    </li>
                                @endif
                                @if(count($revision['annotations']) > 0)
                                    <li @click="currentTab = 3"
                                        class="px-2"
                                        :class="{ 'border-b border-indigo-300': currentTab === 3  }">
                                        <button href="#">Annotations</button>
                                    </li>
                                @endif
                                <li @click="currentTab = 4"
                                    class="px-2"
                                    :class="{ 'border-b border-indigo-300': currentTab === 4  }">
                                    <button href="#">Fields</button>
                                </li>
                            </ul>

                            <!-- Tab content -->
                            <div class="my-4">
                                <div x-show="currentTab === 1">
                                    <code class="block p-4 border rounded shadow bg-gray-100 text-sans break-words">{!! Helpers::formatMessageSummary($revision['summary']) !!}</code>
                                </div>
                                <div x-show="currentTab === 2">
                                    @if(strlen(trim($revision['diff'])) > 0)
                                        {!! $revision['diff'] !!}
                                        <hr class="my-4 border border-gray-300" />
                                        {!! $revision['diff2'] !!}
                                        <hr class="my-4 border border-gray-300" />
                                        {!! $revision['diff3'] !!}
                                    @else
                                        <div class="my-4 block text-gray-500">No diff generated for this revision</div>
                                    @endif

                                </div>
                                <div x-show="currentTab === 3">
                                    @forelse($revision['annotations'] as $annotationStamp => $annotationMessage )
                                        <strong>{{ Str::headline($annotationStamp) }}</strong> &rarr; {{ $annotationMessage }}<br>
                                    @empty
                                        <div class="my-2 block text-xs text-gray-500">No annotations</div>
                                    @endforelse
                                </div>
                                <div x-show="currentTab === 4">
                                    <table class="text-sm py-4 my-4 table-auto border divider divide-gray-300 border-gray-300 w-full bg-gray-50">
                                        <tbody class="my-4 px-4 border rounded">
                                            @foreach($revision['fields'] as $fieldkey => $fieldvalue)
                                                <tr>
                                                    <th class="py-1 text-right {{ is_array($fieldvalue) ? 'text-gray-400 ' : 'text-gray-900' }}">&nbsp;{{ $fieldkey }}</th>
                                                    <td class="px-2 {{ is_array($fieldvalue) ? 'text-gray-300 ' : 'text-indigo-500' }}">&rarr;</td>
                                                    <td class="text-left"> {{ is_array($fieldvalue) ? '' : $fieldvalue }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    @endforeach

</div>

@endif
