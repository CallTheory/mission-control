<?php

namespace App\Utilities\Email;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class LinkParser
{
    public static function parse($text): array
    {
        $links = Arr::where(explode(' ', $text), function ($value, $key) {
            // Get all of our well-formed http links
            return Str::startsWith($value, '<http') && Str::endsWith($value, '>');
        });

        $clean = [];
        foreach ($links as $link) {
            $clean[] = substr($link, 1, strlen($link) - 2);
        }

        return $clean;
    }
}
