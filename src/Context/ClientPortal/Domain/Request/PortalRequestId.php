<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Request;

use App\Shared\Domain\Identifier\AbstractUuidId;

/** The id of an owner's request: a pet change, a renewal or a pre-visit questionnaire. */
final class PortalRequestId extends AbstractUuidId
{
    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
