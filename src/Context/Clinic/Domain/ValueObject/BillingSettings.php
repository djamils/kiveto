<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\ValueObject;

use App\Context\Clinic\Domain\Exception\InvalidBillingSettingsException;
use App\Shared\Domain\ValueObject\PostalAddress;

/**
 * How the clinic bills: where the invoice is issued from, how its numbers are
 * built, when it expects to be paid, and where the money goes.
 *
 * Everything is optional. A clinic that fills in nothing keeps invoicing from
 * its letterhead address with bare numbers — which is what it did before this
 * existed.
 */
final readonly class BillingSettings
{
    public const int MAX_PAYMENT_TERMS_DAYS = 365;

    private const string PREFIX = '/^[A-Z0-9][A-Z0-9\-\/]{0,11}$/';
    private const string IBAN   = '/^[A-Z]{2}[0-9]{2}[A-Z0-9]{10,30}$/';
    private const string BIC    = '/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/';

    private function __construct(
        /** Where invoices are issued from, when that is not the clinic's own address. */
        public ?PostalAddress $billingAddress,
        public ?string $invoiceNumberPrefix,
        public ?int $paymentTermsDays,
        public ?string $iban,
        public ?string $bic,
        public ?string $invoiceFooterNote,
    ) {
    }

    public static function empty(): self
    {
        return new self(null, null, null, null, null, null);
    }

    public static function create(
        ?PostalAddress $billingAddress = null,
        ?string $invoiceNumberPrefix = null,
        ?int $paymentTermsDays = null,
        ?string $iban = null,
        ?string $bic = null,
        ?string $invoiceFooterNote = null,
    ): self {
        $prefix = self::clean($invoiceNumberPrefix);

        if (null !== $prefix) {
            $prefix = mb_strtoupper($prefix);

            if (1 !== preg_match(self::PREFIX, $prefix)) {
                throw InvalidBillingSettingsException::malformedPrefix($prefix);
            }
        }

        if (null !== $paymentTermsDays && ($paymentTermsDays < 0 || $paymentTermsDays > self::MAX_PAYMENT_TERMS_DAYS)) {
            throw InvalidBillingSettingsException::implausiblePaymentTerms($paymentTermsDays);
        }

        $iban = self::clean($iban);

        if (null !== $iban) {
            $iban = mb_strtoupper(str_replace(' ', '', $iban));

            if (1 !== preg_match(self::IBAN, $iban)) {
                throw InvalidBillingSettingsException::malformedIban($iban);
            }
        }

        $bic = self::clean($bic);

        if (null !== $bic) {
            $bic = mb_strtoupper(str_replace(' ', '', $bic));

            if (1 !== preg_match(self::BIC, $bic)) {
                throw InvalidBillingSettingsException::malformedBic($bic);
            }
        }

        return new self(
            billingAddress: $billingAddress,
            invoiceNumberPrefix: $prefix,
            paymentTermsDays: $paymentTermsDays,
            iban: $iban,
            bic: $bic,
            invoiceFooterNote: self::clean($invoiceFooterNote),
        );
    }

    public function isEmpty(): bool
    {
        return null === $this->billingAddress
            && null === $this->invoiceNumberPrefix
            && null === $this->paymentTermsDays
            && null === $this->iban
            && null === $this->bic
            && null === $this->invoiceFooterNote;
    }

    public function equals(self $other): bool
    {
        $sameAddress = (null === $this->billingAddress && null === $other->billingAddress)
            || (null !== $this->billingAddress && null !== $other->billingAddress
                && $this->billingAddress->equals($other->billingAddress));

        return $sameAddress
            && $this->invoiceNumberPrefix === $other->invoiceNumberPrefix
            && $this->paymentTermsDays === $other->paymentTermsDays
            && $this->iban === $other->iban
            && $this->bic === $other->bic
            && $this->invoiceFooterNote === $other->invoiceFooterNote;
    }

    /** Blank inputs are stored as nothing rather than as empty strings. */
    private static function clean(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = mb_trim($value);

        return '' !== $value ? $value : null;
    }
}
