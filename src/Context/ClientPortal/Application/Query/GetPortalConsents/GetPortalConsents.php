<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetPortalConsents;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The owner's consents in a clinic, merged across the confirmed-linked
 * client files that are not archived. Answers PortalConsents; throws
 * ClinicNotLinkedException when there is no such file.
 */
final readonly class GetPortalConsents implements QueryInterface
{
    public function __construct(
        public string $userId,
        public string $clinicId,
    ) {
    }
}
