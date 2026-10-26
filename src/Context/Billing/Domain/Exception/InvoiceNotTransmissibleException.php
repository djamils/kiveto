<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Exception;

use App\Context\Billing\Domain\ValueObject\ReadinessRequirement;

/**
 * A professional invoice lacks what an electronic invoice needs: it is not
 * numbered, and the draft stays open until the data is fixed.
 */
final class InvoiceNotTransmissibleException extends \DomainException
{
    /**
     * @param list<ReadinessRequirement> $requirements
     */
    public function __construct(
        public readonly string $invoiceId,
        public readonly array $requirements,
    ) {
        parent::__construct(\sprintf(
            'Invoice %s cannot be issued as an electronic invoice: %s.',
            $invoiceId,
            implode(', ', array_map(static fn (ReadinessRequirement $r): string => $r->value, $requirements)),
        ));
    }
}
