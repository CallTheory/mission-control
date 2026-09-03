<?php

declare(strict_types=1);

namespace Tests\Feature\System;

use App\Models\System\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The SAML columns were historically encrypted by hand with encrypt()/decrypt()
 * at each call site; they now use the EncryptedSerialized cast. No data
 * migration was needed, so both shapes must read back as plaintext.
 */
class SamlSettingsEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_values_are_ciphertext_at_rest_and_plaintext_through_the_cast(): void
    {
        // Written by direct property assignment, exactly as SamlSettings does —
        // these columns are deliberately not mass-assignable.
        $settings = new Settings;
        $settings->saml2_sp_private_key = 'PRIVATE-KEY-BODY';
        $settings->saml2_sp_certificate = 'CERT-BODY';
        $settings->saml2_metadata_xml = '<xml/>';
        $settings->save();

        $raw = DB::table('settings')->first();
        $this->assertNotSame('PRIVATE-KEY-BODY', $raw->saml2_sp_private_key);
        $this->assertNotSame('CERT-BODY', $raw->saml2_sp_certificate);

        $settings = Settings::first();
        $this->assertSame('PRIVATE-KEY-BODY', $settings->saml2_sp_private_key);
        $this->assertSame('CERT-BODY', $settings->saml2_sp_certificate);
        $this->assertSame('<xml/>', $settings->saml2_metadata_xml);
    }

    public function test_legacy_manually_encrypted_values_still_read(): void
    {
        // Exactly what the old encrypt() call sites wrote.
        $settings = Settings::create([]);
        DB::table('settings')->where('id', $settings->id)->update([
            'saml2_sp_certificate' => encrypt('LEGACY-CERT'),
            'saml2_sp_private_key' => encrypt('LEGACY-KEY'),
        ]);

        $fresh = Settings::first();
        $this->assertSame('LEGACY-CERT', $fresh->saml2_sp_certificate);
        $this->assertSame('LEGACY-KEY', $fresh->saml2_sp_private_key);
    }

    public function test_null_certificate_reads_as_null_rather_than_throwing(): void
    {
        // DownloadCertificateController used to call strlen(decrypt(null)).
        Settings::create([]);

        $this->assertNull(Settings::first()->saml2_sp_certificate);
    }

    public function test_saml_secrets_are_hidden_from_array_serialization(): void
    {
        $settings = new Settings;
        $settings->saml2_sp_private_key = 'PRIVATE-KEY-BODY';
        $settings->save();

        $array = Settings::first()->toArray();

        $this->assertArrayNotHasKey('saml2_sp_private_key', $array);
        $this->assertArrayNotHasKey('saml2_sp_certificate', $array);
        $this->assertStringNotContainsString('PRIVATE-KEY-BODY', json_encode($array));
    }
}
