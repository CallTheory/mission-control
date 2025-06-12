<?php

namespace App\Utilities;


use Exception;
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
            $base64Image = Browsershot::html($fullHtml)
               // ->noSandbox()
                ->timeout($timeout)
                ->windowSize($width, $height)
                ->setScreenshotType($format, $quality)
                ->base64Screenshot();
        } catch (Exception $e) {
            $base64Image = $e->getMessage();
        }

        return $base64Image;
    }
}
