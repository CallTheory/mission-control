<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\EnterpriseHost;
use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhoneNumberTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function numbers(): array
    {
        return [
            'already normalized' => ['15551234567', '15551234567'],
            'bare ten digits gains the country code' => ['5551234567', '15551234567'],
            'e164' => ['+1 (555) 123-4567', '15551234567'],
            'dashes and spaces' => ['555-123-4567', '15551234567'],
            'dots' => ['555.123.4567', '15551234567'],
            // .fs files write the recipient with a trailing semicolon.
            'trailing semicolon' => ['9139069098;', '19139069098'],
            'international keeps its own country code' => ['+44 20 7123 4567', '442071234567'],
            'empty' => ['', ''],
            'letters only' => ['not a number', ''],
        ];
    }

    #[DataProvider('numbers')]
    public function test_it_normalizes_to_digits_with_a_country_code(string $input, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($input));
    }

    #[DataProvider('numbers')]
    public function test_enterprise_host_still_normalizes_identically(string $input, string $expected): void
    {
        // EnterpriseHost::normalizeNumber() keys the SMS carrier overrides and now
        // delegates here. Fax provider pins must agree with it exactly, so this pins the
        // delegation rather than trusting it.
        $this->assertSame($expected, EnterpriseHost::normalizeNumber($input));
    }
}
