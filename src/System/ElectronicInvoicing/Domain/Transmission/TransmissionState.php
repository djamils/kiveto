<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Transmission;

/**
 * - PREPARED: waiting to be sent (no verified connection yet, or queued);
 * - AWAITING_INVOICE: a credit note waiting for its invoice to be submitted;
 * - SUBMITTING: the file is rendered, stored and frozen; the platform may or
 *   may not have received it;
 * - SUBMITTED: the platform returned a flow id;
 * - ACCEPTED: the platform gave a first status (500 or any 2xx);
 * - INADMISSIBLE: the platform refused the file (501);
 * - BLOCKED: requires action, see BlockReason;
 * - NOT_REQUIRED: an internal credit note, never transmitted: its invoice was
 *   refused (210) or rejected (213).
 */
enum TransmissionState: string
{
    case PREPARED         = 'prepared';
    case AWAITING_INVOICE = 'awaiting_invoice';
    case SUBMITTING       = 'submitting';
    case SUBMITTED        = 'submitted';
    case ACCEPTED         = 'accepted';
    case INADMISSIBLE     = 'inadmissible';
    case BLOCKED          = 'blocked';
    case NOT_REQUIRED     = 'not_required';
}
