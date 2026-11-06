<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Account\GetClinicOnboarding;

use App\Context\Clinic\Application\Service\ClinicOnboardingChecklist;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetClinicOnboardingHandler
{
    public function __construct(
        private ClinicOnboardingChecklist $checklist,
    ) {
    }

    public function __invoke(GetClinicOnboarding $query): ClinicOnboardingView
    {
        return $this->checklist->of($query->clinicId);
    }
}
