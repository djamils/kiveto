<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Staff\Repository;

use App\Context\Clinic\Domain\Staff\ClinicMembership;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;

interface ClinicMembershipRepositoryInterface
{
    public function save(ClinicMembership $membership): void;

    public function findById(ClinicMembershipId $id): ?ClinicMembership;

    public function findByClinicAndUser(ClinicId $clinicId, UserId $userId): ?ClinicMembership;

    public function existsByClinicAndUser(ClinicId $clinicId, UserId $userId): bool;

    public function findDefaultForUser(UserId $userId): ?ClinicMembership;

    public function saveAll(ClinicMembership ...$memberships): void;
}
