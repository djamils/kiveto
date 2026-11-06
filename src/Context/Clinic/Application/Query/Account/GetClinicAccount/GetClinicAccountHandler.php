<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Account\GetClinicAccount;

use App\Context\Clinic\Application\Service\ClinicAccounts;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetClinicAccountHandler
{
    public function __construct(
        private ClinicAccounts $accounts,
    ) {
    }

    public function __invoke(GetClinicAccount $query): ClinicAccountView
    {
        $account    = $this->accounts->of($query->clinicId);
        $suspension = $account->suspension();

        return new ClinicAccountView(
            clinicId: $query->clinicId,
            plan: $account->plan()->value,
            planLabel: $account->plan()->label(),
            pilotEndsOn: $account->pilotEndsOn(),
            parallelSoftware: $account->parallelSoftware()?->value,
            parallelSoftwareLabel: $account->parallelSoftware()?->label(),
            internalNote: $account->internalNote(),
            onboardedAt: $account->onboardedAt(),
            suspensionReason: $suspension?->reason()->value,
            suspensionReasonLabel: $suspension?->reason()->label(),
            suspensionComment: $suspension?->comment(),
            suspendedAt: $suspension?->suspendedAt(),
            suspendedByName: $suspension?->actorName(),
        );
    }
}
