<?php

declare(strict_types=1);

namespace App\Services\Sms\Gateways;

use App\Models\DataSource;
use App\Services\Sms\SmsGateway;

/**
 * Shared plumbing for the carrier gateways: reading credentials off the single
 * `DataSource` row, and phone-number formatting.
 *
 * The row is read once per instance, not once per call, so rendering the three
 * provider tiles is three queries rather than thirty. Instances are built per
 * resolution of SmsGatewayManager (which is deliberately not a singleton), so a
 * long-running queue worker still sees credential edits on its next job.
 */
abstract class Gateway implements SmsGateway
{
    private ?DataSource $settings = null;

    private bool $loaded = false;

    protected function settings(): ?DataSource
    {
        if (! $this->loaded) {
            $this->settings = DataSource::first();
            $this->loaded = true;
        }

        return $this->settings;
    }

    /**
     * One credential column, normalised so an empty string reads as absent.
     * Encrypted columns are decrypted by the model cast; see DataSource.
     */
    protected function setting(string $column): ?string
    {
        $value = $this->settings()?->{$column};

        return filled($value) ? (string) $value : null;
    }

    /**
     * Digits only -- the shape Commio/thinQ wants, and the shape numbers are
     * compared in.
     */
    protected function digits(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        // A bare 10-digit number is North American; prepend the country code.
        if (strlen($digits) === 10) {
            $digits = '1'.$digits;
        }

        return $digits;
    }

    /**
     * E.164 -- the shape Twilio and Bandwidth want.
     */
    protected function e164(string $number): string
    {
        return '+'.$this->digits($number);
    }
}
