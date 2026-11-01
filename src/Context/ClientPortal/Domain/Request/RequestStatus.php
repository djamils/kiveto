<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Request;

/**
 * `submitted` until the clinic answers it: `done` (« Traiter »), `rejected`
 * (« Refuser »), or `closed` without answer when its subject is gone
 * (terminal, never notified, out of the clinic's queue). A questionnaire is
 * only ever `submitted` or `closed`.
 */
enum RequestStatus: string
{
    case SUBMITTED = 'submitted';
    case DONE      = 'done';
    case REJECTED  = 'rejected';
    case CLOSED    = 'closed';
}
