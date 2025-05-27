@php
    use Carbon\Carbon;
    use Carbon\CarbonInterface;
    use Illuminate\Support\Facades\Auth;

@endphp
<div>

    <div class="relative min-h-screen w-full">

            <div class="max-w-3xl mx-auto md:flex md:items-center md:justify-between md:space-x-5 lg:max-w-7xl ">
                <div class="flex items-center space-x-5">
                    <div class="shrink-0">
                        <div class="relative">
                            <div class="rounded-full h-12 w-12 flex items-center justify-center  shadow border border-indigo-400 uppercase">{{ $agent['Initials'] }}</div>
                            <span class="absolute inset-0 shadow-inner rounded-full" aria-hidden="true"></span>
                        </div>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 0">{{ $agent['Name'] }}</h1>
                        <p class="text-sm font-normal text-gray-500 ">
                             @php
                                $agent_created = Carbon::parse( $agent['Stamp'], Auth::user()->timezone)
                             @endphp

                            Created <span class="font-semibold cursor-help" title="{{ $agent_created }}">{{ $agent_created->diffForHumans(Carbon::now(Auth::user()->timezone), \Carbon\CarbonInterface::DIFF_ABSOLUTE  , false, 2)  }} ago</span>
                        </p>
                    </div>
                </div>
                <!--
                <div class="mt-6 flex flex-col-reverse justify-stretch space-y-4 space-y-reverse sm:flex-row-reverse sm:justify-end sm:space-x-reverse sm:space-y-0 sm:space-x-3 md:mt-0 md:flex-row md:space-x-3">
                    <button type="button" class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 shadow text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-100 focus:ring-blue-500">
                        Disqualify
                    </button>
                    <button type="button" class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow text-white bg-blue-600 hover:bg-blue-700 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-100 focus:ring-blue-500">
                        Advance to offer
                    </button>
                </div>
                -->
            </div>

            <div class="mt-8 max-w-3xl mx-auto grid grid-cols-1 gap-6 lg:max-w-7xl lg:grid-flow-col-dense lg:grid-cols-3">
                <div class="space-y-6 lg:col-start-1 lg:col-span-2">
                    <!-- Description list-->
                    <section aria-labelledby="applicant-information-title">
                        <div class="bg-white  shadow sm:rounded-lg">
                            <div class="px-4 py-5 sm:px-6">
                                <h2 id="applicant-information-title" class="text-lg leading-6 font-medium text-gray-900 ">
                                    Agent Information
                                </h2>
                                <p class="mt-1 max-w-2xl text-sm text-gray-500 0">
                                   Intelligent Series Details
                                </p>
                            </div>
                            <div class="border-t border-gray-300  px-4 py-5 sm:px-6">
                                <dl class="grid grid-cols-1 gap-x-4 gap-y-8 sm:grid-cols-3">

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                            Call Limit
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            {{ $agent['CallLimit'] }}
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                            Style
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            {{ $agent['StyleName']  }}

                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                            Default Client
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            {{ $agent['ClientNumber']  }}

                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                            Directory Subject
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            {{ $agent['DirectorySubject']  }}

                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                            Directory View
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            {{ $agent['ViewName']  }}

                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                            Login Failures
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            {{ $agent['LoginFailures'] }}
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                            Locked Out
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            @if($agent['LockedOut'])
                                                <span class="bg-red-500 text-white">Locked Out</span>
                                            @else
                                                <span class="bg-green-500 text-white px-1 py-0.5 text-xs rounded">Unlocked</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                            Voice Logger
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            @if($agent['VoiceLogger'])
                                                <span class="bg-green-500 text-white px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-red-500 text-white px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                            Auto-Connect
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            @if($agent['AutoConnect'])
                                                <span class="bg-green-500 text-white px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-red-500 text-white px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                            New Chat To Foreground
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            @if($agent['NewChatToForeground'])
                                                <span class="bg-green-500 text-white px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-red-500 text-white px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                           Flash New Chat
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            @if($agent['FlashNewChat'])
                                                <span class="bg-green-500 text-white px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-red-500 text-white px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                            Exclude From Chat
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            @if($agent['ExcludeFromChat'])
                                                <span class="bg-green-500 text-white px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-red-500 text-white px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                            Take Call In Waits
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            @if($agent['TakeCallInWaits'])
                                                <span class="bg-green-500 text-white px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-red-500 text-white px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                            Docked Chat
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            @if($agent['DockedChat'])
                                                <span class="bg-green-500 text-white px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-red-500 text-white px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                            Call Log Access
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            @if($agent['CallLogAccess'])
                                                <span class="bg-green-500 text-white px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-red-500 text-white px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                            Call Log Advanced Search
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            @if($agent['CallLogAdvancedSearch'])
                                                <span class="bg-green-500 text-white px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-red-500 text-white px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                            MiTeamWeb Layout Edit
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            @if($agent['MiTeamWebLayoutEdit'])
                                                <span class="bg-green-500 text-white px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-red-500 text-white px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                            MiTeamWeb Admin
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            @if($agent['MiTeamWebAdmin'])
                                                <span class="bg-green-500 text-white px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-red-500 text-white px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                            Screen Capture Access
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            @if($agent['ScreenCaptureAccess'])
                                                <span class="bg-green-500 text-white px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-red-500 text-white px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                            Deliver Resumes Dispatch
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            @if($agent['DeliverResumesDispatch'])
                                                <span class="bg-green-500 text-white px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-red-500 text-white px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                            Auto Display SideBar
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            @if($agent['AutoDisplaySideBar'])
                                                <span class="bg-teal-500 text-white px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-red-500 text-white px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                            Default Dispatch View All
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            @if($agent['DefaultDispatchViewAll'])
                                                <span class="bg-teal-500 text-white px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-red-500 text-white px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                            Disable Spy
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            @if($agent['DisableSpy'])
                                                <span class="bg-teal-500 text-white px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-red-500 text-white px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                            Notify When Not Ready
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            @if($agent['NotifyWhenNotReady'])
                                                <span class="bg-teal-500 text-white px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-red-500 text-white px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                            Maximize Agent On Login
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            @if($agent['MaximizeAgentOnLogin'])
                                                <span class="bg-teal-500 text-white px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-red-500 text-white px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                            Use Logout Reasons
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            @if($agent['UseLogoutReasons'])
                                                <span class="bg-teal-500 text-white px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-red-500 text-white px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                            Use Not-Ready Reasons
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            @if($agent['UseNotReadyReasons'])
                                                <span class="bg-teal-500 text-white px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-red-500 text-white px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                            Allow Toggle Call Recording
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            @if($agent['AllowToggleCallRecording'])
                                                <span class="bg-teal-500 text-white px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-red-500 text-white px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500 0">
                                            Filter Monitor By Skill Group
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 ">
                                            @if($agent['AllowToggleCallRecording'])
                                                <span class="bg-teal-500 text-white px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-red-500 text-white px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </section>
                </div>

                <section aria-labelledby="timeline-title" class="lg:col-start-3 lg:col-span-1 w-full">
                    <div class="bg-white  px-2 py-5 shadow sm:rounded-lg sm:px-6">
                        <h2 id="timeline-title" class="text-lg font-medium text-gray-900">Dispatch Groups</h2>
                    </div>
                </section>
            </div>
    </div>

</div>
