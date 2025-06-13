<?php

namespace App\Livewire\System;

use App\Models\System\Settings;
use Carbon\Carbon;
use Exception;
use Illuminate\View\View;
use Livewire\Component;

class SamlSettings extends Component
{
    public bool $saml_enabled = false;

    public bool $stateless_redirect = false;

    public bool $stateless_callback = false;

    public bool $sign_assertions = false;

    public ?string $metadata_url = null;

    public ?string $cert_fingerprint = null;

    public ?string $cert_valid_from = null;

    public ?string $cert_valid_to = null;

    public ?string $metadata_xml = null;

    public function saveSamlSettings(): void
    {
        $this->resetErrorBag();
        $settings = Settings::first();
        $settings->saml2_stateless_redirect = $this->stateless_redirect ?? false;
        $settings->saml2_stateless_callback = $this->stateless_callback ?? false;
        $settings->saml2_enabled = $this->saml_enabled ?? false;
        $settings->saml2_metadata_url = $this->metadata_url ?? null;
        if (strlen($this->metadata_xml)) {
            $settings->saml2_metadata_xml = encrypt($this->metadata_xml);
        } else {
            $settings->saml2_metadata_xml = null;
        }

        $settings->saml2_sp_sign_assertions = $this->sign_assertions ?? false;
        try {
            $settings->save();
            $this->dispatch('saved');
        } catch (Exception $e) {
            $this->addError('metadata_url', $e->getMessage());
            $this->addError('metadata_xml', $e->getMessage());
        }
    }

    public function toggleSamlSupport(): void
    {
        $this->resetErrorBag();
        $this->saml_enabled = ! $this->saml_enabled;
        $settings = Settings::first();
        $settings->saml2_enabled = $this->saml_enabled;

        if ($this->saml_enabled === false) {
            $settings->saml2_metadata_url = null;
            $settings->saml2_metadata_xml = null;
            $settings->saml2_sp_certificate = null;
            $settings->saml2_sp_private_key = null;
            $settings->saml2_sp_sign_assertions = false;
        }

        try {
            $settings->save();
            $this->dispatch('saved');
        } catch (Exception $e) {
            $this->addError('saml_enabled', $e->getMessage());
        }
    }

    private function makeCert(): array
    {
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
        ];

        $csrConfig = [
            'countryName' => 'US',
            'stateOrProvinceName' => 'Ohio',
            'localityName' => 'Grove City',
            'organizationName' => 'Call Theory',
            'organizationalUnitName' => 'Mission Control',
            'commonName' => request()->getHost(),
            'emailAddress' => 'support@calltheory.com',
        ];

        $private_key = null;
        $certificate = null;
        $pk = openssl_pkey_new($config);
        openssl_pkey_export($pk, $private_key);

        $csr = openssl_csr_new($csrConfig, $pk, ['digest_alg' => 'sha512']);
        $crt = openssl_csr_sign($csr, null, $pk, 365 * 3, ['digest_alg' => 'sha512']);

        openssl_x509_export($crt, $certificate);

        return ['private_key' => $private_key, 'certificate' => $certificate];
    }

    public function toggleSignAssertions(): void
    {

        $this->resetErrorBag();
        $this->sign_assertions = ! $this->sign_assertions;

        $settings = Settings::first();
        $settings->saml2_sp_sign_assertions = $this->sign_assertions;

        if ($this->sign_assertions === true) {
            $cert = $this->makeCert();
            $settings->saml2_sp_certificate = encrypt($cert['certificate']);
            $settings->saml2_sp_private_key = encrypt($cert['private_key']);
        } else {
            $settings->saml2_sp_certificate = null;
            $settings->saml2_sp_private_key = null;
        }

        try {
            $settings->save();
            $this->getCertificateDetails($settings);
            $this->dispatch('saved');
        } catch (Exception $e) {
            $this->addError('sign_assertions', $e->getMessage());
        }
    }

    public function toggleStatlessRedirect(): void
    {
        $this->resetErrorBag();
        $this->stateless_redirect = ! $this->stateless_redirect;
        $settings = Settings::first();
        $settings->saml2_stateless_redirect = $this->stateless_redirect;
        try {
            $settings->save();
            $this->dispatch('saved');
        } catch (Exception $e) {
            $this->addError('stateless_redirect', $e->getMessage());
        }
    }

    public function toggleStatelessCallback(): void
    {
        $this->resetErrorBag();
        $this->stateless_callback = ! $this->stateless_callback;
        $settings = Settings::first();
        $settings->saml2_stateless_callback = $this->stateless_callback;
        try {
            $settings->save();
            $this->dispatch('saved');
        } catch (Exception $e) {
            $this->addError('stateless_callback', $e->getMessage());
        }
    }

    public function mount(): void
    {
        $settings = Settings::first();
        $this->stateless_callback = $settings->saml2_stateless_callback ?? false;
        $this->stateless_redirect = $settings->saml2_stateless_redirect ?? false;
        $this->saml_enabled = $settings->saml2_enabled ?? false;
        $this->metadata_url = $settings->saml2_metadata_url ?? null;
        if (strlen($settings->saml2_metadata_xml)) {
            $this->metadata_xml = decrypt($settings->saml2_metadata_xml);
        }
        $this->sign_assertions = $settings->saml2_sp_sign_assertions ?? false;

        $this->getCertificateDetails($settings);
    }

    public function getCertificateDetails(Settings $settings): void
    {
        if ($settings->saml2_sp_certificate && strlen($settings->saml2_sp_certificate)) {

            $this->cert_fingerprint = openssl_x509_fingerprint(decrypt($settings->saml2_sp_certificate));
            $cert_details = openssl_x509_parse(decrypt($settings->saml2_sp_certificate));
            $this->cert_valid_from = Carbon::createFromTimestampUTC($cert_details['validFrom_time_t'])->timezone(request()->user()->timezone)->format('m/d/Y g:i:s A T');
            $this->cert_valid_to = Carbon::createFromTimestampUTC($cert_details['validTo_time_t'])->timezone(request()->user()->timezone)->format('m/d/Y g:i:s A T');
        } else {
            $this->cert_fingerprint = null;
            $this->cert_valid_from = null;
            $this->cert_valid_to = null;
        }
    }

    public function render(): View
    {
        return view('livewire.system.saml-settings');
    }
}
