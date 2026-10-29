<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Staff\Repository;

use App\Context\Clinic\Domain\Staff\ClinicMembership;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;

interface ClinicMembershipRepositoryInterface
{
    /**
     * How many memberships in this clinic both wear the administration hat and
     * are in force at $now. $excluding answers "would anyone be left besides
     * this one?" before a write that is about to remove it.
     */
    public function countEffectiveAdministrators(
        ClinicId $clinicId,
        \DateTimeImmutable $now,
        ?ClinicMembershipId $excluding = null,
    ): int;

    /**
     * The users whose membership in this clinic is in force at $now (status
     * and validity window), optionally of one role, optionally only those
     * wearing the administration hat.
     *
     * @return list<string> user ids
     */
    public function findEffectiveUserIds(
        ClinicId $clinicId,
        \DateTimeImmutable $now,
        ?ClinicMemberRole $role = null,
        bool $administratorsOnly = false,
    ): array;

    public function save(ClinicMembership $membership): void;

    public function findById(ClinicMembershipId $id): ?ClinicMembership;

    public function findByClinicAndUser(ClinicId $clinicId, UserId $userId): ?ClinicMembership;

    public function existsByClinicAndUser(ClinicId $clinicId, UserId $userId): bool;

    public function findDefaultForUser(UserId $userId): ?ClinicMembership;

    public function saveAll(ClinicMembership ...$memberships): void;
}
