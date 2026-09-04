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
                            <div class="rounded-full h-12 w-12 flex items-center justify-center shadow border border-primary uppercase">{{ $agent['Initials'] }}</div>
                            <span class="absolute inset-0 shadow-inner rounded-full" aria-hidden="true"></span>
                        </div>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-surface-fg">{{ $agent['Name'] }}</h1>
                        <p class="text-sm font-normal text-muted ">
                             @php
                                $agent_created = Carbon::parse( $agent['Stamp'], Auth::user()->timezone)
                             @endphp

                            Created <span class="font-semibold cursor-help" title="{{ $agent_created }}">{{ $agent_created->diffForHumans(Carbon::now(Auth::user()->timezone), \Carbon\CarbonInterface::DIFF_ABSOLUTE  , false, 2)  }} ago</span>
                        </p>
                    </div>
                </div>
                <!--
                <div class="mt-6 flex flex-col-reverse justify-stretch space-y-4 space-y-reverse sm:flex-row-reverse sm:justify-end sm:space-x-reverse sm:space-y-0 sm:space-x-3 md:mt-0 md:flex-row md:space-x-3">
                    <button type="button" class="inline-flex items-center justify-center px-4 py-2 border border-border shadow text-sm font-medium rounded-md text-surface-fg-soft bg-surface hover:bg-surface-2 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-100 focus:ring-info">
                        Disqualify
                    </button>
                    <button type="button" class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow text-info-fg bg-info hover:bg-info-hover focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-100 focus:ring-info">
                        Advance to offer
                    </button>
                </div>
                -->
            </div>

            <div class="mt-8 max-w-3xl mx-auto grid grid-cols-1 gap-6 lg:max-w-7xl lg:grid-flow-col-dense lg:grid-cols-3">
                <div class="space-y-6 lg:col-start-1 lg:col-span-2">
                    <!-- Description list-->
                    <section aria-labelledby="applicant-information-title">
                        <div class="bg-surface shadow sm:rounded-lg">
                            <div class="px-4 py-5 sm:px-6">
                                <h2 id="applicant-information-title" class="text-lg leading-6 font-medium text-surface-fg ">
                                    Agent Information
                                </h2>
                                <p class="mt-1 max-w-2xl text-sm text-muted">
                                   Intelligent Series Details
                                </p>
                            </div>
                            <div class="border-t border-border px-4 py-5 sm:px-6">
                                <dl class="grid grid-cols-1 gap-x-4 gap-y-8 sm:grid-cols-3">

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                            Call Limit
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            {{ $agent['CallLimit'] }}
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                            Style
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            {{ $agent['StyleName']  }}

                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                            Default Client
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            {{ $agent['ClientNumber']  }}

                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                            Directory Subject
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            {{ $agent['DirectorySubject']  }}

                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                            Directory View
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            {{ $agent['ViewName']  }}

                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                            Login Failures
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            {{ $agent['LoginFailures'] }}
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                            Locked Out
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            @if($agent['LockedOut'])
                                                <span class="bg-danger text-danger-fg">Locked Out</span>
                                            @else
                                                <span class="bg-success text-success-fg px-1 py-0.5 text-xs rounded">Unlocked</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                            Voice Logger
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            @if($agent['VoiceLogger'])
                                                <span class="bg-success text-success-fg px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-danger text-danger-fg px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                            Auto-Connect
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            @if($agent['AutoConnect'])
                                                <span class="bg-success text-success-fg px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-danger text-danger-fg px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                            New Chat To Foreground
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            @if($agent['NewChatToForeground'])
                                                <span class="bg-success text-success-fg px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-danger text-danger-fg px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                           Flash New Chat
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            @if($agent['FlashNewChat'])
                                                <span class="bg-success text-success-fg px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-danger text-danger-fg px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                            Exclude From Chat
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            @if($agent['ExcludeFromChat'])
                                                <span class="bg-success text-success-fg px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-danger text-danger-fg px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                            Take Call In Waits
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            @if($agent['TakeCallInWaits'])
                                                <span class="bg-success text-success-fg px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-danger text-danger-fg px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                            Docked Chat
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            @if($agent['DockedChat'])
                                                <span class="bg-success text-success-fg px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-danger text-danger-fg px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                            Call Log Access
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            @if($agent['CallLogAccess'])
                                                <span class="bg-success text-success-fg px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-danger text-danger-fg px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                            Call Log Advanced Search
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            @if($agent['CallLogAdvancedSearch'])
                                                <span class="bg-success text-success-fg px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-danger text-danger-fg px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                            MiTeamWeb Layout Edit
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            @if($agent['MiTeamWebLayoutEdit'])
                                                <span class="bg-success text-success-fg px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-danger text-danger-fg px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                            MiTeamWeb Admin
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            @if($agent['MiTeamWebAdmin'])
                                                <span class="bg-success text-success-fg px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-danger text-danger-fg px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                            Screen Capture Access
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            @if($agent['ScreenCaptureAccess'])
                                                <span class="bg-success text-success-fg px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-danger text-danger-fg px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                            Deliver Resumes Dispatch
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            @if($agent['DeliverResumesDispatch'])
                                                <span class="bg-success text-success-fg px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-danger text-danger-fg px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                            Auto Display SideBar
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            @if($agent['AutoDisplaySideBar'])
                                                <span class="bg-accent-2 text-accent-2-fg px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-danger text-danger-fg px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                            Default Dispatch View All
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            @if($agent['DefaultDispatchViewAll'])
                                                <span class="bg-accent-2 text-accent-2-fg px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-danger text-danger-fg px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                            Disable Spy
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            @if($agent['DisableSpy'])
                                                <span class="bg-accent-2 text-accent-2-fg px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-danger text-danger-fg px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                            Notify When Not Ready
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            @if($agent['NotifyWhenNotReady'])
                                                <span class="bg-accent-2 text-accent-2-fg px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-danger text-danger-fg px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                            Maximize Agent On Login
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            @if($agent['MaximizeAgentOnLogin'])
                                                <span class="bg-accent-2 text-accent-2-fg px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-danger text-danger-fg px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                            Use Logout Reasons
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            @if($agent['UseLogoutReasons'])
                                                <span class="bg-accent-2 text-accent-2-fg px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-danger text-danger-fg px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                            Use Not-Ready Reasons
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            @if($agent['UseNotReadyReasons'])
                                                <span class="bg-accent-2 text-accent-2-fg px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-danger text-danger-fg px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                            Allow Toggle Call Recording
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            @if($agent['AllowToggleCallRecording'])
                                                <span class="bg-accent-2 text-accent-2-fg px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-danger text-danger-fg px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-muted">
                                            Filter Monitor By Skill Group
                                        </dt>
                                        <dd class="mt-1 text-sm text-surface-fg ">
                                            @if($agent['AllowToggleCallRecording'])
                                                <span class="bg-accent-2 text-accent-2-fg px-1 py-0.5 text-xs rounded">Enabled</span>
                                            @else
                                                <span class="bg-danger text-danger-fg px-1 py-0.5 text-xs rounded">Disabled</span>
                                            @endif
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </section>
                </div>

                <section aria-labelledby="timeline-title" class="lg:col-start-3 lg:col-span-1 w-full">
                    <div class="bg-surface px-2 py-5 shadow sm:rounded-lg sm:px-6">
                        <h2 id="timeline-title" class="text-lg font-medium text-surface-fg">Dispatch Groups</h2>
                    </div>
                </section>
            </div>
    </div>

</div>
