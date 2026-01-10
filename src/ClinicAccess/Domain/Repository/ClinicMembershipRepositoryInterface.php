<?php

declare(strict_types=1);

namespace App\ClinicAccess\Domain\Repository;

use App\Clinic\Domain\ValueObject\ClinicId;
use App\ClinicAccess\Domain\ClinicMembership;
use App\ClinicAccess\Domain\ValueObject\MembershipId;
use App\IdentityAccess\Domain\ValueObject\UserId;

interface ClinicMembershipRepositoryInterface
{
    public function save(ClinicMembership $membership): void;

    public function findById(MembershipId $id): ?ClinicMembership;

    public function findByClinicAndUser(ClinicId $clinicId, UserId $userId): ?ClinicMembership;

    public function existsByClinicAndUser(ClinicId $clinicId, UserId $userId): bool;
}
