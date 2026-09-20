<?php

use App\Enums\SmsProvider;
use App\Http\Controllers\Accounts\ClientAccountsController;
use App\Http\Controllers\Accounts\ClientDetailController;
use App\Http\Controllers\Accounts\ClientGreetingController;
use App\Http\Controllers\Api\WctpController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailUnsubscribeController;
use App\Http\Controllers\PrivacyPolicyController;
// use App\Http\Controllers\SAML2\SingleLogoutServiceController as SAMLLogoutController;
use App\Http\Controllers\Profile\SsoLinkController;
use App\Http\Controllers\RecordingController;
use App\Http\Controllers\RedirectHomeController;
use App\Http\Controllers\SAML2\CallbackController as SAMLCallbackController;
use App\Http\Controllers\SAML2\DownloadCertificateController as SAMLDownloadCertificateController;
use App\Http\Controllers\SAML2\MetadataController as SAMLMetadataController;
use App\Http\Controllers\SAML2\RedirectController as SAMLRedirectController;
use App\Http\Controllers\ScreenCaptureController;
use App\Http\Controllers\SendGrid\ParseController;
use App\Http\Controllers\System\ApiGatewayController as ApiGatewaySettingsController;
use App\Http\Controllers\System\AzureTokensController;
use App\Http\Controllers\System\BetterEmailController as BetterEmailSettingsController;
use App\Http\Controllers\System\BoardCheckController as BoardCheckSettingsController;
use App\Http\Controllers\System\CloudFaxingController as CloudFaxingSettingsController;
use App\Http\Controllers\System\CsvExportController as CsvExportSettingsController;
use App\Http\Controllers\System\DataSourcesController;
use App\Http\Controllers\System\IntegrationsController;
use App\Http\Controllers\System\McpServerController as McpServerSettingsController;
use App\Http\Controllers\System\ObservabilityController;
use App\Http\Controllers\System\PermissionsController;
use App\Http\Controllers\System\PreviewBetterEmailsThemeController;
use App\Http\Controllers\System\SamlSettingsController;
use App\Http\Controllers\System\ScriptSearchController as ScriptSearchSettingsController;
use App\Http\Controllers\System\SystemController;
use App\Http\Controllers\System\UserController as UserDetailController;
use App\Http\Controllers\System\UsersController as UsersAndGroupsController;
use App\Http\Controllers\System\Wctp\CarriersController as WctpCarriersController;
use App\Http\Controllers\System\Wctp\EnterpriseHostsController as WctpEnterpriseHostsController;
use App\Http\Controllers\System\Wctp\GatewayController as WctpGatewayPageController;
use App\Http\Controllers\System\Wctp\IndexController as WctpIndexController;
use App\Http\Controllers\System\Wctp\MessagesController as WctpMessagesController;
use App\Http\Controllers\TermsOfServiceController;
use App\Http\Controllers\Utilities\ApiGatewayController;
use App\Http\Controllers\Utilities\BetterEmailController;
use App\Http\Controllers\Utilities\BoardActivityController;
use App\Http\Controllers\Utilities\BoardCheckController;
use App\Http\Controllers\Utilities\BoardReportController;
use App\Http\Controllers\Utilities\BoardReviewController;
use App\Http\Controllers\Utilities\CallLookupController;
use App\Http\Controllers\Utilities\CardProcessingController;
use App\Http\Controllers\Utilities\CloudFaxingController;
use App\Http\Controllers\Utilities\ConfigEditorController;
use App\Http\Controllers\Utilities\CsvExportController;
use App\Http\Controllers\Utilities\DatabaseHealthController;
use App\Http\Controllers\Utilities\DirectorySearchController;
use App\Http\Controllers\Utilities\DownloadTBSReport;
use App\Http\Controllers\Utilities\InboundEmailController;
use App\Http\Controllers\Utilities\McpServerController;
use App\Http\Controllers\Utilities\MessageExportController;
use App\Http\Controllers\Utilities\ScriptSearchController;
use App\Http\Controllers\Utilities\VoicemailDigestController;
use App\Http\Controllers\UtilitiesController;
use App\Http\Middleware\ValidateSmsProviderWebhook;
use App\Http\Middleware\ValidateTwilioRequest;
use App\Models\Stats\Helpers;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [RedirectHomeController::class, 'index']);

