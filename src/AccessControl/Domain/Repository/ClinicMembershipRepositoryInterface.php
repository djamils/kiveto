<?php

declare(strict_types=1);

namespace App\AccessControl\Domain\Repository;

use App\AccessControl\Domain\ClinicMembership;
use App\AccessControl\Domain\ValueObject\MembershipId;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\IdentityAccess\Domain\ValueObject\UserId;

interface ClinicMembershipRepositoryInterface
{
    public function save(ClinicMembership $membership): void;

    public function findById(MembershipId $id): ?ClinicMembership;

    public function findByClinicAndUser(ClinicId $clinicId, UserId $userId): ?ClinicMembership;

    public function existsByClinicAndUser(ClinicId $clinicId, UserId $userId): bool;
}
