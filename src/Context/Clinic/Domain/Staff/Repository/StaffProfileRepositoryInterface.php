<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Staff\Repository;

use App\Context\Clinic\Domain\Staff\StaffProfile;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\StaffProfileId;

interface StaffProfileRepositoryInterface
{
    public function save(StaffProfile $profile): void;

    public function findById(StaffProfileId $id): ?StaffProfile;

    public function findByMembershipId(ClinicMembershipId $membershipId): ?StaffProfile;
}
