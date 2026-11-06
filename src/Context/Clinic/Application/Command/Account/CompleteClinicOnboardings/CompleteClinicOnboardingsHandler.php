<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Account\CompleteClinicOnboardings;

use App\Context\Clinic\Application\Port\ClinicConsoleReadRepositoryInterface;
use App\Context\Clinic\Application\Query\Console\ConsoleClinicRow;
use App\Context\Clinic\Application\Service\ClinicAccounts;
use App\Context\Clinic\Application\Service\ClinicOnboardingChecklist;
use App\Context\Clinic\Domain\Account\ClinicAccountRepositoryInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CompleteClinicOnboardingsHandler
{
    public function __construct(
        private ClinicConsoleReadRepositoryInterface $console,
        private ClinicOnboardingChecklist $checklist,
        private ClinicAccounts $accounts,
        private ClinicAccountRepositoryInterface $repository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CompleteClinicOnboardings $command): int
    {
        $onboarding = array_filter(
            $this->console->clinics(),
            static fn (ConsoleClinicRow $clinic): bool => !$clinic->isSuspended() && null === $clinic->onboardedAt,
        );

        $completed = 0;
        foreach ($onboarding as $clinic) {
            if (!$this->checklist->of($clinic->id)->isComplete()) {
                continue;
            }

            $account = $this->accounts->of($clinic->id);

            if ($account->markOnboarded($this->clock->now())) {
                $this->repository->save($account);
                ++$completed;
            }
        }

        return $completed;
    }
}
