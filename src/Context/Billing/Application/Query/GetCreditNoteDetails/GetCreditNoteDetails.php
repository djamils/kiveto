<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\GetCreditNoteDetails;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetCreditNoteDetails implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $creditNoteId,
    ) {
    }
}
