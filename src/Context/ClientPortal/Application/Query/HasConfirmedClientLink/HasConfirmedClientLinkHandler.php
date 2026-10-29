<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\HasConfirmedClientLink;

use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class HasConfirmedClientLinkHandler
{
    public function __construct(private ClientLinkRepositoryInterface $links)
    {
    }

    /** False for a malformed id too (a URL segment). */
    public function __invoke(HasConfirmedClientLink $query): bool
    {
        return Uuid::isValid($query->userId)
            && Uuid::isValid($query->clinicId)
            && $this->links->hasConfirmed($query->userId, $query->clinicId);
    }
}
