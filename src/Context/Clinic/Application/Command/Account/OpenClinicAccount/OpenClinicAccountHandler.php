<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Account\OpenClinicAccount;

use App\Context\Clinic\Application\Service\ClinicAccountDates;
use App\Context\Clinic\Domain\Account\ClinicAccount;
use App\Context\Clinic\Domain\Account\ClinicAccountRepositoryInterface;
use App\Context\Clinic\Domain\Account\ClinicPlan;
use App\Context\Clinic\Domain\Account\ParallelSoftware;
use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class OpenClinicAccountHandler
{
    public function __construct(
        private ClinicRepositoryInterface $clinics,
        private ClinicAccountRepositoryInterface $accounts,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(OpenClinicAccount $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);

        if (null === $this->clinics->findById($clinicId)) {
            throw new \RuntimeException(\sprintf('Clinic with ID "%s" not found.', $command->clinicId));
        }

        $this->accounts->save(ClinicAccount::open(
            $clinicId,
            ClinicPlan::from($command->plan),
            ClinicAccountDates::day($command->pilotEndsOn),
            null !== $command->parallelSoftware ? ParallelSoftware::from($command->parallelSoftware) : null,
            $this->clock->now(),
        ));
    }
}
