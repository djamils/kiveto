<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\Staff\ValueObject;

use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClinicMembershipEngagementTest extends TestCase
{
    /**
     * @return iterable<string, array{ClinicMembershipEngagement, bool}>
     */
    public static function provideTellsTheContractsThatEndByThemselvesCases(): iterable
    {
        yield 'partner' => [ClinicMembershipEngagement::PARTNER, false];
        yield 'open-ended employee' => [ClinicMembershipEngagement::EMPLOYEE, false];
        yield 'fixed-term employee' => [ClinicMembershipEngagement::FIXED_TERM, true];
        yield 'contractor' => [ClinicMembershipEngagement::CONTRACTOR, false];
        yield 'replacement' => [ClinicMembershipEngagement::REPLACEMENT, true];
        yield 'intern' => [ClinicMembershipEngagement::INTERN, true];
    }

    #[DataProvider('provideTellsTheContractsThatEndByThemselvesCases')]
    public function testTellsTheContractsThatEndByThemselves(ClinicMembershipEngagement $engagement, bool $temporary): void
    {
        self::assertSame($temporary, $engagement->isTemporary());
    }
}
