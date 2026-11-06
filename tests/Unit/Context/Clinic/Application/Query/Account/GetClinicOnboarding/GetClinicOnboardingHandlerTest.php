<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\Account\GetClinicOnboarding;

use App\Context\Clinic\Application\Query\Account\GetClinicOnboarding\GetClinicOnboarding;
use App\Context\Clinic\Application\Query\Account\GetClinicOnboarding\GetClinicOnboardingHandler;
use App\Context\Clinic\Application\Query\Account\GetClinicOnboarding\OnboardingStepView;
use App\Context\Clinic\Application\Service\ClinicAccounts;
use App\Context\Clinic\Application\Service\ClinicOnboardingChecklist;
use App\Tests\Unit\Context\Clinic\Support\ConsoleFakes;
use App\Tests\Unit\Context\Clinic\Support\InMemoryClinicAccounts;
use App\Tests\Unit\Context\Clinic\Support\InMemoryClinics;
use PHPUnit\Framework\TestCase;

final class GetClinicOnboardingHandlerTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000c9a1';

    public function testAnswersTheChecklistOfTheClinic(): void
    {
        $clinics         = new InMemoryClinics()->add(InMemoryClinics::clinic(self::CLINIC));
        $console         = new ConsoleFakes();
        $console->booked = [self::CLINIC];

        $view = new GetClinicOnboardingHandler(new ClinicOnboardingChecklist(new ClinicAccounts($clinics, new InMemoryClinicAccounts()), $console, $console, $console))(
            new GetClinicOnboarding(self::CLINIC),
        );

        self::assertSame(['created', 'invited', 'accepted', 'imported', 'hours', 'appointment'], array_map(static fn (OnboardingStepView $step): string => $step->key, $view->steps));
        self::assertSame([2, 5], [$view->done(), $view->total()]);
    }
}
