<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Support;

use App\Context\Clinic\Domain\Account\ClinicAccount;
use App\Context\Clinic\Domain\Account\ClinicAccountRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicId;

final class InMemoryClinicAccounts implements ClinicAccountRepositoryInterface
{
    /** @var array<string, ClinicAccount> */
    public array $accounts = [];

    /** @var list<string> clinic ids saved, in order */
    public array $saved = [];

    public function save(ClinicAccount $account): void
    {
        $this->accounts[$account->clinicId()->toString()] = $account;
        $this->saved[]                                    = $account->clinicId()->toString();
    }

    public function findByClinicId(ClinicId $clinicId): ?ClinicAccount
    {
        return $this->accounts[$clinicId->toString()] ?? null;
    }
}
