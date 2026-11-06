<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Account\ReviseClinicAccount;

use App\Context\Clinic\Application\Service\ClinicAccountDates;
use App\Context\Clinic\Application\Service\ClinicAccounts;
use App\Context\Clinic\Domain\Account\ClinicAccountRepositoryInterface;
use App\Context\Clinic\Domain\Account\ClinicPlan;
use App\Context\Clinic\Domain\Account\ParallelSoftware;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ReviseClinicAccountHandler
{
    public function __construct(
        private ClinicAccounts $accounts,
        private ClinicAccountRepositoryInterface $repository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ReviseClinicAccount $command): void
    {
        $account = $this->accounts->of($command->clinicId);

        $account->revise(
            ClinicPlan::from($command->plan),
            ClinicAccountDates::day($command->pilotEndsOn),
            null !== $command->parallelSoftware && '' !== $command->parallelSoftware ? ParallelSoftware::from($command->parallelSoftware) : null,
            $command->internalNote,
            $this->clock->now(),
        );

        $this->repository->save($account);
    }
}
