<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Port;

use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;

interface StaffProfileReadRepositoryInterface
{
    /**
     * Batch lookup: returns a map of membershipId (string) → StaffProfileReadItem.
     * Missing profiles are simply absent from the returned map.
     *
     * @param list<string> $membershipIds
     *
     * @return array<string, StaffProfileReadItem>
     */
    public function findByMembershipIds(array $membershipIds): array;

    public function findByMembershipId(string $membershipId): ?StaffProfileReadItem;

    public function hasVeterinaryCredentialsFor(ClinicMembershipId $membershipId): bool;
}
