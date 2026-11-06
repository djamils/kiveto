<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Service;

use App\Context\Clinic\Domain\Account\ClinicAccount;
use App\Context\Clinic\Domain\Account\ClinicAccountRepositoryInterface;
use App\Context\Clinic\Domain\Clinic;
use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicId;

/**
 * The account of an existing clinic: the stored one, or the default of a
 * clinic created before the console.
 */
final readonly class ClinicAccounts
{
    public function __construct(
        private ClinicRepositoryInterface $clinics,
        private ClinicAccountRepositoryInterface $accounts,
    ) {
    }

    public function of(string $clinicId): ClinicAccount
    {
        $clinic = $this->clinic($clinicId);

        return $this->accounts->findByClinicId($clinic->id()) ?? ClinicAccount::defaultFor($clinic->id(), $clinic->createdAt());
    }

    public function clinic(string $clinicId): Clinic
    {
        return $this->clinics->findById(ClinicId::fromString($clinicId))
            ?? throw new \RuntimeException(\sprintf('Clinic with ID "%s" not found.', $clinicId));
    }
}
