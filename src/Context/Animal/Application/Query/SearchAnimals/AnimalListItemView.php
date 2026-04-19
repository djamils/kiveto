<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\SearchAnimals;

final readonly class AnimalListItemView
{
    public function __construct(
        public string $id,
        public string $name,
        public string $species,
        public string $sex,
        public ?string $breedName,
        public ?string $birthDate,
        public ?string $color,
        public ?string $microchipNumber,
        public string $status,
        public string $lifeStatus,
        public ?string $primaryOwnerClientId,
        public string $createdAt,
    ) {
    }

    public function ageLabel(?\DateTimeImmutable $now = null): ?string
    {
        if (null === $this->birthDate) {
            return null;
        }

        $birth = new \DateTimeImmutable($this->birthDate);
        $now   = $now ?? new \DateTimeImmutable();
        $diff  = $now->diff($birth);

        if (0 === $diff->days) {
            return '< 1 j';
        }

        if ($diff->y >= 2) {
            return $diff->y . ' ans';
        }

        $months = $diff->y * 12 + $diff->m;
        if ($months >= 1) {
            return $months . ' mois';
        }

        return $diff->days . ' j';
    }
}
