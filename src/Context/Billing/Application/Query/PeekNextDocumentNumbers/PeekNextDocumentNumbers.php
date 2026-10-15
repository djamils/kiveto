<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\PeekNextDocumentNumbers;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The numbers the clinic's next invoice and next credit note would carry this
 * year, read without reserving them.
 */
final readonly class PeekNextDocumentNumbers implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public int $year,
    ) {
    }
}
