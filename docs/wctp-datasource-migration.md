# WCTP Gateway - DataSource Migration

## Overview
The WCTP gateway has been updated to exclusively use the DataSource model for Twilio configuration, removing all dependencies on environment variables (.env file).

## Changes Made

### 1. TwilioService Updates
- **File**: `app/Services/TwilioService.php`
- Removed all fallbacks to `config('services.twilio.*')`
- Now exclusively uses DataSource for credentials
- Throws clear exceptions when DataSource is not configured
- Added `getFromNumber()` method for accessing the from number

### 2. WctpController Updates
- **File**: `app/Http/Controllers/Api/WctpController.php`
- Removed config fallback for from number
- Returns 503 Service Unavailable when DataSource is not configured
- Added proper HTTP status code mapping for WCTP error codes

### 3. WctpGateway Component Updates
- **File**: `app/Livewire/Utilities/WctpGateway.php`
- Removed config check fallback
- Only checks DataSource for Twilio configuration status

### 4. View Updates
- **File**: `resources/views/livewire/utilities/wctp-gateway.blade.php`
- Updated instructions to only mention DataSource configuration
- Added note that .env variables are no longer supported

### 5. Test Updates
- **File**: `tests/Feature/Api/WctpTest.php`
- Updated to use encrypted values in DataSource setup
- Added comment noting no fallback to .env config

### 6. New Test Coverage
- **File**: `tests/Feature/Api/WctpDataSourceTest.php`
- Tests that system properly rejects requests without DataSource
- Tests that TwilioService fails without DataSource
- Tests that incomplete DataSource configuration is rejected

## Configuration Requirements

### DataSource Fields Required
All Twilio configuration must be stored in the DataSource model:
- `twilio_account_sid` (encrypted)
- `twilio_auth_token` (encrypted)
- `twilio_from_number` (plain text)

### No Environment Variables
The following environment variables are NO LONGER USED by the WCTP gateway:
- `TWILIO_ACCOUNT_SID`
- `TWILIO_AUTH_TOKEN`
- `TWILIO_FROM_NUMBER`

Note: These may still be defined in `config/services.php` for backwards compatibility with other parts of the system, but the WCTP gateway will not use them.

## Error Handling

### When DataSource is Missing
- **HTTP Response**: 503 Service Unavailable
- **WCTP Error Code**: 503
- **Error Message**: "Service unavailable - Twilio not configured"

### When DataSource is Incomplete
- **Exception**: `Exception`
- **Message**: "Twilio credentials not configured in DataSource. Please configure in System > Data Sources."

## Migration Steps for Existing Installations

1. Navigate to **System Settings → Data Sources**
2. Add or update the following fields:
   - Twilio Account SID
   - Twilio Auth Token
   - Twilio From Number
3. Remove or comment out Twilio environment variables from `.env` (optional, but recommended for clarity)

## Testing

Run the following tests to verify the configuration:
```bash
./vendor/bin/sail artisan test tests/Feature/Api/WctpTest.php
./vendor/bin/sail artisan test tests/Feature/Api/WctpDataSourceTest.php
```

## Benefits of This Approach

1. **Centralized Configuration**: All SMS provider credentials in one place
2. **Database-Driven**: Can be managed through the UI without server access
3. **Encrypted Storage**: Sensitive credentials are encrypted in the database
4. **Multi-tenancy Ready**: Different teams could potentially use different credentials (future enhancement)
5. **No Server Restart**: Changes take effect immediately without restarting services