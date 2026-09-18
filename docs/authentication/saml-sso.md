# SAML SSO

Mission Control supports SAML 2.0 Single Sign-On (SSO) for enterprise authentication, allowing users to log in using their organization's identity provider (IdP).

## Setup

SAML SSO is configured in the System SAML Settings page (`/system/saml-settings`), which
requires the `system.access` capability.

### Service Provider (SP) Configuration

Mission Control acts as the SAML Service Provider with the following endpoints:

- **Entity ID**: `{your-server}/sso/saml2`
- **ACS URL (Assertion Consumer Service)**: `{your-server}/sso/saml2/callback`
- **SP Metadata URL**: `{your-server}/sso/saml2/metadata`

The SP metadata XML can be downloaded and provided to your IdP for configuration.

### Identity Provider (IdP) Configuration

Configure your IdP connection using one of the following methods:

- **Metadata URL**: Enter your IdP's metadata URL for automatic configuration
- **Metadata XML**: Paste your IdP's metadata XML directly if a URL is not available

### Settings

- **SAML2 Enabled**: Toggle SSO on or off
- **Sign Assertions**: Enable SP-side assertion signing for additional security
- **Stateless Redirect**: Enable stateless redirect handling
- **Stateless Callback**: Enable stateless callback handling

## Certificate Management

If assertion signing is enabled, you can configure:

- **SP Certificate**: The service provider's X.509 certificate for signing
- **SP Private Key**: The service provider's private key for signing

The settings page shows the stored certificate's fingerprint and its validity dates, so you
can confirm which certificate is in use and see when it needs replacing.

The SP certificate can be downloaded from the SAML settings page at
`/system/saml-settings/download-cert` for sharing with your IdP.

> All certificates and keys are stored encrypted in the database.

## SAML Attribute Mapping

The following SAML attributes are mapped to Mission Control user fields:

| SAML Attribute | User Field |
|----------------|------------|
| `emailaddress` | Email |
| `givenname` | First Name |
| `surname` | Last Name |

## User Provisioning

When a user authenticates via SAML SSO:

1. A user account is created or updated based on the email address
2. A personal team is created if the user doesn't have one
3. The user is linked via a `saml_linked_id` field
4. The user's timezone is set from the system's configured switch data timezone

## SSO Routes

| Route | Method | Description |
|-------|--------|-------------|
| `/sso/saml2/redirect` | GET/POST | Initiates SSO login |
| `/sso/saml2/callback` | GET/POST | Handles IdP response |
| `/sso/saml2/metadata` | GET/POST | Serves SP metadata XML |
