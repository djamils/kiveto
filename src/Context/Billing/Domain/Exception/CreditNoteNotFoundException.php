<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Exception;

final class CreditNoteNotFoundException extends \DomainException
{
    public function __construct(string $creditNoteId)
    {
        parent::__construct(\sprintf('Credit note %s not found.', $creditNoteId));
    }
}
