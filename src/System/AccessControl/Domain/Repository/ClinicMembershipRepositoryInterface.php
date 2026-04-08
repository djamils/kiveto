<?php

declare(strict_types=1);

namespace App\System\AccessControl\Domain\Repository;

use App\System\AccessControl\Domain\ClinicMembership;
use App\System\AccessControl\Domain\ValueObject\ClinicId;
use App\System\AccessControl\Domain\ValueObject\MembershipId;
use App\System\AccessControl\Domain\ValueObject\UserId;

interface ClinicMembershipRepositoryInterface
{
    public function save(ClinicMembership $membership): void;

    public function findById(MembershipId $id): ?ClinicMembership;

    public function findByClinicAndUser(ClinicId $clinicId, UserId $userId): ?ClinicMembership;

    public function existsByClinicAndUser(ClinicId $clinicId, UserId $userId): bool;
}
