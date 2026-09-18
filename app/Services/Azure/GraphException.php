<?php

declare(strict_types=1);

namespace App\Services\Azure;

use RuntimeException;

/**
 * A Microsoft Graph or Entra token endpoint call that did not succeed.
 *
 * Carries a message fit to show an administrator: the two failures that actually
 * happen in practice are an expired client secret and missing admin consent for
 * Application.Read.All, and both are only actionable if the message says so.
 */
class GraphException extends RuntimeException {}
