<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Faxing;

use App\Services\Faxing\FsFileParser;
use Tests\TestCase;

/**
 * Pins the `.fs` format against the two hand-rolled parsers this class replaced, so the
 * extraction is provably behaviour-preserving for classic IS and the fields keep their
 * exact shapes (quotes stripped, Windows paths reduced to a leaf, status truncated at the
 * first space).
 */
class FsFileParserTest extends TestCase
{
    private FsFileParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new FsFileParser;
    }

    public function test_it_parses_a_classic_is_fs_file(): void
    {
        $contents = implode("\r\n", [
            '$var_def DATA5 "12345"',
            '$var_def DATA6 "c:\\copia\\tosend\\IS20.cap"',
            '$fax_filename c:\\copia\\tosend\\IS20.cap',
            '$fax_phone 9139069098',
            '$fax_status1 2',
        ]);

        $this->assertSame([
            'fsFileName' => 'IS20.fs',
            'jobID' => '12345',
            'capfile' => 'IS20.cap',
            'filename' => 'IS20.cap',
            'phone' => '9139069098',
            'status' => '2',
        ], $this->parser->parse('IS20.fs', $contents, 'genesis'));
    }

    public function test_it_falls_back_to_lf_line_endings(): void
    {
        // A .fs that has been through a text-mode copy arrives LF-only. The CRLF split
        // then yields a single "line", which is the signal to re-split.
        $contents = "\$var_def DATA6 c:\\copia\\tosend\\IS20.cap\n\$fax_phone 9139069098\n\$fax_status1 2\n";

        $parsed = $this->parser->parse('IS20.fs', $contents, 'genesis');

        $this->assertSame('IS20.cap', $parsed['capfile']);
        $this->assertSame('9139069098', $parsed['phone']);
        $this->assertSame('2', $parsed['status']);
    }

    public function test_it_takes_only_the_first_token_of_the_status_line(): void
    {
        $parsed = $this->parser->parse('IS20.fs', '$fax_status1 2 something else', 'genesis');

        $this->assertSame('2', $parsed['status']);
    }

    public function test_it_ends_a_quoted_filename_at_the_closing_quote(): void
    {
        $parsed = $this->parser->parse('IS20.fs', '$fax_filename c:\\copia\\tosend\\IS20.cap" trailing', 'genesis');

        $this->assertSame('IS20.cap', $parsed['filename']);
    }

    public function test_infinity_takes_the_payload_name_from_fax_filename(): void
    {
        // Infinity emits no DATA6 at all; the payload name is $fax_filename and the file
        // itself lives in messages/ rather than tosend/.
        $contents = implode("\r\n", [
            '$var_def DATA5 "4242"',
            '$fax_filename c:\\infinity\\messages\\IS77.cap',
            '$fax_phone 9139069098',
            '$fax_status1 2',
        ]);

        $parsed = $this->parser->parse('IS77.fs', $contents, FsFileParser::ENGINE_INFINITY);

        $this->assertSame('IS77.cap', $parsed['capfile']);
        $this->assertSame('IS77.cap', $parsed['filename']);
        $this->assertTrue($this->parser->validate($parsed)->passes());
    }

    public function test_infinity_ignores_a_data6_line_if_one_is_present(): void
    {
        $contents = implode("\r\n", [
            '$var_def DATA5 "4242"',
            '$var_def DATA6 "c:\\copia\\tosend\\STALE.cap"',
            '$fax_filename c:\\infinity\\messages\\IS77.cap',
            '$fax_phone 9139069098',
            '$fax_status1 2',
        ]);

        $this->assertSame('IS77.cap', $this->parser->parse('IS77.fs', $contents, FsFileParser::ENGINE_INFINITY)['capfile']);
    }

    public function test_it_does_not_leak_fields_between_files(): void
    {
        $good = $this->parser->parse('IS20.fs', implode("\r\n", [
            '$var_def DATA5 "12345"',
            '$var_def DATA6 "IS20.cap"',
            '$fax_filename IS20.cap',
            '$fax_phone 9139069098',
            '$fax_status1 2',
        ]), 'genesis');

        $this->assertTrue($this->parser->validate($good)->passes());

        // A malformed file must fail on its own merits rather than inheriting the
        // previous file's job id and payload.
        $bad = $this->parser->parse('IS21.fs', '$fax_phone 9139069098', 'genesis');

        $this->assertSame(['fsFileName' => 'IS21.fs', 'phone' => '9139069098'], $bad);
        $this->assertTrue($this->parser->validate($bad)->fails());
    }

    public function test_validation_rejects_a_non_cap_payload(): void
    {
        $parsed = $this->parser->parse('IS20.fs', implode("\r\n", [
            '$var_def DATA5 "12345"',
            '$var_def DATA6 "IS20.txt"',
            '$fax_filename IS20.txt',
            '$fax_phone 9139069098',
            '$fax_status1 2',
        ]), 'genesis');

        $errors = $this->parser->validate($parsed)->errors();

        $this->assertTrue($errors->has('capfile'));
        $this->assertTrue($errors->has('filename'));
    }

    public function test_it_defaults_the_engine_from_config(): void
    {
        config(['app.switch_engine' => FsFileParser::ENGINE_INFINITY]);

        $parsed = $this->parser->parse('IS77.fs', '$fax_filename c:\\infinity\\messages\\IS77.cap');

        $this->assertSame('IS77.cap', $parsed['capfile']);
    }
}
