{{ $schedule->name }}
================================

Call recordings for the period from {{ $startDate->format('M j, Y g:i A') }} to {{ $endDate->format('M j, Y g:i A') }} ({{ $schedule->timezone }}).

@if(count($recordings) > 0)
This email contains {{ count($recordings) }} recording(s) attached as MP3 files.

----------------------------------------

@foreach($recordings as $index => $recording)
Recording {{ $index + 1 }}: Call ID {{ $recording['call_id'] }}
----------------------------------------

@if($includeCallMetadata)
Client: {{ $recording['client_number'] }} - {{ $recording['client_name'] ?? 'N/A' }}
Call Time: {{ $recording['call_start'] ?? 'N/A' }}
Caller: {{ $recording['caller_name'] ?? 'Unknown' }} ({{ $recording['caller_ani'] ?? 'N/A' }})
Agent: {{ $recording['agent_name'] ?? 'N/A' }} ({{ $recording['agent_initials'] ?? '' }})
Duration: {{ $recording['duration'] ? floor($recording['duration'] / 60) . 'm ' . ($recording['duration'] % 60) . 's' : 'N/A' }}
@endif

@if($includeTranscription && !empty($recording['transcription']))
Transcription:
@if(is_array($recording['transcription']) && isset($recording['transcription']['transcription']))
{{ $recording['transcription']['transcription'] }}
@elseif(is_array($recording['transcription']) && isset($recording['transcription']['segments']))
@foreach($recording['transcription']['segments'] as $segment)
{{ $segment['text'] ?? '' }}
@endforeach
@elseif(is_string($recording['transcription']))
{{ $recording['transcription'] }}
@endif
@endif

Attached file: recording_{{ $recording['call_id'] }}.mp3

----------------------------------------

@endforeach
@else
No recordings were found for the specified time period.
@endif

Thanks,
{{ config('app.name') }}
