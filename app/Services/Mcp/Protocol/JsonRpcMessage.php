<?php

declare(strict_types=1);

namespace App\Services\Mcp\Protocol;

class JsonRpcMessage
{
    public const VERSION = '2.0';
    
    public function __construct(
        public readonly string $jsonrpc = self::VERSION,
        public readonly ?string $method = null,
        public readonly mixed $params = null,
        public readonly mixed $result = null,
        public readonly ?array $error = null,
        public readonly string|int|null $id = null
    ) {}
    
    /**
     * Create a request message
     */
    public static function request(string $method, mixed $params = null, string|int|null $id = null): self
    {
        return new self(
            method: $method,
            params: $params,
            id: $id
        );
    }
    
    /**
     * Create a notification message (request without id)
     */
    public static function notification(string $method, mixed $params = null): self
    {
        return new self(
            method: $method,
            params: $params
        );
    }
    
    /**
     * Create a success response
     */
    public static function success(mixed $result, string|int|null $id = null): self
    {
        return new self(
            result: $result,
            id: $id
        );
    }
    
    /**
     * Create an error response
     */
    public static function error(int $code, string $message, mixed $data = null, string|int|null $id = null): self
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];
        
        if ($data !== null) {
            $error['data'] = $data;
        }
        
        return new self(
            error: $error,
            id: $id
        );
    }
    
    /**
     * Parse a JSON-RPC message from string
     */
    public static function parse(string $json): ?self
    {
        $data = json_decode($json, true);
        
        if (!is_array($data) || !isset($data['jsonrpc']) || $data['jsonrpc'] !== self::VERSION) {
            return null;
        }
        
        return new self(
            jsonrpc: $data['jsonrpc'],
            method: $data['method'] ?? null,
            params: $data['params'] ?? null,
            result: $data['result'] ?? null,
            error: $data['error'] ?? null,
            id: $data['id'] ?? null
        );
    }
    
    /**
     * Convert to JSON string
     */
    public function toJson(): string
    {
        $data = ['jsonrpc' => $this->jsonrpc];
        
        if ($this->method !== null) {
            $data['method'] = $this->method;
        }
        
        if ($this->params !== null) {
            $data['params'] = $this->params;
        }
        
        if ($this->result !== null) {
            $data['result'] = $this->result;
        }
        
        if ($this->error !== null) {
            $data['error'] = $this->error;
        }
        
        if ($this->id !== null) {
            $data['id'] = $this->id;
        }
        
        return json_encode($data);
    }
    
    /**
     * Check if this is a request
     */
    public function isRequest(): bool
    {
        return $this->method !== null && $this->error === null && $this->result === null;
    }
    
    /**
     * Check if this is a notification
     */
    public function isNotification(): bool
    {
        return $this->isRequest() && $this->id === null;
    }
    
    /**
     * Check if this is a response
     */
    public function isResponse(): bool
    {
        return !$this->isRequest();
    }
}