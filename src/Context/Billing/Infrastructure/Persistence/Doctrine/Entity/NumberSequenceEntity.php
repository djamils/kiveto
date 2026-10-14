<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * The counter behind invoice and credit note numbers, one row per clinic,
 * series and year.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_number_sequence', columns: ['clinic_id', 'series', 'year'])]
class NumberSequenceEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\Column(type: 'string', length: 16, options: ['default' => 'INVOICE'])]
    private string $series = 'INVOICE';

    #[ORM\Column(type: 'smallint')]
    private int $year;

    #[ORM\Column(name: 'last_value', type: 'integer')]
    private int $lastValue = 0;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getClinicId(): Uuid
    {
        return $this->clinicId;
    }

    public function setClinicId(Uuid $clinicId): void
    {
        $this->clinicId = $clinicId;
    }

    public function getSeries(): string
    {
        return $this->series;
    }

    public function setSeries(string $series): void
    {
        $this->series = $series;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function setYear(int $year): void
    {
        $this->year = $year;
    }

    public function getLastValue(): int
    {
        return $this->lastValue;
    }

    public function setLastValue(int $lastValue): void
    {
        $this->lastValue = $lastValue;
    }
}
