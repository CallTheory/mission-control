<?php

declare(strict_types=1);

namespace App\Services\Faxing\Spool\Exceptions;

use RuntimeException;

/**
 * The spool source could not be reached.
 *
 * Deliberately distinct from "the folder was empty". Only one Intelligent Series server
 * processes faxes at a time, so an empty tosend/ is the normal resting state for every
 * other one; treating the two alike would either hide a genuine outage or alert on a
 * healthy standby.
 */
class SpoolUnavailable extends RuntimeException {}
