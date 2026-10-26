<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Exception;

/**
 * Another establishment of the same company (same SIREN) already numbers its
 * invoices or credit notes under this prefix: the two series would print the
 * same numbers, and a document number would no longer identify one document
 * of the company.
 */
final class InvoicePrefixAlreadyUsedException extends \DomainException
{
    public const string SERIES_INVOICE     = 'invoice';
    public const string SERIES_CREDIT_NOTE = 'credit_note';

    /**
     * @param self::SERIES_* $series the series of this clinic whose prefix is taken
     */
    public function __construct(
        public readonly string $prefix,
        public readonly string $series,
    ) {
        parent::__construct(\sprintf('Number series "%s" is already used by another establishment of the same company.', $prefix));
    }
}
