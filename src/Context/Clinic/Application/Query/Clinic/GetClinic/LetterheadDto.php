<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Clinic\GetClinic;

use App\Context\Clinic\Domain\ValueObject\Letterhead;

/**
 * The identity block as the documents need it: flat strings, plus the address
 * already folded into the lines a letterhead prints.
 */
final readonly class LetterheadDto
{
    /**
     * @param list<string> $addressLines
     */
    public function __construct(
        public ?string $legalName,
        public ?string $legalForm,
        public ?string $shareCapital,
        public ?string $siret,
        public ?string $vatNumber,
        public ?string $rcsCity,
        public ?string $rcsNumber,
        public ?string $apeCode,
        public ?string $ordinalNumber,
        public array $addressLines,
        /** Where the document is drawn up: "Fait à …". */
        public ?string $city,
        public ?string $phone,
        public ?string $email,
        public ?string $website,
    ) {
    }

    public static function fromDomain(Letterhead $letterhead): self
    {
        $address = $letterhead->address;
        $lines   = [];

        if (null !== $address) {
            $lines[] = $address->streetLine1;

            if (null !== $address->streetLine2) {
                $lines[] = $address->streetLine2;
            }

            $lines[] = trim(\sprintf('%s %s', $address->postalCode ?? '', $address->city));
        }

        return new self(
            legalName: $letterhead->legalName,
            legalForm: $letterhead->legalForm,
            shareCapital: $letterhead->shareCapital,
            siret: $letterhead->siret,
            vatNumber: $letterhead->vatNumber,
            rcsCity: $letterhead->rcsCity,
            rcsNumber: $letterhead->rcsNumber,
            apeCode: $letterhead->apeCode,
            ordinalNumber: $letterhead->ordinalNumber,
            addressLines: array_values(array_filter($lines, static fn (string $line): bool => '' !== $line)),
            city: $address?->city,
            phone: $letterhead->phone?->toString(),
            email: $letterhead->email?->toString(),
            website: $letterhead->website,
        );
    }

    /**
     * The SIRET as it is written on paper: 3 groups of 3, then 5.
     */
    public function formattedSiret(): ?string
    {
        if (null === $this->siret) {
            return null;
        }

        return implode(' ', [
            mb_substr($this->siret, 0, 3),
            mb_substr($this->siret, 3, 3),
            mb_substr($this->siret, 6, 3),
            mb_substr($this->siret, 9, 5),
        ]);
    }

    /**
     * "SELARL au capital de 30 000 €, RCS Paris 812 456 903" — whichever of those
     * the clinic has filled in.
     */
    public function legalSentence(): ?string
    {
        $parts = [];

        if (null !== $this->legalForm) {
            $parts[] = $this->legalForm;
        }

        if (null !== $this->shareCapital) {
            $parts[] = \sprintf('au capital de %s', $this->shareCapital);
        }

        if (null !== $this->rcsNumber) {
            $parts[] = null !== $this->rcsCity
                ? \sprintf('RCS %s %s', $this->rcsCity, $this->rcsNumber)
                : \sprintf('RCS %s', $this->rcsNumber);
        }

        if (null !== $this->apeCode) {
            $parts[] = \sprintf('code APE %s', $this->apeCode);
        }

        return [] === $parts ? null : implode(', ', $parts);
    }
}
