<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Exception;

final class InvalidBillingSettingsException extends \DomainException
{
    public static function malformedPrefix(string $prefix): self
    {
        return new self(\sprintf('Invoice number prefix "%s" is not made of letters, digits, dashes or slashes.', $prefix));
    }

    public static function implausiblePaymentTerms(int $days): self
    {
        return new self(\sprintf('Payment terms of "%d" days are outside what an invoice can carry.', $days));
    }

    public static function malformedIban(string $iban): self
    {
        return new self(\sprintf('IBAN "%s" does not look like an IBAN.', $iban));
    }

    public static function malformedBic(string $bic): self
    {
        return new self(\sprintf('BIC "%s" does not look like a BIC.', $bic));
    }

    public static function unknownPaymentMethod(string $method): self
    {
        return new self(\sprintf('Payment method "%s" is not one a clinic can take.', $method));
    }

    public static function unknownVatRegime(string $regime): self
    {
        return new self(\sprintf('VAT regime "%s" is not one a clinic can file under.', $regime));
    }
}
