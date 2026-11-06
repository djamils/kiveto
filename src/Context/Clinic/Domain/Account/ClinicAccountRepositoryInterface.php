<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Account;

use App\Context\Clinic\Domain\ValueObject\ClinicId;

interface ClinicAccountRepositoryInterface
{
    public function save(ClinicAccount $account): void;

    /**
     * Null for a clinic whose account was never written (created before the
     * console): callers use ClinicAccount::defaultFor().
     */
    public function findByClinicId(ClinicId $clinicId): ?ClinicAccount;
}
