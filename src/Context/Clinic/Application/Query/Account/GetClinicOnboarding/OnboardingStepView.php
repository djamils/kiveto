<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Account\GetClinicOnboarding;

/**
 * One step of « Mise en route ». A step that is not counted (pre-alpha) is
 * shown greyed and does not hold the clinic in onboarding.
 */
final readonly class OnboardingStepView
{
    public function __construct(
        public string $key,
        public string $label,
        public bool $done,
        public string $detail,
        public bool $counted = true,
    ) {
    }
}
