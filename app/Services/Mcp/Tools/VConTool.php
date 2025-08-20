<?php

declare(strict_types=1);

namespace App\Services\Mcp\Tools;

use App\Models\Stats\Calls\Call;
use App\Models\Stats\Calls\Recordings;
use App\Services\VCon\VConGenerator;
use Illuminate\Support\Facades\Auth;

class VConTool implements ToolInterface
{
    /**
     * Get the tool name
     */
    public function getName(): string
    {
        return 'get_vcon_record';
    }
    
    /**
     * Get the tool description
     */
    public function getDescription(): string
    {
        return 'Retrieve a vCon (Virtual Conversation) record for a specific call by its ID. Returns call metadata, participants, timeline, and associated recordings in vCon format.';
    }
    
    /**
     * Get the input schema for the tool
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'callId' => [
                    'type' => 'string',
                    'description' => 'The unique identifier of the call'
                ],
                'includeRecording' => [
                    'type' => 'boolean',
                    'description' => 'Whether to include recording URLs in the vCon',
                    'default' => true
                ],
                'includeTranscription' => [
                    'type' => 'boolean',
                    'description' => 'Whether to include transcription if available',
                    'default' => true
                ]
            ],
            'required' => ['callId']
        ];
    }
    
    /**
     * Execute the tool with given arguments
     */
    public function execute(array $arguments): mixed
    {
        $callId = $arguments['callId'] ?? null;
        $includeRecording = $arguments['includeRecording'] ?? true;
        $includeTranscription = $arguments['includeTranscription'] ?? true;
        
        if (!$callId) {
            throw new \InvalidArgumentException('callId is required');
        }
        
        // Get the current user's team context
        $user = Auth::user();
        $teamId = $user ? $user->current_team_id : null;
        
        // Generate vCon using the service
        $generator = new VConGenerator();
        
        try {
            $vcon = $generator->generateFromCall(
                $callId,
                $teamId,
                $includeRecording,
                $includeTranscription
            );
            
            return $vcon;
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to generate vCon: ' . $e->getMessage());
        }
    }
}