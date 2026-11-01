<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/** No request of that id in the clinic (or a questionnaire, never resolved): a 404. */
final class PortalRequestNotFoundException extends \DomainException
{
    public function __construct(public readonly string $requestId)
    {
        parent::__construct(\sprintf('Portal request "%s" not found.', $requestId));
    }
}
