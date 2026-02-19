<?php

namespace Tests\Unit\Utilities;

use App\Utilities\RenderMessageSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class RenderMessageSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function chromeIsAvailable(): bool
    {
        $possiblePaths = [
            '/home/sail/.cache/puppeteer/chrome-headless-shell/linux-139.0.7258.68/chrome-headless-shell-linux64/chrome-headless-shell',
            '/root/.cache/puppeteer/chrome-headless-shell/linux-139.0.7258.68/chrome-headless-shell-linux64/chrome-headless-shell',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
        ];

        $homeDir = getenv('HOME') ?: '/home/sail';
        $puppeteerCache = $homeDir . '/.cache/puppeteer';
        if (is_dir($puppeteerCache)) {
            $chromeHeadlessDir = glob($puppeteerCache . '/chrome-headless-shell/*/chrome-headless-shell-linux64/chrome-headless-shell');
            if (!empty($chromeHeadlessDir)) {
                array_unshift($possiblePaths, $chromeHeadlessDir[0]);
            }
        }

        foreach ($possiblePaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Test that screenshot generation returns a base64 encoded PNG image
     */
    public function test_html_to_image_generates_base64_png(): void
    {
        if (!$this->chromeIsAvailable()) {
            $this->markTestSkipped('Chrome binary not available. Run: npx puppeteer browsers install chrome-headless-shell');
        }

        $testContent = '<h1>Test Screenshot</h1><p>This is a test message</p>';
        
        $result = RenderMessageSummary::htmlToImage($testContent);
        
        // Check that result is not empty
        $this->assertNotEmpty($result, 'Screenshot generation should not return empty result');
        
        // Check that result is a valid base64 string
        $decoded = base64_decode($result, true);
        $this->assertNotFalse($decoded, 'Result should be valid base64');
        
        // Check that decoded content is a PNG image
        $this->assertStringStartsWith("\x89PNG", $decoded, 'Decoded content should be a PNG image');
        
        // Verify image can be parsed
        $imageInfo = getimagesizefromstring($decoded);
        $this->assertNotFalse($imageInfo, 'Should be able to parse image info');
        $this->assertEquals('image/png', image_type_to_mime_type($imageInfo[2]), 'Image should be PNG format');
    }

    /**
     * Test that screenshot respects custom dimensions
     */
    public function test_html_to_image_respects_custom_dimensions(): void
    {
        if (!$this->chromeIsAvailable()) {
            $this->markTestSkipped('Chrome binary not available. Run: npx puppeteer browsers install chrome-headless-shell');
        }

        $testContent = '<h1>Custom Size Test</h1>';
        
        $result = RenderMessageSummary::htmlToImage($testContent, [
            'width' => 1024,
            'height' => 768,
        ]);
        
        $decoded = base64_decode($result);
        $imageInfo = getimagesizefromstring($decoded);
        
        $this->assertNotFalse($imageInfo, 'Should be able to parse image info');
        $this->assertEquals(1024, $imageInfo[0], 'Width should be 1024px');
        $this->assertEquals(768, $imageInfo[1], 'Height should be 768px');
    }

    /**
     * Test that screenshot can generate JPEG format
     */
    public function test_html_to_image_can_generate_jpeg(): void
    {
        if (!$this->chromeIsAvailable()) {
            $this->markTestSkipped('Chrome binary not available. Run: npx puppeteer browsers install chrome-headless-shell');
        }

        $testContent = '<h1>JPEG Test</h1>';
        
        $result = RenderMessageSummary::htmlToImage($testContent, [
            'format' => 'jpeg',
            'quality' => 85,
        ]);
        
        $decoded = base64_decode($result);
        
        // Check JPEG signature
        $this->assertStringStartsWith("\xFF\xD8\xFF", $decoded, 'Decoded content should be a JPEG image');
        
        $imageInfo = getimagesizefromstring($decoded);
        $this->assertNotFalse($imageInfo, 'Should be able to parse image info');
        $this->assertEquals('image/jpeg', image_type_to_mime_type($imageInfo[2]), 'Image should be JPEG format');
    }

    /**
     * Test that screenshot handles complex HTML content
     */
    public function test_html_to_image_handles_complex_html(): void
    {
        if (!$this->chromeIsAvailable()) {
            $this->markTestSkipped('Chrome binary not available. Run: npx puppeteer browsers install chrome-headless-shell');
        }

        $complexContent = '
            <div style="padding: 20px; font-family: Arial, sans-serif;">
                <h1 style="color: #333;">Board Check Report</h1>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px; border: 1px solid #ddd;"><strong>Call ID:</strong></td>
                        <td style="padding: 8px; border: 1px solid #ddd;">123456</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #ddd;"><strong>Agent:</strong></td>
                        <td style="padding: 8px; border: 1px solid #ddd;">JD01</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #ddd;"><strong>Message:</strong></td>
                        <td style="padding: 8px; border: 1px solid #ddd;">Emergency call - Patient needs immediate assistance</td>
                    </tr>
                </table>
                <p style="margin-top: 20px; color: #666;">Generated at ' . now()->format('Y-m-d H:i:s') . '</p>
            </div>
        ';
        
        $result = RenderMessageSummary::htmlToImage($complexContent);
        
        $this->assertNotEmpty($result, 'Should handle complex HTML');
        
        $decoded = base64_decode($result);
        $this->assertGreaterThan(1000, strlen($decoded), 'Complex HTML should generate larger image');
        
        $imageInfo = getimagesizefromstring($decoded);
        $this->assertNotFalse($imageInfo, 'Should generate valid image from complex HTML');
    }

    /**
     * Test that screenshot handles special characters properly
     */
    public function test_html_to_image_handles_special_characters(): void
    {
        if (!$this->chromeIsAvailable()) {
            $this->markTestSkipped('Chrome binary not available. Run: npx puppeteer browsers install chrome-headless-shell');
        }

        $contentWithSpecialChars = '
            <div>
                <h1>Special Characters Test</h1>
                <p>Quotes: "double" and \'single\'</p>
                <p>HTML entities: &lt;tag&gt; &amp; &nbsp;</p>
                <p>Unicode: 日本語 العربية emoji 😀</p>
                <p>Math: 2 > 1 && 3 < 4</p>
            </div>
        ';
        
        $result = RenderMessageSummary::htmlToImage($contentWithSpecialChars);
        
        $this->assertNotEmpty($result, 'Should handle special characters');
        
        $decoded = base64_decode($result);
        $imageInfo = getimagesizefromstring($decoded);
        $this->assertNotFalse($imageInfo, 'Should generate valid image with special characters');
    }

    /**
     * Test that Chrome/Puppeteer is properly installed
     */
    public function test_chrome_binary_is_accessible(): void
    {
        if (!$this->chromeIsAvailable()) {
            $this->markTestSkipped('Chrome binary not available. Run: npx puppeteer browsers install chrome-headless-shell');
        }

        // Check possible Chrome binary locations
        $possiblePaths = [
            '/home/sail/.cache/puppeteer/chrome-headless-shell/linux-139.0.7258.68/chrome-headless-shell-linux64/chrome-headless-shell',
            '/root/.cache/puppeteer/chrome-headless-shell/linux-139.0.7258.68/chrome-headless-shell-linux64/chrome-headless-shell',
        ];
        
        // Also check for any chrome-headless-shell in puppeteer cache
        $homeDir = getenv('HOME') ?: '/home/sail';
        $puppeteerCache = $homeDir . '/.cache/puppeteer';
        if (is_dir($puppeteerCache)) {
            $chromeHeadlessDir = glob($puppeteerCache . '/chrome-headless-shell/*/chrome-headless-shell-linux64/chrome-headless-shell');
            if (!empty($chromeHeadlessDir)) {
                array_unshift($possiblePaths, $chromeHeadlessDir[0]);
            }
        }
        
        $foundChrome = false;
        foreach ($possiblePaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                $foundChrome = true;
                break;
            }
        }
        
        $this->assertTrue($foundChrome, 'Chrome binary should be installed and accessible. Run: npx puppeteer browsers install chrome-headless-shell');
    }

    /**
     * Test timeout handling
     */
    public function test_html_to_image_respects_timeout(): void
    {
        if (!$this->chromeIsAvailable()) {
            $this->markTestSkipped('Chrome binary not available. Run: npx puppeteer browsers install chrome-headless-shell');
        }

        $testContent = '<h1>Timeout Test</h1>';
        
        // Test with very short timeout (should still work for simple content)
        $result = RenderMessageSummary::htmlToImage($testContent, [
            'timeout' => 5, // 5 seconds
        ]);
        
        $this->assertNotEmpty($result, 'Should generate screenshot within timeout');
    }
}