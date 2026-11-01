<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/** Only a pending link is confirmed or rejected by the clinic. */
final class ClientLinkNotPendingException extends \DomainException
{
    public function __construct(
        public readonly string $linkId,
        public readonly string $status,
    ) {
        parent::__construct(\sprintf('Client link %s is %s, not pending.', $linkId, $status));
    }
}
