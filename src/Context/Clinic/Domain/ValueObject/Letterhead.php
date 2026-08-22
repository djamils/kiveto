<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\ValueObject;

use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Shared\Domain\ValueObject\PostalAddress;

/**
 * What the clinic prints at the top and the bottom of every document it hands to
 * a client: where it is, how to reach it, and the identifiers the law expects on
 * an invoice or a prescription.
 *
 * Every field is optional. A clinic that has filled in nothing still prints — it
 * just prints its name and nothing else.
 */
final readonly class Letterhead
{
    private function __construct(
        public ?string $legalName,
        public ?string $legalForm,
        public ?string $shareCapital,
        public ?string $siret,
        public ?string $vatNumber,
        public ?string $rcsCity,
        public ?string $rcsNumber,
        public ?string $apeCode,
        public ?string $ordinalNumber,
        public ?PostalAddress $address,
        public ?PhoneNumber $phone,
        public ?EmailAddress $email,
        public ?string $website,
    ) {
    }

    public static function empty(): self
    {
        return new self(null, null, null, null, null, null, null, null, null, null, null, null, null);
    }

    /**
     * Blank strings are stored as nothing: a form that posts empty inputs must
     * not turn into a letterhead full of empty lines.
     */
    public static function create(
        ?string $legalName = null,
        ?string $legalForm = null,
        ?string $shareCapital = null,
        ?string $siret = null,
        ?string $vatNumber = null,
        ?string $rcsCity = null,
        ?string $rcsNumber = null,
        ?string $apeCode = null,
        ?string $ordinalNumber = null,
        ?PostalAddress $address = null,
        ?string $phone = null,
        ?string $email = null,
        ?string $website = null,
    ): self {
        $normalizedSiret = self::digitsOnly($siret);

        if (null !== $normalizedSiret && 14 !== mb_strlen($normalizedSiret)) {
            throw new \InvalidArgumentException(\sprintf(
                'A SIRET is made of 14 digits, got: "%s".',
                $siret ?? '',
            ));
        }

        $normalizedPhone = self::normalize($phone);
        $normalizedEmail = self::normalize($email);

        return new self(
            legalName: self::normalize($legalName),
            legalForm: self::normalize($legalForm),
            shareCapital: self::normalize($shareCapital),
            siret: $normalizedSiret,
            vatNumber: self::upperNormalize($vatNumber),
            rcsCity: self::normalize($rcsCity),
            rcsNumber: self::normalize($rcsNumber),
            apeCode: self::upperNormalize($apeCode),
            ordinalNumber: self::normalize($ordinalNumber),
            address: $address,
            phone: null !== $normalizedPhone ? PhoneNumber::fromString($normalizedPhone) : null,
            email: null !== $normalizedEmail ? EmailAddress::fromString($normalizedEmail) : null,
            website: self::normalize($website),
        );
    }

    public function isEmpty(): bool
    {
        return null === $this->legalName
            && null === $this->legalForm
            && null === $this->shareCapital
            && null === $this->siret
            && null === $this->vatNumber
            && null === $this->rcsCity
            && null === $this->rcsNumber
            && null === $this->apeCode
            && null === $this->ordinalNumber
            && null === $this->address
            && null === $this->phone
            && null === $this->email
            && null === $this->website;
    }

    public function equals(self $other): bool
    {
        $sameAddress = match (true) {
            null === $this->address  => null === $other->address,
            null === $other->address => false,
            default                  => $this->address->equals($other->address),
        };

        return $sameAddress
            && $this->legalName === $other->legalName
            && $this->legalForm === $other->legalForm
            && $this->shareCapital === $other->shareCapital
            && $this->siret === $other->siret
            && $this->vatNumber === $other->vatNumber
            && $this->rcsCity === $other->rcsCity
            && $this->rcsNumber === $other->rcsNumber
            && $this->apeCode === $other->apeCode
            && $this->ordinalNumber === $other->ordinalNumber
            && $this->phone?->toString() === $other->phone?->toString()
            && $this->email?->toString() === $other->email?->toString()
            && $this->website === $other->website;
    }

    private static function normalize(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    private static function upperNormalize(?string $value): ?string
    {
        $normalized = self::normalize($value);

        return null !== $normalized ? mb_strtoupper($normalized) : null;
    }

    private static function digitsOnly(?string $value): ?string
    {
        $normalized = self::normalize($value);

        if (null === $normalized) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $normalized);

        return null === $digits || '' === $digits ? null : $digits;
    }
}
