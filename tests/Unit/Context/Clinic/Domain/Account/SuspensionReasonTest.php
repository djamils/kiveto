<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\Account;

use App\Context\Clinic\Domain\Account\SuspensionReason;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SuspensionReasonTest extends TestCase
{
    #[DataProvider('provideEachReasonIsShownInFrenchCases')]
    public function testEachReasonIsShownInFrench(SuspensionReason $reason, string $label): void
    {
        self::assertSame($label, $reason->label());
    }

    /**
     * @return iterable<string, array{SuspensionReason, string}>
     */
    public static function provideEachReasonIsShownInFrenchCases(): iterable
    {
        yield 'clinic request' => [SuspensionReason::CLINIC_REQUEST, 'Demande de la clinique'];

        yield 'unpaid' => [SuspensionReason::UNPAID, 'Impayé'];

        yield 'end of pilot' => [SuspensionReason::END_OF_PILOT, 'Fin du pilote'];

        yield 'end of demo' => [SuspensionReason::END_OF_DEMO, 'Fin de démo'];

        yield 'security' => [SuspensionReason::SECURITY, 'Incident de sécurité'];

        yield 'other' => [SuspensionReason::OTHER, 'Autre'];
    }
}
