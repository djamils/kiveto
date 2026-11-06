<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\Account;

use App\Context\Clinic\Domain\Account\ClinicPlan;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClinicPlanTest extends TestCase
{
    #[DataProvider('provideOnlyAPaidClinicIsBilledCases')]
    public function testOnlyAPaidClinicIsBilled(ClinicPlan $plan, string $label, bool $billed): void
    {
        self::assertSame([$label, $billed], [$plan->label(), $plan->isBilled()]);
    }

    /**
     * @return iterable<string, array{ClinicPlan, string, bool}>
     */
    public static function provideOnlyAPaidClinicIsBilledCases(): iterable
    {
        yield 'pilot' => [ClinicPlan::PILOT, 'Pilote (gratuit)', false];

        yield 'paid' => [ClinicPlan::PAID, 'Payant', true];

        yield 'internal' => [ClinicPlan::INTERNAL, 'Interne (non facturée)', false];
    }
}