Route::middleware(['auth:sanctum', 'verified'])->get('/dashboard', DashboardController::class)->name('dashboard');

Route::middleware(['auth:sanctum', 'verified'])->get('/utilities', UtilitiesController::class)->name('utilities');
Route::middleware(['auth:sanctum', 'verified'])->get('/utilities/inbound-email', InboundEmailController::class)->name('utilities.inbound-email');
Route::middleware(['auth:sanctum', 'verified'])->get('/utilities/database-health', DatabaseHealthController::class)->name('utilities.database-health');
Route::middleware(['auth:sanctum', 'verified'])->get('/utilities/api-gateway', ApiGatewayController::class)->name('utilities.api-gateway');
Route::middleware(['auth:sanctum', 'verified'])->get('/utilities/board-check', BoardCheckController::class)->name('utilities.board-check');
Route::middleware(['auth:sanctum', 'verified'])->get('/utilities/board-review', BoardReviewController::class)->name('utilities.board-review');
Route::middleware(['auth:sanctum', 'verified'])->get('/utilities/script-search', ScriptSearchController::class)->name('utilities.script-search');
Route::middleware(['auth:sanctum', 'verified'])->get('/utilities/board-report', BoardReportController::class)->name('utilities.board-report');
Route::middleware(['auth:sanctum', 'verified'])->get('/utilities/board-activity', BoardActivityController::class)->name('utilities.board-activity');
Route::middleware(['auth:sanctum', 'verified'])->get('/utilities/cloud-faxing/{provider?}', CloudFaxingController::class)->name('utilities.cloud-faxing');
Route::middleware(['auth:sanctum', 'verified'])->get('/utilities/card-processing', CardProcessingController::class)->name('utilities.card-processing');
Route::middleware(['auth:sanctum', 'verified'])->get('/utilities/card-processing/download-tbs-import', DownloadTBSReport::class)->name('utilities.card-processing.download-tbs-import');
Route::middleware(['auth:sanctum', 'verified'])->get('/utilities/call-lookup/{isCallID?}', CallLookupController::class)->name('utilities.call-lookup');
Route::middleware(['auth:sanctum', 'verified'])->get('/utilities/recording/{isCallID}.wav', RecordingController::class);
Route::middleware(['auth:sanctum', 'verified'])->get('/utilities/screencapture/{isCallID}.mp4', ScreenCaptureController::class);
Route::middleware(['auth:sanctum', 'verified'])->get('/utilities/better-emails', BetterEmailController::class)->name('utilities.better-emails');
Route::middleware(['auth:sanctum', 'verified'])->get('/utilities/directory-search', DirectorySearchController::class)->name('utilities.directory-search');
Route::middleware(['auth:sanctum', 'verified'])->get('/utilities/mcp-server', McpServerController::class)->name('utilities.mcp-server');
Route::middleware(['auth:sanctum', 'verified'])->get('/utilities/mcp-protocol-test', function () {
    return view('livewire.utilities.mcp-protocol-test');
})->name('utilities.mcp-protocol-test');
Route::middleware(['auth:sanctum', 'verified'])->get('/utilities/csv-export', [CsvExportController::class, 'index'])->name('utilities.csv-export');
Route::middleware(['auth:sanctum', 'verified'])->get('/utilities/csv-export/history', [CsvExportController::class, 'history'])->name('utilities.csv-export.history');
Route::middleware(['auth:sanctum', 'verified'])->get('/utilities/message-export', [MessageExportController::class, 'index'])->name('utilities.message-export');
Route::middleware(['auth:sanctum', 'verified'])->get('/utilities/message-export/history', [MessageExportController::class, 'history'])->name('utilities.message-export.history');
Route::middleware(['auth:sanctum', 'verified'])->get('/utilities/message-export/download/{log}', [MessageExportController::class, 'download'])->name('utilities.message-export.download');
Route::middleware(['auth:sanctum', 'verified'])->get('/utilities/voicemail-digest', [VoicemailDigestController::class, 'index'])->name('utilities.voicemail-digest');
Route::middleware(['auth:sanctum', 'verified'])->get('/utilities/voicemail-digest/history', [VoicemailDigestController::class, 'history'])->name('utilities.voicemail-digest.history');
Route::middleware(['auth:sanctum', 'verified'])->get('/utilities/config-editor', ConfigEditorController::class)->name('utilities.config-editor');

