@php
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Stats\Helpers;
@endphp
<div>
    @if($account->Inactive)
        <h2 class="text-2xl font-bold text-indigo-700 px-2">Account Inactive</h2>
        <p class="text-indigo-900 px-2">
            This account will busy any inbound call because it is marked as <strong><i>Inactive</i></strong>
        </p>
        <hr class="w-full mt-2 border border-gray-300"/>
    @endif
    <div class="p-2 @if($account->Inactive) opacity-50 bg-gray-200 @endif">
        <div class="w-full flex flex-wrap">
            <div class="px-4 sm:px-0 w-full lg:w-1/2">
                <h3 class="text-2xl font-semibold leading-7 text-gray-900 my-1">
                    {{ $account->ClientName }} @if($account->Emergency) <span class="align-middle text-white bg-red-500 rounded-md px-2 py-0.5 text-xs uppercase">Emergency</span>  @endif
                </h3>
                <p class="mt-1 text-xl max-w-2xl text-sm leading-6 text-gray-500 my-1">
                    Account Number {{ $account->ClientNumber }}
                </p>
                @if($account->BillingCode)
                    <p class="text-xl max-w-2xl text-sm leading-6 text-gray-400 my-1">
                        Billing Code {{ $account->BillingCode }}
                    </p>
                @endif
                <p class="text-sm max-w-2xl text-sm leading-4 text-indigo-800 my-1">
                    @php
                        $accountCreated = Carbon::parse($account->Stamp, $switch_timezone)->timezone(request()->user()->timezone ?? 'UTC')
                    @endphp
                    Created {{ $accountCreated->diffForHumans(null, null, false, 3) }}<br>
                    <small>on {{ $accountCreated->format('l F jS g:i:s A T') }}</small>
                </p>
                <p class="text-sm max-w-2xl text-sm leading-4 text-gray-400 my-1">
                    cltId <code>{{ $account->cltId }}</code>
                </p>
                <div class="flex flex-wrap w-full">
                    @forelse($sources as $source)
                        <x-client-source source="{{ $source->Source }}" />
                    @empty
                        <x-client-source source="None" />
                    @endforelse
                </div>
            </div>
            @if($account->AnswerPhrase)
                <div class="w-full lg:w-1/2">
                    <span class="text-xs text-gray-300">Answer Phrase</span>
                    <iframe class="w-full max-h-32 border border-300 rounded-lg shadow p-1" src="{!!  htmlspecialchars("data:text/html," . rawurlencode($account->AnswerPhrase)) !!}"></iframe>
                </div>
            @endif
            <div class="mt-6 border-t border-gray-300 w-full lg:w-1/2">
                <h3 class="text-xs text-gray-300">Account Details</h3>
                <dl class="divide-y divide-gray-100">

                    @php
                    if($account->TimezoneOffset == 0){
                        $tzOffset = 'None';
                    }else{
                        $tzOffset  = Helpers::ticksToSeconds($account->TimezoneOffset)/60 . " minutes";
                    }
                    @endphp

                    <x-client-detail-list-item label="Timezone Offset" :details="$tzOffset" />
                    <x-client-detail-list-item label="Directory Subject" :details="$account->DirectorySubject ?? '—'" />
                    <x-client-detail-list-item label="Directory View" :details="$account->DirectoryView ?? '—' " />
                    @if($account->DirectorySchedule)
                        <x-client-detail-list-item label="Directory View" :details="$account->DirectorySchedule" />
                    @endif
                    <x-client-detail-list-item label="Skill" :details="$account->SkillName ?? '—'" />
                    <x-client-detail-list-item label="Message Purge Time" :details="$account->MsgPurgeTime ?? 'System'" />
                </dl>
            </div>

            <div class="mt-6 border-t border-gray-300 w-full lg:w-1/2">
                <h3 class="text-xs text-gray-300">Account Peripherals</h3>
                <dl class="divide-y divide-gray-100">
                    <x-client-detail-list-item label="Show Specials" :details="$account->ShowSpecials ? 'Yes' : 'No'" />
                    <x-client-detail-list-item label="New Specials" :details="$account->SpecialOldToNew ? 'Yes' : 'No'" />
                    <x-client-detail-list-item label="Save Edited Special" :details="$account->SaveEditedSpecial ? 'Yes' : 'No'" />
                    <x-client-detail-list-item label="Show Info Pages" :details="$account->ShowInfos  ? 'Yes' : 'No'" />
                </dl>
            </div>

            <div class="mt-6 border-t border-gray-300 w-full lg:w-1/2">
                <h3 class="text-xs text-gray-300">Inbound Call Handling</h3>
                <dl class="divide-y divide-gray-100">

                    <x-client-detail-list-item label="DID Limit" :details=" $account->DIDLimit ? $account->DIDLimit : 'None'" />
                </dl>
            </div>

            <div class="mt-6 border-t border-gray-300 w-full lg:w-1/2">
                <h3 class="text-xs text-gray-300">Outbound Call Handling</h3>
                <dl class="divide-y divide-gray-100">
                    <x-client-detail-list-item label="Default Route" :details="$account->DefaultRoute " />
                    @if($account->CallerIdName)
                        <x-client-detail-list-item label="Caller ID Name" :details="$account->CallerIdName" />
                    @endif
                    @if($account->CallerIdNumber)
                        <x-client-detail-list-item label="Caller ID Number" :details="$account->CallerIdNumber" />
                    @endif
                </dl>
            </div>

            @if($greetings)
                <div class="mt-6 border-t border-gray-300 w-full lg:w-1/2">
                    <h3 class="text-xs text-gray-300">Greetings</h3>
                    <dl class="divide-y divide-gray-100">
                        @foreach($greetings as $greeting)
                            <!-- /*label="Carbon::parse($greeting->Stamp, $switch_timezone)->timezone(request()->user()->timezone ?? 'UTC')->format('l, F jS Y A T')"*/ -->
                            @if(strlen($greeting->GreetingName))
                                <x-client-detail-list-item
                                    :label="Str::headline($greeting->GreetingName)"
                                    :details="$greeting->greetingID"
                                    link="/accounts/greetings/{{ $greeting->greetingID }}"
                                />
                            @else
                                <x-client-detail-list-item
                                    label="Unnamed Greeting"
                                    :details="$greeting->greetingID"
                                    link="/accounts/greetings/{{ $greeting->greetingID }}"
                                />
                            @endif

                        @endforeach
                    </dl>
                </div>
            @endif

            <div class="mt-6 border-t border-gray-300 w-full lg:w-1/2">
                <h3 class="text-xs text-gray-300">Agent Interface</h3>
                <dl class="divide-y divide-gray-100">
                    <x-client-detail-list-item label="Log Voice" :details="$account->LogVoice ? 'Yes' : 'No'" />
                    <x-client-detail-list-item label="Screen Capture" :details="$account->ScreenCapture ? 'Yes' : 'No' " />
                    <x-client-detail-list-item label="Perfect Answer" :details="$account->PerfectAnswer ? 'Yes' : 'No' " />
                    <x-client-detail-list-item label="Auto-Connect" :details=" $account->AutoConnect ? 'Yes' : 'No'" />
                </dl>
            </div>

            <div class="mt-6 border-t border-gray-300 w-full lg:w-1/2">
                <h3 class="text-xs text-gray-300">Voice Details</h3>
                <dl class="divide-y divide-gray-100">
                    <x-client-detail-list-item label="Logger Beep" :details="$account->LoggerBeep ? $account->LoggerBeepInterval . 's' : 'No'"/>
                    <x-client-detail-list-item label="PCI Compliance" :details="$account->PciCompliance ? 'Yes' : 'No'"/>
                    <x-client-detail-list-item label="Play Quality Prompt" :details="$account->PlayQualityPrompt ? 'Yes' : 'No'"/>
                </dl>
            </div>

            <div class="mt-6 border-t border-gray-300 w-full lg:w-1/2">
                <h3 class="text-xs text-gray-300">Auto-Answer</h3>
                <dl class="divide-y divide-gray-100">

                    <x-client-detail-list-item label="Re-Assign Time" :details="$account->ReassignTime" />
                    <x-client-detail-list-item label="Auto-Answer Rings" :details="$account->AutoAnswerRings " />
                    <x-client-detail-list-item label="Auto-Answer Interval" :details="$account->AutoAnswerInterval " />
                    <x-client-detail-list-item label="Music On-Hold After Auto-Answer" :details="$account->MusicOnHoldAfterAutoAnswer " />
                    <x-client-detail-list-item label="Announce Calls In Queue" :details="$account->AnnounceCallsInQue" />
                    <x-client-detail-list-item label="Announce ATTA" :details="$account->AnnounceATTA? 'Yes' : 'No'" />
                    <x-client-detail-list-item label="Repeat ATTA" :details="$account->RepeatATTA ? 'Yes' : 'No'" />
                </dl>
            </div>

            <div class="mt-6 border-t border-gray-300 w-full lg:w-1/2">
                <h3 class="text-xs text-gray-300">Script Assignments</h3>
                <dl class="divide-y divide-gray-100">

                    <x-client-detail-list-item label="Operator Script" :details="$account->ScriptName" />

                    @if($account->AcdScriptName)
                        <x-client-detail-list-item label="ACD Script" :details="$account->AcdScriptName" />
                    @endif
                    @if($account->WebScriptName)
                        <x-client-detail-list-item label="Web Script" :details="$account->WebScriptName" />
                    @endif
                    @if($account->WebMessagingScriptName)
                        <x-client-detail-list-item label="Web Messaging Script" :details="$account->WebMessagingScriptName" />
                    @endif
                    @if($account->MergeCommScriptName)
                        <x-client-detail-list-item label="MergeComm Script" :details="$account->MergeCommScriptName" />
                    @endif
                </dl>
            </div>

            <div class="mt-6 border-t border-gray-300 w-full lg:w-1/2">
                <h3 class="text-xs text-gray-300">Message Handling</h3>
                <dl class="divide-y divide-gray-100">

                    <x-client-detail-list-item label="Select Next Undelivered Message When Deleted" :details="$account->SelectNextUndelMsgWhenDel ? 'Yes' : 'No'" />
                    <x-client-detail-list-item label="Done Key Cancels Script" :details="$account->DoneKeyCancelsScript ? 'Yes' : 'No'" />
                    <x-client-detail-list-item label="Hangup Removes Work Area" :details="$account->HangupRemovesWorkArea ? 'Yes' : 'No'" />
                    <x-client-detail-list-item label="Transfer Conference Removes Work Area" :details="$account->TransferConfRemovesWorkArea ? 'Yes' : 'No'" />
                </dl>
            </div>

        </div>

        <div class="mt-6 border-t border-gray-300">
            <dl class="divide-y divide-gray-100">
                @foreach($account as $label => $detail)
                    @if(!in_array($label,
                        ['cltId', 'ClientName','ClientNumber', 'BillingCode', 'Stamp', 'MsgPurgeTime', 'AutoAnswerRings',
                        'ReassignTime', 'Skill', 'ScriptName', 'AcdScriptName', 'WebScriptName', 'WebMessagingScriptName',
                        'DefaultRoute', 'Emergency', 'AnswerPhrase', 'DirectorySubject', 'DirectoryView', 'DirectorySchedule',
                        'MergeCommScriptName', 'LogVoice', 'PerfectAnswer','AutoConnect','LoggerBeep','LoggerBeepInterval','PciCompliance',
                        'SkillName','CallerIdName','CallerIdNumber','ScreenCapture','WebScriptName','WebMessagingScriptName',
                        'AutoAnswerInterval','DoneKeyCancelsScript', 'HangupRemovesWorkArea', 'TransferConfRemovesWorkArea',
                        'MusicOnHoldAfterAutoAnswer','DIDLimit','AnnounceATTA', 'RepeatATTA','AnnounceCallsInQue',
                        'PlayQualityPrompt','ShowSpecials', 'ShowInfos','SpecialOldToNew','SelectNextUndelMsgWhenDel','SaveEditedSpecial',
                        'TimezoneOffset','Inactive'
                        ]))
                        <div class="px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
                            <dt class="text-sm font-semibold leading-6 text-gray-900">{{ Str::headline($label) }}</dt>
                            <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2  lg:col-span-1 sm:mt-0">
                                {!! $detail !!}
                            </dd>
                        </div>
                    @endif
                @endforeach


            </dl>
        </div>
    </div>

</div>
