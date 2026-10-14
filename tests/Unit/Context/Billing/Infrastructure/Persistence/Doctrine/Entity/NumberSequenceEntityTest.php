<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity\NumberSequenceEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The counter row is written through raw SQL by the number generator — this
 * covers the mapping itself, which is what Doctrine builds the table from.
 */
final class NumberSequenceEntityTest extends TestCase
{
    private const string ID        = '01960000-0000-7000-8000-00000000e001';
    private const string CLINIC_ID = '01960000-0000-7000-8000-00000000e002';

    public function testItCarriesTheCounterOfOneClinicAndYear(): void
    {
        $entity = new NumberSequenceEntity();

        $entity->setId(Uuid::fromString(self::ID));
        $entity->setClinicId(Uuid::fromString(self::CLINIC_ID));
        $entity->setYear(2026);
        $entity->setLastValue(42);
        $entity->setSeries('CREDIT_NOTE');

        self::assertSame(self::ID, $entity->getId()->toRfc4122());
        self::assertSame(self::CLINIC_ID, $entity->getClinicId()->toRfc4122());
        self::assertSame(2026, $entity->getYear());
        self::assertSame(42, $entity->getLastValue());
        self::assertSame('CREDIT_NOTE', $entity->getSeries());
    }

    public function testACounterStartsAtZeroOnTheInvoiceSeries(): void
    {
        self::assertSame(0, new NumberSequenceEntity()->getLastValue());
        self::assertSame('INVOICE', new NumberSequenceEntity()->getSeries());
    }
}
