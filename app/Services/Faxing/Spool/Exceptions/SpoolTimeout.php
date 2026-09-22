<?php

declare(strict_types=1);

namespace App\Services\Faxing\Spool\Exceptions;

/**
 * The source did not answer within its wall-clock budget.
 */
class SpoolTimeout extends SpoolUnavailable {}
