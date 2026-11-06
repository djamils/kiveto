<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Account\ReactivateClinic;

use App\Context\Clinic\Application\Service\ClinicAccounts;
use App\Context\Clinic\Domain\Account\ClinicAccountRepositoryInterface;
use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ReactivateClinicHandler
{
    public function __construct(
        private ClinicAccounts $accounts,
        private ClinicRepositoryInterface $clinics,
        private ClinicAccountRepositoryInterface $accountRepository,
        private ClockInterface $clock,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(ReactivateClinic $command): void
    {
        $clinic  = $this->accounts->clinic($command->clinicId);
        $account = $this->accounts->of($command->clinicId);
        $now     = $this->clock->now();

        $clinic->activate($now);
        $account->clearSuspension($now);

        $this->clinics->save($clinic);
        $this->accountRepository->save($account);
        $this->domainEventPublisher->publish($clinic);
    }
}
