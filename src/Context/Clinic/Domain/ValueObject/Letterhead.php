<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\ValueObject;

use App\Context\Clinic\Domain\Exception\InvalidLetterheadException;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\FrenchVatNumber;
use App\Shared\Domain\ValueObject\InvalidFrenchVatNumberException;
use App\Shared\Domain\ValueObject\InvalidSirenException;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Shared\Domain\ValueObject\PostalAddress;
use App\Shared\Domain\ValueObject\Siren;

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
    private const string LA_POSTE_SIREN = '356000000';

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
        /** The regional council of the Order the company is registered with. */
        public ?OrdinalCouncil $ordinalCouncil = null,
        /** Who insures the clinic's professional liability, printed on invoices. */
        public ?string $liabilityInsurer = null,
        public ?string $liabilityPolicyNumber = null,
    ) {
    }

    public static function empty(): self
    {
        return new self(null, null, null, null, null, null, null, null, null, null, null, null, null);
    }

    /**
     * The letterhead as the clinic types it: blank strings are stored as nothing,
     * and the fiscal identity is checked — the SIRET (its SIREN and its own check
     * digit) and the VAT number, which must be the one of that SIREN.
     *
     * @throws InvalidLetterheadException
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
        ?string $ordinalCouncil = null,
        ?string $liabilityInsurer = null,
        ?string $liabilityPolicyNumber = null,
    ): self {
        $normalizedSiret = self::digitsOnly($siret);
        $normalizedVat   = self::upperNormalize($vatNumber);
        $siren           = null !== $normalizedSiret ? self::sirenOf($normalizedSiret) : null;

        if (null !== $normalizedVat) {
            if (null === $siren) {
                throw new InvalidLetterheadException(InvalidLetterheadException::FIELD_SIRET, InvalidLetterheadException::REASON_MISSING, '');
            }

            $normalizedVat = self::vatNumberOf($normalizedVat, $siren)->value();
        }

        $council = self::normalize($ordinalCouncil);

        if (null !== $council && null === OrdinalCouncil::tryFrom($council)) {
            throw new \InvalidArgumentException(\sprintf('Unknown regional council of the Order: "%s".', $council));
        }

        return self::reconstitute(
            legalName: $legalName,
            legalForm: $legalForm,
            shareCapital: $shareCapital,
            siret: $normalizedSiret,
            vatNumber: $normalizedVat,
            rcsCity: $rcsCity,
            rcsNumber: $rcsNumber,
            apeCode: $apeCode,
            ordinalNumber: $ordinalNumber,
            address: $address,
            phone: $phone,
            email: $email,
            website: $website,
            ordinalCouncil: $council,
            liabilityInsurer: $liabilityInsurer,
            liabilityPolicyNumber: $liabilityPolicyNumber,
        );
    }

    /**
     * The letterhead as it was stored, without checking the fiscal identity
     * again: a row saved before the checks existed must still load, and simply
     * reads back with no valid SIREN.
     */
    public static function reconstitute(
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
        ?string $ordinalCouncil = null,
        ?string $liabilityInsurer = null,
        ?string $liabilityPolicyNumber = null,
    ): self {
        $normalizedPhone = self::normalize($phone);
        $normalizedEmail = self::normalize($email);
        $council         = self::normalize($ordinalCouncil);

        return new self(
            legalName: self::normalize($legalName),
            legalForm: self::normalize($legalForm),
            shareCapital: self::normalize($shareCapital),
            siret: self::digitsOnly($siret),
            vatNumber: self::upperNormalize($vatNumber),
            rcsCity: self::normalize($rcsCity),
            rcsNumber: self::normalize($rcsNumber),
            apeCode: self::upperNormalize($apeCode),
            ordinalNumber: self::normalize($ordinalNumber),
            address: $address,
            phone: null !== $normalizedPhone ? PhoneNumber::fromString($normalizedPhone) : null,
            email: null !== $normalizedEmail ? EmailAddress::fromString($normalizedEmail) : null,
            website: self::normalize($website),
            ordinalCouncil: null !== $council ? OrdinalCouncil::tryFrom($council) : null,
            liabilityInsurer: self::normalize($liabilityInsurer),
            liabilityPolicyNumber: self::upperNormalize($liabilityPolicyNumber),
        );
    }

    /** The company the SIRET belongs to; null when there is no SIRET or it is not a valid one. */
    public function siren(): ?Siren
    {
        if (null === $this->siret) {
            return null;
        }

        try {
            return self::sirenOf($this->siret);
        } catch (InvalidLetterheadException) {
            return null;
        }
    }

    /** The VAT number, when there is one and it is the one of a valid SIREN. */
    public function vatNumber(): ?FrenchVatNumber
    {
        $siren = $this->siren();

        if (null === $this->vatNumber || null === $siren) {
            return null;
        }

        try {
            return self::vatNumberOf($this->vatNumber, $siren);
        } catch (InvalidLetterheadException) {
            return null;
        }
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
            && null === $this->website
            && null === $this->ordinalCouncil
            && null === $this->liabilityInsurer
            && null === $this->liabilityPolicyNumber;
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
            && $this->website === $other->website
            && $this->ordinalCouncil === $other->ordinalCouncil
            && $this->liabilityInsurer === $other->liabilityInsurer
            && $this->liabilityPolicyNumber === $other->liabilityPolicyNumber;
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

    /**
     * A SIRET is the SIREN of the company followed by 5 digits for the
     * establishment, the whole of it carrying a Luhn check digit — except the
     * establishments of La Poste (SIREN 356 000 000), whose SIRETs only have a
     * digit sum that is a multiple of 5, as INSEE accepts them.
     *
     * @throws InvalidLetterheadException
     */
    private static function sirenOf(string $siret): Siren
    {
        if (1 !== preg_match('/^\d{14}$/', $siret)) {
            throw new InvalidLetterheadException(InvalidLetterheadException::FIELD_SIRET, InvalidLetterheadException::REASON_FORMAT, $siret);
        }

        try {
            $siren = Siren::fromInput($siret);
        } catch (InvalidSirenException) {
            throw new InvalidLetterheadException(InvalidLetterheadException::FIELD_SIRET, InvalidLetterheadException::REASON_CHECKSUM, $siret);
        }

        $valid = str_starts_with($siret, self::LA_POSTE_SIREN)
            ? 0 === array_sum(array_map(intval(...), str_split($siret))) % 5
            : self::passesLuhn($siret);

        if (!$valid) {
            throw new InvalidLetterheadException(InvalidLetterheadException::FIELD_SIRET, InvalidLetterheadException::REASON_CHECKSUM, $siret);
        }

        return $siren;
    }

    /**
     * @throws InvalidLetterheadException
     */
    private static function vatNumberOf(string $vatNumber, Siren $siren): FrenchVatNumber
    {
        try {
            return FrenchVatNumber::fromInput($vatNumber, $siren);
        } catch (InvalidFrenchVatNumberException $exception) {
            throw new InvalidLetterheadException(InvalidLetterheadException::FIELD_VAT_NUMBER, $exception->reason, $vatNumber);
        }
    }

    private static function passesLuhn(string $digits): bool
    {
        $sum = 0;

        foreach (array_reverse(str_split($digits)) as $position => $digit) {
            $n = (int) $digit;

            if (1 === $position % 2) {
                $n *= 2;
                $n = $n > 9 ? $n - 9 : $n;
            }

            $sum += $n;
        }

        return 0 === $sum % 10;
    }
}
