<?php

declare(strict_types=1);

namespace App\Services\Mcp\Tools;

interface ToolInterface
{
    /**
     * Get the tool name
     */
    public function getName(): string;
    
    /**
     * Get the tool description
     */
    public function getDescription(): string;
    
    /**
     * Get the input schema for the tool
     */
    public function getInputSchema(): array;
    
    /**
     * Execute the tool with given arguments
     */
    public function execute(array $arguments): mixed;
}