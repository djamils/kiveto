<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity\CashClosingEntity;
use App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity\ChequeDepositEntity;
use App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity\ChequeDepositPaymentEntity;
use App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity\CreditNoteEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Rows the repositories write but read back through SQL: this covers the
 * mapping Doctrine builds the tables from.
 */
final class DeskEntitiesTest extends TestCase
{
    private const string ID     = '01960000-0000-7000-8000-00000000e201';
    private const string CLINIC = '01960000-0000-7000-8000-00000000e202';
    private const string USER   = '01960000-0000-7000-8000-00000000e203';

    public function testADepositRow(): void
    {
        $entity = new ChequeDepositEntity();
        $entity->setId(Uuid::fromString(self::ID));
        $entity->setClinicId(Uuid::fromString(self::CLINIC));
        $entity->setTotalMinorUnits(4500);
        $entity->setCurrency('EUR');
        $entity->setDepositedAt(new \DateTimeImmutable('2026-10-14 17:30'));
        $entity->setDepositedByUserId(Uuid::fromString(self::USER));

        self::assertSame(self::ID, $entity->getId()->toRfc4122());
        self::assertSame(self::CLINIC, $entity->getClinicId()->toRfc4122());
        self::assertSame(4500, $entity->getTotalMinorUnits());
        self::assertSame('EUR', $entity->getCurrency());
        self::assertSame('17:30', $entity->getDepositedAt()->format('H:i'));
        self::assertSame(self::USER, $entity->getDepositedByUserId()?->toRfc4122());
    }

    public function testADepositedChequeRow(): void
    {
        $entity = new ChequeDepositPaymentEntity();
        $entity->setPaymentId(Uuid::fromString(self::USER));
        $entity->setDepositId(Uuid::fromString(self::ID));

        self::assertSame(self::USER, $entity->getPaymentId()->toRfc4122());
        self::assertSame(self::ID, $entity->getDepositId()->toRfc4122());
    }

    public function testAClosingRow(): void
    {
        $entity = new CashClosingEntity();
        $entity->setClinicId(Uuid::fromString(self::CLINIC));

        self::assertSame(self::CLINIC, $entity->getClinicId()->toRfc4122());
        self::assertSame(0, $entity->getNumber());
        self::assertSame([], $entity->getExpected());
    }

    public function testACreditNoteRowStoresItsTotals(): void
    {
        $entity = new CreditNoteEntity();
        $entity->setTotalNetMinorUnits(1000);
        $entity->setTotalTaxMinorUnits(200);
        $entity->setTotalGrossMinorUnits(1200);

        self::assertSame(1000, $entity->getTotalNetMinorUnits());
        self::assertSame(200, $entity->getTotalTaxMinorUnits());
        self::assertSame(1200, $entity->getTotalGrossMinorUnits());
    }
}