Route::middleware(['auth:sanctum', 'verified'])->get('/system', SystemController::class)->name('system');
Route::middleware(['auth:sanctum', 'verified'])->get('/system/data-sources', DataSourcesController::class)->name('system.data-sources');
Route::middleware(['auth:sanctum', 'verified'])->get('/system/integrations', IntegrationsController::class)->name('system.integrations');
Route::middleware(['auth:sanctum', 'verified'])->get('/system/observability', ObservabilityController::class)->name('system.observability');
Route::middleware(['auth:sanctum', 'verified'])->get('/system/permissions', PermissionsController::class)->name('system.permissions');
Route::middleware(['auth:sanctum', 'verified'])->get('/system/azure-tokens', AzureTokensController::class)->name('system.azure-tokens');
Route::middleware(['auth:sanctum', 'verified'])->get('/system/board-check', BoardCheckSettingsController::class)->name('system.board-check');
Route::middleware(['auth:sanctum', 'verified'])->get('/system/cloud-faxing', CloudFaxingSettingsController::class)->name('system.cloud-faxing');
Route::middleware(['auth:sanctum', 'verified'])->get('/system/script-search', ScriptSearchSettingsController::class)->name('system.script-search');

// The WCTP gateway section. Administrative throughout -- carriers, numbers, hosts
// and traffic are one installation-wide configuration, not a per-team utility -- so
// it lives under /system and is gated on wctp.manage / wctp.messages rather than on
// any team's utility flag. What used to be tabs on one screen are these pages.
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/system/wctp', WctpIndexController::class)->name('system.wctp');
    Route::get('/system/wctp/gateway', WctpGatewayPageController::class)->name('system.wctp.gateway');
    Route::get('/system/wctp/carriers', WctpCarriersController::class)->name('system.wctp.carriers');
    Route::get('/system/wctp/enterprise-hosts', WctpEnterpriseHostsController::class)->name('system.wctp.enterprise-hosts');
    Route::get('/system/wctp/messages', WctpMessagesController::class)->name('system.wctp.messages');

    // Where these pages used to live, kept so bookmarks and older links resolve.
    Route::redirect('/system/wctp-gateway', '/system/wctp')->name('system.wctp-gateway');
    Route::redirect('/utilities/wctp-gateway', '/system/wctp/gateway')->name('utilities.wctp-gateway');
    Route::redirect('/utilities/enterprise-hosts', '/system/wctp/enterprise-hosts')->name('utilities.enterprise-hosts');
    Route::redirect('/utilities/wctp-messages', '/system/wctp/messages')->name('utilities.wctp-messages');
});
Route::middleware(['auth:sanctum', 'verified'])->get('/system/api-gateway', ApiGatewaySettingsController::class)->name('system.api-gateway');
Route::middleware(['auth:sanctum', 'verified'])->get('/system/better-emails', BetterEmailSettingsController::class)->name('system.better-emails');
Route::middleware(['auth:sanctum', 'verified'])->get('/system/better-emails/preview/{theme}', PreviewBetterEmailsThemeController::class);
Route::middleware(['auth:sanctum', 'verified'])->get('/system/saml-settings', SamlSettingsController::class)->name('system.saml-settings');
Route::middleware(['auth:sanctum', 'verified'])->get('/system/saml-settings/download-cert', SAMLDownloadCertificateController::class)->name('system.saml-settings.download-cert');
Route::middleware(['auth:sanctum', 'verified'])->get('/system/users', UsersAndGroupsController::class)->name('system.users');
Route::middleware(['auth:sanctum', 'verified'])->get('/system/users/{user}', UserDetailController::class)->name('system.user.{user}');
Route::middleware(['auth:sanctum', 'verified'])->get('/system/mcp-server', McpServerSettingsController::class)->name('system.mcp-server');
Route::middleware(['auth:sanctum', 'verified'])->get('/system/csv-export', CsvExportSettingsController::class)->name('system.csv-export');

