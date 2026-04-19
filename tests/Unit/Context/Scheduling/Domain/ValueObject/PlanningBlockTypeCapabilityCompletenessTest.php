<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Domain\ValueObject;

use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlanningBlockTypeCapabilityCompletenessTest extends TestCase
{
    #[DataProvider('provideCapabilityMatrixCases')]
    public function testCapabilityMatrix(PlanningBlockType $type, bool $acceptsAppointments, bool $hasCapacityLimit): void
    {
        self::assertSame($acceptsAppointments, $type->acceptsAppointments());
        self::assertSame($hasCapacityLimit, $type->hasCapacityLimit());
    }

    /**
     * @return iterable<string, array{PlanningBlockType, bool, bool}>
     */
    public static function provideCapabilityMatrixCases(): iterable
    {
        yield 'CONSULTATION' => [PlanningBlockType::CONSULTATION, true,  true];
        yield 'CHIRURGIE' => [PlanningBlockType::CHIRURGIE,    true,  true];
        yield 'BILAN' => [PlanningBlockType::BILAN,        true,  true];
        yield 'URGENCE' => [PlanningBlockType::URGENCE,      true,  true];
        yield 'GARDE' => [PlanningBlockType::GARDE,        true,  true];
        yield 'CONGE' => [PlanningBlockType::CONGE,        false, false];
        yield 'FORMATION' => [PlanningBlockType::FORMATION,    false, false];
        yield 'ADMIN' => [PlanningBlockType::ADMIN,        false, false];
    }

    public function testAllCasesAreCovered(): void
    {
        $covered = array_column(iterator_to_array(self::provideCapabilityMatrixCases()), 0);
        self::assertCount(\count(PlanningBlockType::cases()), $covered);
    }
}
