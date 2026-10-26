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

    /**
     * The prefixes a document is numbered under when the clinic chose none —
     * the very ones the Billing context falls back to when it numbers them.
     */
    public const string DEFAULT_INVOICE_PREFIX     = 'FAC';
    public const string DEFAULT_CREDIT_NOTE_PREFIX = 'AV';

    /** The ways of paying a clinic can choose to take at its desk. */
    public const array PAYMENT_METHODS = ['CARD', 'CASH', 'CHECK', 'TRANSFER'];

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
        /** Credit notes run in a series of their own, under their own prefix. */
        public ?string $creditNotePrefix = null,
        /** Breeders and shelters are often given longer to pay than households. */
        public ?int $professionalPaymentTermsDays = null,
        public ?VatRegime $vatRegime = null,
        /** Opting for VAT on debits must be stated on every invoice. */
        public bool $vatOnDebits = false,
        /**
         * The payment methods the desk takes; null when the clinic has not said,
         * which means all of them.
         *
         * @var list<string>|null
         */
        public ?array $acceptedPaymentMethods = null,
    ) {
    }

    public static function empty(): self
    {
        return new self(null, null, null, null, null, null);
    }

    /**
     * @param array<array-key, mixed>|null $acceptedPaymentMethods
     */
    public static function create(
        ?PostalAddress $billingAddress = null,
        ?string $invoiceNumberPrefix = null,
        ?int $paymentTermsDays = null,
        ?string $iban = null,
        ?string $bic = null,
        ?string $invoiceFooterNote = null,
        ?string $creditNotePrefix = null,
        ?int $professionalPaymentTermsDays = null,
        ?VatRegime $vatRegime = null,
        bool $vatOnDebits = false,
        ?array $acceptedPaymentMethods = null,
    ): self {
        $prefix = self::prefix($invoiceNumberPrefix);

        self::checkTerms($paymentTermsDays);
        self::checkTerms($professionalPaymentTermsDays);

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
            creditNotePrefix: self::prefix($creditNotePrefix),
            professionalPaymentTermsDays: $professionalPaymentTermsDays,
            vatRegime: $vatRegime,
            vatOnDebits: $vatOnDebits,
            acceptedPaymentMethods: self::methods($acceptedPaymentMethods),
        );
    }

    /** The prefix the invoices are actually numbered under. */
    public function effectiveInvoicePrefix(): string
    {
        return $this->invoiceNumberPrefix ?? self::DEFAULT_INVOICE_PREFIX;
    }

    /** The prefix the credit notes are actually numbered under. */
    public function effectiveCreditNotePrefix(): string
    {
        return $this->creditNotePrefix ?? self::DEFAULT_CREDIT_NOTE_PREFIX;
    }

    /** Whether the desk takes this payment method. */
    public function accepts(string $method): bool
    {
        return null === $this->acceptedPaymentMethods || \in_array($method, $this->acceptedPaymentMethods, true);
    }

    public function isEmpty(): bool
    {
        return null === $this->billingAddress
            && null === $this->invoiceNumberPrefix
            && null === $this->paymentTermsDays
            && null === $this->iban
            && null === $this->bic
            && null === $this->invoiceFooterNote
            && null === $this->creditNotePrefix
            && null === $this->professionalPaymentTermsDays
            && null === $this->vatRegime
            && false === $this->vatOnDebits
            && null === $this->acceptedPaymentMethods;
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
            && $this->invoiceFooterNote === $other->invoiceFooterNote
            && $this->creditNotePrefix === $other->creditNotePrefix
            && $this->professionalPaymentTermsDays === $other->professionalPaymentTermsDays
            && $this->vatRegime === $other->vatRegime
            && $this->vatOnDebits === $other->vatOnDebits
            && $this->acceptedPaymentMethods === $other->acceptedPaymentMethods;
    }

    private static function prefix(?string $value): ?string
    {
        $prefix = self::clean($value);

        if (null === $prefix) {
            return null;
        }

        $prefix = mb_strtoupper($prefix);

        if (1 !== preg_match(self::PREFIX, $prefix)) {
            throw InvalidBillingSettingsException::malformedPrefix($prefix);
        }

        return $prefix;
    }

    private static function checkTerms(?int $days): void
    {
        if (null !== $days && ($days < 0 || $days > self::MAX_PAYMENT_TERMS_DAYS)) {
            throw InvalidBillingSettingsException::implausiblePaymentTerms($days);
        }
    }

    /**
     * Known methods only, each once, in the order of PAYMENT_METHODS — so that
     * two equal choices compare equal whatever order they were ticked in.
     *
     * @param array<array-key, mixed>|null $methods
     *
     * @return list<string>|null
     */
    private static function methods(?array $methods): ?array
    {
        if (null === $methods) {
            return null;
        }

        foreach ($methods as $method) {
            if (!\is_string($method) || !\in_array($method, self::PAYMENT_METHODS, true)) {
                throw InvalidBillingSettingsException::unknownPaymentMethod(\is_string($method) ? $method : get_debug_type($method));
            }
        }

        return array_values(array_filter(self::PAYMENT_METHODS, static fn (string $known): bool => \in_array($known, $methods, true)));
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
