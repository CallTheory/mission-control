<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class TermsOfServiceControllerTest extends TestCase
{
    private string $termsPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up the terms.md file path
        $this->termsPath = resource_path('markdown/terms.md');

        // Create the directory if it doesn't exist
        File::ensureDirectoryExists(dirname($this->termsPath));

        // Create a temporary terms.md file for testing
        File::put($this->termsPath, '# Test Terms of Service');
    }

    protected function tearDown(): void
    {
        // Clean up the temporary terms.md file
        if (File::exists($this->termsPath)) {
            File::delete($this->termsPath);
        }

        parent::tearDown();
    }

    public function test_terms_of_service_page_can_be_rendered(): void
    {
        $response = $this->get(route('terms.show'));

        $response->assertStatus(200);
        $response->assertViewIs('terms');
        $response->assertViewHas('terms');
        $response->assertSee('Test Terms of Service');
    }
}
