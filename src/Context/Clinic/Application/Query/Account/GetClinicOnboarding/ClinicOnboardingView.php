<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Account\GetClinicOnboarding;

final readonly class ClinicOnboardingView
{
    /**
     * @param list<OnboardingStepView> $steps
     */
    public function __construct(
        public array $steps,
        public ?\DateTimeImmutable $onboardedAt,
    ) {
    }

    public function done(): int
    {
        return \count(array_filter($this->steps, static fn (OnboardingStepView $step): bool => $step->counted && $step->done));
    }

    public function total(): int
    {
        return \count(array_filter($this->steps, static fn (OnboardingStepView $step): bool => $step->counted));
    }

    /**
     * Onboarded once (the date is kept), or every counted step done now.
     */
    public function isComplete(): bool
    {
        return null !== $this->onboardedAt || $this->done() === $this->total();
    }
}
