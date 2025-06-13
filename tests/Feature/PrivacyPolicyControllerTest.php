<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class PrivacyPolicyControllerTest extends TestCase
{
    private string $policyPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up the policy.md file path
        $this->policyPath = resource_path('markdown/policy.md');

        // Create the directory if it doesn't exist
        File::ensureDirectoryExists(dirname($this->policyPath));

        // Create a temporary policy.md file for testing
        File::put($this->policyPath, '# Test Privacy Policy');
    }

    protected function tearDown(): void
    {
        // Clean up the temporary policy.md file
        if (File::exists($this->policyPath)) {
            File::delete($this->policyPath);
        }

        parent::tearDown();
    }

    public function test_privacy_policy_page_can_be_rendered(): void
    {
        $response = $this->get(route('policy.show'));

        $response->assertStatus(200);
        $response->assertViewIs('policy');
        $response->assertViewHas('policy');
        $response->assertSee('Test Privacy Policy');
    }
}
