<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Port;

interface AnimalReadRepositoryPort
{
    public function findByMicrochip(string $microchipNumber, string $clinicId): ?AnimalChipView;
}
