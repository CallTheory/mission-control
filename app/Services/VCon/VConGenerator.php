<?php

declare(strict_types=1);

namespace App\Services\VCon;

use App\Models\Stats\Calls\Call;
use App\Models\Stats\Calls\Messages;
use App\Models\Stats\Calls\Recordings;
use App\Models\Stats\Calls\Transcriptions;
use Carbon\Carbon;
use Illuminate\Support\Str;

class VConGenerator
{
    /**
     * Generate a vCon record from a call
     */
    public function generateFromCall(
        string $callId,
        ?int $teamId = null,
        bool $includeRecording = true,
        bool $includeTranscription = true
    ): array {
        // Fetch call data
        $callData = $this->getCallData($callId, $teamId);
        
        if (!$callData) {
            throw new \RuntimeException("Call not found: {$callId}");
        }
        
        // Build vCon structure
        $vcon = [
            'vcon' => '0.0.1',
            'uuid' => Str::uuid()->toString(),
            'created_at' => Carbon::now()->toIso8601String(),
            'updated_at' => Carbon::now()->toIso8601String(),
            'subject' => "Call {$callId}",
            'redacted' => 0,
            'appended' => 0,
            'group' => [],
            'parties' => $this->buildParties($callData),
            'dialog' => $this->buildDialog($callData, $includeRecording, $includeTranscription),
            'analysis' => $this->buildAnalysis($callData),
            'attachments' => []
        ];
        
        // Add recording attachment if available
        if ($includeRecording && isset($callData['recording'])) {
            $vcon['attachments'][] = $this->buildRecordingAttachment($callData['recording']);
        }
        
        return $vcon;
    }
    
    /**
     * Get call data from the database
     */
    private function getCallData(string $callId, ?int $teamId = null): ?array
    {
        // This would typically query your actual database
        // For now, returning mock data structure based on your models
        
        try {
            $call = new Call(['callId' => $callId]);
            $messages = $call->messages();
            $history = $call->history();
            $statistics = $call->statistics();
            
            // Get recording if exists
            $recordings = new Recordings(['callId' => $callId]);
            $recordingData = $recordings->details();
            
            // Get transcription if exists
            $transcriptions = new Transcriptions(['callId' => $callId]);
            $transcriptionData = $transcriptions->details();
            
            return [
                'id' => $callId,
                'messages' => $messages,
                'history' => $history,
                'statistics' => $statistics,
                'recording' => $recordingData,
                'transcription' => $transcriptionData,
                'start_time' => Carbon::now()->subMinutes(5)->toIso8601String(),
                'end_time' => Carbon::now()->toIso8601String(),
                'duration' => 300,
                'caller_number' => '+15551234567',
                'called_number' => '+15557654321',
                'agent_name' => 'Agent Smith',
                'agent_id' => 'agent_001'
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Build parties array for vCon
     */
    private function buildParties(array $callData): array
    {
        $parties = [];
        
        // Caller party
        $parties[] = [
            'tel' => $callData['caller_number'] ?? 'unknown',
            'name' => 'Caller',
            'role' => 'caller',
            'meta' => [
                'role' => 'customer'
            ]
        ];
        
        // Agent party
        if (isset($callData['agent_name'])) {
            $parties[] = [
                'tel' => $callData['called_number'] ?? 'unknown',
                'name' => $callData['agent_name'],
                'role' => 'agent',
                'meta' => [
                    'role' => 'agent',
                    'agent_id' => $callData['agent_id'] ?? null
                ]
            ];
        }
        
        return $parties;
    }
    
    /**
     * Build dialog array for vCon
     */
    private function buildDialog(array $callData, bool $includeRecording, bool $includeTranscription): array
    {
        $dialog = [];
        
        // Add call leg
        $dialogItem = [
            'type' => 'recording',
            'start' => $callData['start_time'],
            'end' => $callData['end_time'],
            'duration' => $callData['duration'],
            'parties' => [0, 1], // Indices of parties involved
            'originator' => 0,
            'mimetype' => 'audio/x-wav',
            'filename' => "call_{$callData['id']}.wav"
        ];
        
        // Add recording URL if available
        if ($includeRecording && isset($callData['recording']['url'])) {
            $dialogItem['url'] = $callData['recording']['url'];
        }
        
        // Add transcription if available
        if ($includeTranscription && isset($callData['transcription']['text'])) {
            $dialogItem['transcript'] = $callData['transcription']['text'];
            $dialogItem['transcript_language'] = 'en-US';
        }
        
        $dialog[] = $dialogItem;
        
        // Add messages as dialog items if available
        if (isset($callData['messages']) && is_array($callData['messages'])) {
            foreach ($callData['messages'] as $message) {
                $dialog[] = [
                    'type' => 'text',
                    'start' => $message['timestamp'] ?? $callData['start_time'],
                    'parties' => [0],
                    'mimetype' => 'text/plain',
                    'body' => $message['content'] ?? ''
                ];
            }
        }
        
        return $dialog;
    }
    
    /**
     * Build analysis array for vCon
     */
    private function buildAnalysis(array $callData): array
    {
        $analysis = [];
        
        // Add call statistics as analysis
        if (isset($callData['statistics'])) {
            $analysis[] = [
                'type' => 'statistics',
                'dialog' => 0,
                'vendor' => 'mission-control',
                'product' => 'call-analytics',
                'body' => $callData['statistics']
            ];
        }
        
        // Add sentiment analysis if available
        if (isset($callData['sentiment'])) {
            $analysis[] = [
                'type' => 'sentiment',
                'dialog' => 0,
                'vendor' => 'mission-control',
                'product' => 'sentiment-analyzer',
                'body' => [
                    'sentiment' => $callData['sentiment'],
                    'confidence' => $callData['sentiment_confidence'] ?? 0.0
                ]
            ];
        }
        
        return $analysis;
    }
    
    /**
     * Build recording attachment
     */
    private function buildRecordingAttachment(array $recording): array
    {
        return [
            'type' => 'recording',
            'mimetype' => $recording['mimetype'] ?? 'audio/x-wav',
            'filename' => $recording['filename'] ?? 'recording.wav',
            'url' => $recording['url'] ?? null,
            'size' => $recording['size'] ?? null
        ];
    }
}