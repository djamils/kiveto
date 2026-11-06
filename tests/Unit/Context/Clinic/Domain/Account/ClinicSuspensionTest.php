<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\Account;

use App\Context\Clinic\Domain\Account\ClinicSuspension;
use App\Context\Clinic\Domain\Account\SuspensionReason;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClinicSuspensionTest extends TestCase
{
    private const string ACTOR = '01960000-0000-7000-8000-00000000c0b1';

    public function testASuspensionKeepsWhyAndWhoDecidedIt(): void
    {
        $at         = new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'));
        $suspension = ClinicSuspension::of(SuspensionReason::END_OF_DEMO, '  Démo terminée.  ', $at, self::ACTOR, '  Djamil Soidiki ');

        self::assertSame([SuspensionReason::END_OF_DEMO, 'Démo terminée.', $at, self::ACTOR, 'Djamil Soidiki'], [
            $suspension->reason(), $suspension->comment(), $suspension->suspendedAt(), $suspension->actorId(), $suspension->actorName(),
        ]);
    }

    public function testALongCommentAndNameAreCut(): void
    {
        $suspension = ClinicSuspension::of(SuspensionReason::OTHER, str_repeat('é', 600), new \DateTimeImmutable(), null, str_repeat('à', 200));

        self::assertSame([str_repeat('é', 500), str_repeat('à', 160), null], [$suspension->comment(), $suspension->actorName(), $suspension->actorId()]);
    }

    #[DataProvider('provideASuspensionNamesWhoDecidedItCases')]
    public function testASuspensionNamesWhoDecidedIt(string $actorName): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A suspension names who decided it.');

        ClinicSuspension::of(SuspensionReason::SECURITY, 'Fuite', new \DateTimeImmutable(), self::ACTOR, $actorName);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideASuspensionNamesWhoDecidedItCases(): iterable
    {
        yield 'empty' => [''];

        yield 'blank' => [" \t "];
    }
}
