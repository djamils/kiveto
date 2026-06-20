<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Procurement\Persistence;

use App\Context\Procurement\Domain\PurchaseOrder\Service\PurchaseOrderNumberGeneratorInterface;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class PurchaseOrderNumberGeneratorTest extends KernelTestCase
{
    use Factories;

    public function testGeneratesSequentialNumbers(): void
    {
        $generator = self::getContainer()->get(PurchaseOrderNumberGeneratorInterface::class);
        \assert($generator instanceof PurchaseOrderNumberGeneratorInterface);

        // Use a fresh clinicId to guarantee an isolated sequence
        $clinicId = ClinicId::fromString(Uuid::v7()->toString());
        $year     = 2026;

        $num1 = $generator->next($clinicId, $year);
        $num2 = $generator->next($clinicId, $year);

        self::assertSame('PO-2026-000001', $num1->toString());
        self::assertSame('PO-2026-000002', $num2->toString());
    }

    public function testSequencesAreIsolatedBetweenClinics(): void
    {
        $generator = self::getContainer()->get(PurchaseOrderNumberGeneratorInterface::class);
        \assert($generator instanceof PurchaseOrderNumberGeneratorInterface);

        $clinicA = ClinicId::fromString(Uuid::v7()->toString());
        $clinicB = ClinicId::fromString(Uuid::v7()->toString());
        $year    = 2026;

        $numA1 = $generator->next($clinicA, $year);
        $numB1 = $generator->next($clinicB, $year);
        $numA2 = $generator->next($clinicA, $year);

        // Each clinic starts from 1 independently
        self::assertSame('PO-2026-000001', $numA1->toString());
        self::assertSame('PO-2026-000001', $numB1->toString());
        self::assertSame('PO-2026-000002', $numA2->toString());
    }

    public function testSequencesAreIsolatedBetweenYears(): void
    {
        $generator = self::getContainer()->get(PurchaseOrderNumberGeneratorInterface::class);
        \assert($generator instanceof PurchaseOrderNumberGeneratorInterface);

        $clinicId = ClinicId::fromString(Uuid::v7()->toString());

        $num2025 = $generator->next($clinicId, 2025);
        $num2026 = $generator->next($clinicId, 2026);

        self::assertSame('PO-2025-000001', $num2025->toString());
        self::assertSame('PO-2026-000001', $num2026->toString());
    }
}