Route::middleware(['auth:sanctum', 'verified'])->get('/accounts', ClientAccountsController::class)->name('accounts');
Route::middleware(['auth:sanctum', 'verified'])->get('/accounts/{client_number}', ClientDetailController::class)->name('accounts.{client_number}');
Route::middleware(['auth:sanctum', 'verified'])->get('/accounts/greetings/{greetingID}', ClientGreetingController::class)->name('accounts.greeting.{greetingID}');

Route::get('/terms', [TermsOfServiceController::class, 'show'])->name('terms.show');
Route::get('/privacy', [PrivacyPolicyController::class, 'show'])->name('policy.show');

Route::post('/webhooks/sendgrid/parse/{api_key}', ParseController::class);
// Unsubscribe links are generated with URL::signedRoute (see BeautifyEmail), so the
// signature must be verified here to prevent tampering/enumeration.
Route::get('/email-unsubscribe', EmailUnsubscribeController::class)->middleware('signed')->name('email-unsubscribe');

// WCTP Enterprise Host endpoint (public; authenticated per-request by senderID +
// securityCode). Throttled to blunt security-code brute-forcing.
if (Helpers::isSystemFeatureEnabled('wctp-gateway')) {
    $smsProviders = array_column(SmsProvider::cases(), 'value');

    Route::post('/wctp', [WctpController::class, 'handle'])
        ->middleware('throttle:60,1')
        ->name('wctp');

    // Twilio's original unprefixed routes, kept so consoles configured before the
    // gateway supported other carriers keep working.
    Route::middleware(ValidateTwilioRequest::class)->group(function () {
        Route::post('/wctp/callback/{messageId}', [WctpController::class, 'twilioCallback'])
            ->name('wctp.callback');

        Route::post('/wctp/sms/incoming', [WctpController::class, 'handleIncomingSms'])
            ->name('wctp.sms.incoming');
    });

    // Per-carrier routes. Both paths accept both event kinds, because Bandwidth's
    // messaging application posts inbound messages and delivery receipts to a single
    // configured URL and Commio's portal is configured much the same way; which of
    // the two URLs an operator pastes where should not matter.
    Route::middleware(ValidateSmsProviderWebhook::class)->group(function () use ($smsProviders) {
        Route::post('/wctp/sms/{provider}/incoming', [WctpController::class, 'handleProviderWebhook'])
            ->whereIn('provider', $smsProviders)
            ->name('wctp.sms.provider.incoming');

        Route::post('/wctp/{provider}/callback/{messageId?}', [WctpController::class, 'handleProviderWebhook'])
            ->whereIn('provider', $smsProviders)
            ->name('wctp.provider.callback');
    });
}

// Linking an existing account to the IdP -- see Profile\SsoLinkController.
Route::middleware(['auth:sanctum', 'verified'])
    ->post('/user/sso/link', SsoLinkController::class)
    ->name('profile.sso.link');

// Support SAML2 get/post, but default to POST (see services.php)
Route::match(['get', 'post'], '/sso/saml2/redirect', SAMLRedirectController::class)->name('sso.saml2.redirect');
Route::match(['get', 'post'], '/sso/saml2/callback', SAMLCallbackController::class)->name('sso.saml2.callback');
Route::match(['get', 'post'], '/sso/saml2/metadata', SAMLMetadataController::class)->name('sso.saml2.metadata');

// SLO requires a `samesite` cookie to be set to `none`, meaning any site can post cookies. don't like it.
// let's disable this until it's something we need...
// Route::match(['get', 'post'], '/sso/saml2/slo', SAMLLogoutController::class)->name('sso.saml2.slo');
