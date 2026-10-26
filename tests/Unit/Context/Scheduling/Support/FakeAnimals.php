<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Support;

use App\Context\Scheduling\Application\Port\AnimalDirectoryInterface;

final class FakeAnimals implements AnimalDirectoryInterface
{
    /** @var array<string, string> */
    public array $names = [Appointments::ANIMAL => 'Milo'];

    public function nameOf(string $clinicId, string $animalId): ?string
    {
        return $this->names[$animalId] ?? null;
    }
}
