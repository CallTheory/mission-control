<?php
use LightSaml\SamlConstants;
return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'stripe' => [
        'publishable_key' => env('STRIPE_KEY'),
        'secret_key' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET')
    ],
    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'fax' => [
        'documo' => [
            'api_key' => env('DOCUMO_API_KEY', ''),
            'cover_page_id' => env('DOCUMO_COVERPAGE_ID', ''),
        ],
        'ringcentral' => [
            //this needs fixed eventually
            //basically we "activate" using ring central instead of mfax with this toggle...
            'active' => env('RING_CENTRAL_ACTIVE', 0),
            'client_id' => env('RING_CENTRAL_CLIENT_ID'),
            'client_secret' => env('RING_CENTRAL_CLIENT_SECRET'),
            'jwt_token' => env('RING_CENTRAL_JWT_TOKEN'),
            'api_endpoint' => env('RING_CENTRAL_API_ENDPOINT', 'https://platform.ringcentral.com')
        ],
    ],
    'saml2' => [
        'metadata' => '',
        'sp_default_binding_method' => SamlConstants::BINDING_SAML2_HTTP_POST,
        //'sp_sls' => 'sso/saml2/slo', //same_site = none => i.e., bad security
        'sp_acs' => 'sso/saml2/callback',
        'sp_certificate' => null,
        'sp_private_key' => null,
        //'sp_private_key_passphrase' => '', //do not include unless its needed
        'sp_sign_assertions' => false, // or false to disable assertion signing
        'ttl' => 60*60*24, // 24 hours
        'validation' => [
            'clock_skew' => 120,
            'repeated_id_ttl' => null, //365*24*60*60
        ],
        'sp_entityid' => 'sso/saml2', //needs to be full url
        'sp_tech_contact_surname' => 'Patrick',
        'sp_tech_contact_givenname' => 'Labbett',
        'sp_tech_contact_email' => 'support@calltheory.com',
        'sp_org_lang' => 'en',
        'sp_org_name' => 'Call Theory',
        'sp_org_display_name' => 'Call Theory',
        'sp_org_url' => 'https://calltheory.com'
    ],
];
