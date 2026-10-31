<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Document;

/**
 * The kinds of documents a clinic publishes to the Portal. `lab_result` and
 * `certificate` are reserved: nothing publishes them in v1.
 */
enum DocumentType: string
{
    case INVOICE      = 'invoice';
    case PRESCRIPTION = 'prescription';
    case REPORT       = 'report';
    case LAB_RESULT   = 'lab_result';
    case CERTIFICATE  = 'certificate';

    /** Whether the file is rendered into the Portal's own storage (versioned keys). */
    public function hasPortalFile(): bool
    {
        return self::INVOICE !== $this;
    }
}
