<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Adapter\Animal;

use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\GetAnimalById\GetAnimalById;
use App\Context\Consultation\Application\Port\ReportPatient;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;

/**
 * The animal of a patient (GetAnimalById), worded for the consultation report,
 * with the client id of its primary owner. Used by the patient snapshot
 * provider.
 */
final readonly class QueryBusAnimalReportReader
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array{patient: ReportPatient, ownerClientId: string|null}|null null when the animal cannot be read
     */
    public function read(string $clinicId, string $animalId): ?array
    {
        try {
            $animal = $this->queryBus->ask(new GetAnimalById($clinicId, $animalId));
        } catch (\Throwable) {
            return null;
        }

        if (!$animal instanceof AnimalView) {
            return null;
        }

        return [
            'patient' => new ReportPatient(
                name: $animal->name,
                speciesLabel: self::speciesLabel($animal->species),
                breed: $animal->isMixedBreed ? 'Croisé' : $animal->breedName,
                sexLabel: self::sexLabel($animal->sex),
                sterilized: 'neutered' === $animal->reproductiveStatus,
                birthDate: $animal->birthDate,
                ageLabel: $this->ageLabel($animal->birthDate),
                microchip: $animal->identification->microchipNumber,
            ),
            'ownerClientId' => $animal->primaryOwnerClientId(),
        ];
    }

    private static function speciesLabel(string $species): string
    {
        return match (mb_strtolower($species)) {
            'dog'   => 'Chien',
            'cat'   => 'Chat',
            'nac'   => 'NAC',
            default => 'Autre',
        };
    }

    private static function sexLabel(string $sex): ?string
    {
        return match (mb_strtolower($sex)) {
            'male'   => '♂',
            'female' => '♀',
            default  => null,
        };
    }

    private function ageLabel(?string $birthDate): ?string
    {
        if (null === $birthDate) {
            return null;
        }

        try {
            $birth = new \DateTimeImmutable($birthDate);
        } catch (\Exception) {
            return null;
        }

        $interval = $birth->diff($this->clock->now());

        if ($interval->y > 0) {
            return \sprintf('%d an%s', $interval->y, $interval->y > 1 ? 's' : '');
        }

        if ($interval->m > 0) {
            return \sprintf('%d mois', $interval->m);
        }

        return \sprintf('%d jour%s', $interval->d, $interval->d > 1 ? 's' : '');
    }
}
