<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\TaxProfile;

/**
 * The answer to a « Revérifier » request.
 */
enum VerificationRequestOutcome: string
{
    case QUEUED          = 'queued';
    case ALREADY_PENDING = 'already_pending';
    case ARCHIVED        = 'archived';
    case NOT_APPLICABLE  = 'not_applicable';
}
