<?php

declare(strict_types=1);

namespace App\Services\Faxing;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Parses the `.fs` metadata files Amtelco's Intelligent Series Fax Service drops beside
 * each `.cap` payload.
 *
 * This used to live inline and near-identically in both isfax:process commands — the
 * RingCentral one carried a switch-engine branch the mFax one did not, which meant an
 * mFax `.fs` on an Infinity switch parsed no capfile, failed validation and was
 * quarantined to fail/. Extracting one parser fixes that by construction, and stops the
 * format being described in two places that could drift.
 *
 * The format is line-oriented, CRLF or LF, with the fields we need announced by a literal
 * prefix:
 *
 *   $var_def DATA5 "12345"                    the IS job id -> the account
 *   $var_def DATA6 "c:\copia\tosend\IS20.cap" the payload (classic/Genesis only)
 *   $fax_filename ...\IS20.cap                the payload filename
 *   $fax_phone 9139069098;                    the recipient
 *   $fax_status1 2                            the status we must echo back changed
 */
class FsFileParser
{
    /**
     * Infinity does not emit DATA6; the payload name comes from $fax_filename instead and
     * the file itself lives in messages/ rather than tosend/.
     */
    public const ENGINE_INFINITY = 'infinity';

    /**
     * Split a `.fs` file's contents into the fields the send jobs need.
     *
     * Always returns `fsFileName`; every other key is present only if the file declared
     * it, which is what lets validate() report precisely what was missing.
     *
     * @return array<string, string>
     */
    public function parse(string $fsFileName, string $contents, ?string $engine = null): array
    {
        $engine ??= (string) config('app.switch_engine');
        $infinity = $engine === self::ENGINE_INFINITY;

        // Callers reuse this parser across a whole folder, so the result is built fresh
        // per file: fields from a previously-parsed .fs must never leak into a malformed
        // one and slip past validation.
        $isfax = ['fsFileName' => $fsFileName];

        foreach ($this->lines($contents) as $line) {
            if (Str::startsWith($line, '$var_def DATA5')) {
                $isfax['jobID'] = $this->unquote($this->after($line, '$var_def DATA5 '));

                continue;
            }

            // Classic IS names the payload here. Infinity omits the line entirely.
            if (! $infinity && Str::startsWith($line, '$var_def DATA6')) {
                $isfax['capfile'] = $this->unquote($this->basename($this->after($line, '$var_def DATA6 ')));

                continue;
            }

            if (Str::startsWith($line, '$fax_filename')) {
                $filename = $this->basename($this->after($line, '$fax_filename '));

                // The value may be quoted, in which case the name ends at the closing
                // quote and anything after it is not part of the filename.
                if (Str::contains($filename, '"')) {
                    $quoted = explode('"', $filename);
                    $filename = (string) reset($quoted);
                }

                $isfax['filename'] = $filename;

                // With no DATA6 to read, Infinity's payload name is this same value.
                if ($infinity) {
                    $isfax['capfile'] = $filename;
                }

                continue;
            }

            if (Str::startsWith($line, '$fax_phone')) {
                $isfax['phone'] = $this->unquote($this->after($line, '$fax_phone '));

                continue;
            }

            if (Str::startsWith($line, '$fax_status1')) {
                // The status may be followed by further tokens on the same line; only the
                // first is the code we have to echo back.
                $status = explode(' ', $this->after($line, '$fax_status1 '));
                $isfax['status'] = (string) reset($status);
            }
        }

        return $isfax;
    }

    /**
     * @param  array<string, string>  $isfax
     */
    public function validate(array $isfax): ValidatorContract
    {
        return Validator::make($isfax, [
            'jobID' => 'required|integer',
            'capfile' => 'required|string|ends_with:.cap',
            'fsFileName' => 'required|string|ends_with:.fs',
            'filename' => 'required|string|ends_with:.cap',
            'phone' => 'required|string',
            'status' => 'required|string',
        ]);
    }

    /**
     * CRLF is what IS writes, but a file that has been through a text-mode copy arrives
     * LF-only; a single "line" means the first split did not take.
     *
     * @return array<int, string>
     */
    private function lines(string $contents): array
    {
        $lines = array_filter(explode("\r\n", $contents));

        return count($lines) <= 1 ? array_filter(explode("\n", $contents)) : $lines;
    }

    /**
     * The remainder of a line after its field prefix.
     */
    private function after(string $line, string $prefix): string
    {
        return (string) (array_values(array_filter(explode($prefix, $line)))[0] ?? '');
    }

    /**
     * IS writes Windows paths (`c:\copia\tosend\IS20.cap`); only the leaf is meaningful
     * to us. PHP's basename() is not used because it is separator-aware for the host OS,
     * and on Linux a backslash is an ordinary filename character.
     */
    private function basename(string $value): string
    {
        $segments = explode('\\', $value);

        return (string) end($segments);
    }

    private function unquote(string $value): string
    {
        return str_replace('"', '', $value);
    }
}
