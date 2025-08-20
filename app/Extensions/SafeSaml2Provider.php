<?php

namespace App\Extensions;

use Exception;
use Illuminate\Support\Arr;
use SocialiteProviders\Manager\OAuth2\User;
use SocialiteProviders\Saml2\Provider as BaseProvider;

/**
 * Extends the SAML2 provider to handle PHP 8.4 strict type issues
 * and provide better error handling for null assertions
 */
class SafeSaml2Provider extends BaseProvider
{
    /**
     * Override the user method to handle null assertion errors
     */
    public function user()
    {
        try {
            // Check if we have a valid SAML response first
            if (!$this->messageContext || !$this->messageContext->asResponse()) {
                throw new Exception('Invalid SAML response context');
            }

            // Check if we have an assertion before proceeding
            $response = $this->messageContext->asResponse();
            if (!$response->getFirstAssertion()) {
                throw new Exception('No SAML assertion found in response. This may indicate an authentication failure at the IdP.');
            }

            return parent::user();
        } catch (Exception $e) {
            // Log the actual error for debugging
            \Log::error('SAML2 Provider Error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            // Re-throw with more context
            throw new Exception('SAML2 authentication failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Override to handle the case where attributes might be missing
     */
    protected function mapUserToObject(array $user)
    {
        return (new User())->setRaw($user)->map([
            'id' => Arr::get($user, 'id'),
            'name' => Arr::get($user, 'name'),
            'email' => Arr::get($user, 'email'),
        ]);
    }
}