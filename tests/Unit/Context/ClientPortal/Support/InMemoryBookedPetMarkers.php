<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Domain\Booking\BookedPetMarkerRepositoryInterface;

final class InMemoryBookedPetMarkers implements BookedPetMarkerRepositoryInterface
{
    /** @var array<string, array{string, \DateTimeImmutable}> marker → animal, remembered at */
    public array $rows = [];

    public function animalFor(string $marker, \DateTimeImmutable $since): ?string
    {
        $row = $this->rows[$marker] ?? null;

        return null !== $row && $row[1] >= $since ? $row[0] : null;
    }

    public function remember(string $marker, string $animalId, \DateTimeImmutable $now, \DateTimeImmutable $expiredBefore): void
    {
        $this->rows = array_filter($this->rows, static fn (array $row): bool => $row[1] >= $expiredBefore);

        $this->rows[$marker] = [$animalId, $now];
    }
}
