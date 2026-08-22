<?php

declare(strict_types=1);

namespace App\Context\Prescription\Domain\ValueObject;

use App\Context\Prescription\Domain\Exception\InvalidPatientSnapshotException;

/**
 * The animal the medicines are for, as it stood the day the ordonnance was
 * written. A prescription names its patient; a rename or a new weight later
 * must not rewrite the document.
 */
final class PatientSnapshot
{
    private function __construct(
        private readonly ?string $animalId,
        private readonly string $name,
        private readonly ?string $species,
        private readonly ?string $breed,
        private readonly ?\DateTimeImmutable $birthDate,
        private readonly ?string $weightKg,
        private readonly ?string $microchipNumber,
    ) {
    }

    public static function create(
        ?string $animalId,
        string $name,
        ?string $species = null,
        ?string $breed = null,
        ?\DateTimeImmutable $birthDate = null,
        ?string $weightKg = null,
        ?string $microchipNumber = null,
    ): self {
        $name = mb_trim($name);

        if ('' === $name) {
            throw new InvalidPatientSnapshotException('The patient must be named on a prescription.');
        }

        if (null !== $weightKg && 1 !== preg_match('/^\d+(\.\d{1,3})?$/', $weightKg)) {
            throw new InvalidPatientSnapshotException(\sprintf('Invalid weight "%s".', $weightKg));
        }

        return new self(
            $animalId,
            $name,
            self::clean($species),
            self::clean($breed),
            $birthDate,
            $weightKg,
            self::clean($microchipNumber),
        );
    }

    public function animalId(): ?string
    {
        return $this->animalId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function species(): ?string
    {
        return $this->species;
    }

    public function breed(): ?string
    {
        return $this->breed;
    }

    public function birthDate(): ?\DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function weightKg(): ?string
    {
        return $this->weightKg;
    }

    public function microchipNumber(): ?string
    {
        return $this->microchipNumber;
    }

    private static function clean(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = mb_trim($value);

        return '' === $value ? null : $value;
    }
}
