<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Document;

/**
 * awaiting_link → pending_file → available (or failed); available →
 * pending_file on a re-share; any status → withdrawn, which is terminal.
 */
enum PublicationStatus: string
{
    /** The client has no confirmed Portal link yet: nothing is rendered. */
    case AWAITING_LINK = 'awaiting_link';
    /** A file is expected for `pending_file_version`. */
    case PENDING_FILE = 'pending_file';
    case AVAILABLE    = 'available';
    /** Five renders failed; the last served version (if any) stays served. */
    case FAILED = 'failed';
    /** Cancelled at the source: never listed nor served again. */
    case WITHDRAWN = 'withdrawn';
}
