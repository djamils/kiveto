<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Domain\ValueObject;

use App\Context\Animal\Domain\ValueObject\MedicalAlertKind;
use PHPUnit\Framework\TestCase;

final class MedicalAlertKindTest extends TestCase
{
    public function testTheFourColumnsOfTheMedicalSynthesis(): void
    {
        self::assertSame(
            ['ALLERGY' => 'Allergie', 'CHRONIC_CONDITION' => 'Suivi', 'LONG_TERM_TREATMENT' => 'Traitement au long cours', 'BEHAVIOR' => 'Comportement'],
            array_combine(
                array_map(static fn (MedicalAlertKind $k): string => $k->value, MedicalAlertKind::cases()),
                array_map(static fn (MedicalAlertKind $k): string => $k->label(), MedicalAlertKind::cases()),
            ),
        );
    }
}
