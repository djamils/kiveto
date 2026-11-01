<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClinicListing\Support;

use App\Context\ClinicListing\Application\Port\ClinicListingReadModelInterface;
use App\Context\ClinicListing\Application\Port\ListingRow;

/** Answers the rows it is given (cut to the limit) and keeps every call. */
final class FakeListingReadModel implements ClinicListingReadModelInterface
{
    /** @var list<ListingRow> */
    public array $rows = [];

    /** @var list<array<string, mixed>> */
    public array $calls = [];

    public static function row(string $id, string $clinicId, string $name, bool $acceptsNewClients = true, ?int $distanceM = null, ?string $city = 'Paris'): ListingRow
    {
        return new ListingRow($id, $clinicId, $name, null === $city ? null : '1 rue de la Paix', '75002', $city, '+33142551890', 'Europe/Paris', [['day' => 2, 'opensAt' => '08:30', 'closesAt' => '12:00']], $acceptsNewClients, ['NAC'], $distanceM);
    }

    public function searchByName(?string $q, ?string $afterName, ?string $afterId, int $limit): array
    {
        $this->calls[] = ['by' => 'name', 'q' => $q, 'afterName' => $afterName, 'afterId' => $afterId, 'limit' => $limit];

        return \array_slice($this->rows, 0, $limit);
    }

    public function searchByDistance(?string $q, float $latitude, float $longitude, int $radiusM, ?int $afterDistanceM, ?string $afterId, int $limit): array
    {
        $this->calls[] = ['by' => 'distance', 'q' => $q, 'lat' => $latitude, 'lng' => $longitude, 'radius' => $radiusM, 'afterDistance' => $afterDistanceM, 'afterId' => $afterId, 'limit' => $limit];

        return \array_slice($this->rows, 0, $limit);
    }

    public function findListed(string $listingId): ?ListingRow
    {
        foreach ($this->rows as $row) {
            if ($row->id === $listingId) {
                return $row;
            }
        }

        return null;
    }
}
