<?php

namespace App\Utilities;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;

class RenderMessageSummary
{
    private const int DEFAULT_WIDTH = 800;

    private const int DEFAULT_HEIGHT = 600;

    private const int DEFAULT_QUALITY = 90;

    private const string DEFAULT_FORMAT = 'png';

    private const int DEFAULT_TIMEOUT = 30;

    public static function htmlToImage(string $content, array $options = []): string
    {
        $width = $options['width'] ?? self::DEFAULT_WIDTH;
        $height = $options['height'] ?? self::DEFAULT_HEIGHT;
        $quality = $options['quality'] ?? self::DEFAULT_QUALITY;
        $format = $options['format'] ?? self::DEFAULT_FORMAT;
        $timeout = $options['timeout'] ?? self::DEFAULT_TIMEOUT;

        $fullHtml = View::make('layouts.message-screenshot', ['slot' => $content])->render();

        try {
            $browsershot = Browsershot::html($fullHtml)
                ->timeout($timeout)
                ->windowSize($width, $height);
            
            // Only set quality for JPEG format, PNG doesn't support it
            if ($format === 'jpeg' || $format === 'jpg') {
                $browsershot->setScreenshotType($format, $quality);
            } else {
                $browsershot->setScreenshotType($format);
            }
            
            // Always use noSandbox in Docker/containerized environments
            // This is safe for server-side rendering where we control the HTML
            $browsershot->noSandbox();
            
            // Try to find Chrome binary installed by Puppeteer
            // Check multiple possible locations
            $possiblePaths = [
                '/home/sail/.cache/puppeteer/chrome-headless-shell/linux-139.0.7258.68/chrome-headless-shell-linux64/chrome-headless-shell',
                '/root/.cache/puppeteer/chrome-headless-shell/linux-139.0.7258.68/chrome-headless-shell-linux64/chrome-headless-shell',
            ];
            
            // Also check for any chrome-headless-shell in puppeteer cache dynamically
            $homeDir = getenv('HOME') ?: '/home/sail';
            $puppeteerCache = $homeDir . '/.cache/puppeteer';
            if (is_dir($puppeteerCache)) {
                $chromeHeadlessDir = glob($puppeteerCache . '/chrome-headless-shell/*/chrome-headless-shell-linux64/chrome-headless-shell');
                if (!empty($chromeHeadlessDir)) {
                    array_unshift($possiblePaths, $chromeHeadlessDir[0]);
                }
            }
            
            // Check additional common locations
            $additionalPaths = [
                '/usr/bin/chromium',
                '/usr/bin/chromium-browser',
                '/usr/bin/google-chrome',
                '/usr/bin/google-chrome-stable',
            ];
            
            $possiblePaths = array_merge($possiblePaths, $additionalPaths);
            
            foreach ($possiblePaths as $path) {
                if (file_exists($path) && is_executable($path)) {
                    $browsershot->setChromePath($path);
                    break;
                }
            }
            
            $base64Image = $browsershot->base64Screenshot();
        } catch (Exception $e) {
            Log::error('Screenshot generation failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $base64Image = '';
        }

        return $base64Image;
    }
}
