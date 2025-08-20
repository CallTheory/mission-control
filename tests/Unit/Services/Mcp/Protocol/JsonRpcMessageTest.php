<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Mcp\Protocol;

use App\Services\Mcp\Protocol\JsonRpcMessage;
use PHPUnit\Framework\TestCase;

class JsonRpcMessageTest extends TestCase
{
    public function test_creates_request_message(): void
    {
        $message = JsonRpcMessage::request('test_method', ['param' => 'value'], 1);
        
        $this->assertEquals('2.0', $message->jsonrpc);
        $this->assertEquals('test_method', $message->method);
        $this->assertEquals(['param' => 'value'], $message->params);
        $this->assertEquals(1, $message->id);
        $this->assertNull($message->result);
        $this->assertNull($message->error);
    }
    
    public function test_creates_notification_message(): void
    {
        $message = JsonRpcMessage::notification('notify_method', ['data' => 'test']);
        
        $this->assertEquals('2.0', $message->jsonrpc);
        $this->assertEquals('notify_method', $message->method);
        $this->assertEquals(['data' => 'test'], $message->params);
        $this->assertNull($message->id);
        $this->assertNull($message->result);
        $this->assertNull($message->error);
    }
    
    public function test_creates_success_response(): void
    {
        $message = JsonRpcMessage::success(['result' => 'data'], 1);
        
        $this->assertEquals('2.0', $message->jsonrpc);
        $this->assertEquals(['result' => 'data'], $message->result);
        $this->assertEquals(1, $message->id);
        $this->assertNull($message->method);
        $this->assertNull($message->params);
        $this->assertNull($message->error);
    }
    
    public function test_creates_error_response(): void
    {
        $message = JsonRpcMessage::error(-32600, 'Invalid Request', 'Additional data', 1);
        
        $this->assertEquals('2.0', $message->jsonrpc);
        $this->assertEquals([
            'code' => -32600,
            'message' => 'Invalid Request',
            'data' => 'Additional data'
        ], $message->error);
        $this->assertEquals(1, $message->id);
        $this->assertNull($message->method);
        $this->assertNull($message->result);
    }
    
    public function test_creates_error_response_without_data(): void
    {
        $message = JsonRpcMessage::error(-32700, 'Parse error', null, 1);
        
        $this->assertEquals([
            'code' => -32700,
            'message' => 'Parse error'
        ], $message->error);
    }
    
    public function test_parses_valid_request_json(): void
    {
        $json = '{"jsonrpc":"2.0","method":"test","params":{"foo":"bar"},"id":1}';
        $message = JsonRpcMessage::parse($json);
        
        $this->assertNotNull($message);
        $this->assertEquals('2.0', $message->jsonrpc);
        $this->assertEquals('test', $message->method);
        $this->assertEquals(['foo' => 'bar'], $message->params);
        $this->assertEquals(1, $message->id);
    }
    
    public function test_parses_valid_response_json(): void
    {
        $json = '{"jsonrpc":"2.0","result":{"data":"test"},"id":1}';
        $message = JsonRpcMessage::parse($json);
        
        $this->assertNotNull($message);
        $this->assertEquals('2.0', $message->jsonrpc);
        $this->assertEquals(['data' => 'test'], $message->result);
        $this->assertEquals(1, $message->id);
    }
    
    public function test_returns_null_for_invalid_json(): void
    {
        $message = JsonRpcMessage::parse('invalid json');
        $this->assertNull($message);
    }
    
    public function test_returns_null_for_wrong_version(): void
    {
        $json = '{"jsonrpc":"1.0","method":"test","id":1}';
        $message = JsonRpcMessage::parse($json);
        $this->assertNull($message);
    }
    
    public function test_converts_to_json(): void
    {
        $message = JsonRpcMessage::request('test_method', ['param' => 'value'], 1);
        $json = $message->toJson();
        $decoded = json_decode($json, true);
        
        $this->assertEquals('2.0', $decoded['jsonrpc']);
        $this->assertEquals('test_method', $decoded['method']);
        $this->assertEquals(['param' => 'value'], $decoded['params']);
        $this->assertEquals(1, $decoded['id']);
    }
    
    public function test_identifies_request(): void
    {
        $request = JsonRpcMessage::request('test', null, 1);
        $notification = JsonRpcMessage::notification('test');
        $response = JsonRpcMessage::success('result', 1);
        
        $this->assertTrue($request->isRequest());
        $this->assertFalse($request->isNotification());
        $this->assertFalse($request->isResponse());
        
        $this->assertTrue($notification->isRequest());
        $this->assertTrue($notification->isNotification());
        $this->assertFalse($notification->isResponse());
        
        $this->assertFalse($response->isRequest());
        $this->assertFalse($response->isNotification());
        $this->assertTrue($response->isResponse());
    }
}