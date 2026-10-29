<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Invitation;

use App\Shared\Domain\Identifier\AbstractUuidId;

final class PortalInvitationId extends AbstractUuidId
{
    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
