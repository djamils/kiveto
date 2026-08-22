<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Domain\ValueObject;

use App\Context\Billing\Domain\ValueObject\InvoiceStatus;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use PHPUnit\Framework\TestCase;

final class EnumTest extends TestCase
{
    public function testEveryInvoiceStatusIsLabelled(): void
    {
        foreach (InvoiceStatus::cases() as $status) {
            self::assertNotSame('', $status->label());
        }

        self::assertSame('Brouillon', InvoiceStatus::DRAFT->label());
        self::assertSame('Impayée', InvoiceStatus::ISSUED->label());
        self::assertSame('Partielle', InvoiceStatus::PARTIALLY_PAID->label());
        self::assertSame('Payée', InvoiceStatus::PAID->label());
        self::assertSame('Annulée', InvoiceStatus::CANCELLED->label());
    }

    public function testOnlyIssuedAndPartiallyPaidStillExpectMoney(): void
    {
        self::assertTrue(InvoiceStatus::ISSUED->isOutstanding());
        self::assertTrue(InvoiceStatus::PARTIALLY_PAID->isOutstanding());
        self::assertFalse(InvoiceStatus::DRAFT->isOutstanding());
        self::assertFalse(InvoiceStatus::PAID->isOutstanding());
        self::assertFalse(InvoiceStatus::CANCELLED->isOutstanding());
    }

    public function testOnlyADraftIsEditable(): void
    {
        self::assertTrue(InvoiceStatus::DRAFT->isEditable());

        foreach ([InvoiceStatus::ISSUED, InvoiceStatus::PARTIALLY_PAID, InvoiceStatus::PAID, InvoiceStatus::CANCELLED] as $status) {
            self::assertFalse($status->isEditable());
        }
    }

    public function testEveryPaymentMethodIsLabelled(): void
    {
        foreach (PaymentMethod::cases() as $method) {
            self::assertNotSame('', $method->label());
        }

        self::assertSame('Espèces', PaymentMethod::CASH->label());
        self::assertSame('Carte bancaire', PaymentMethod::CARD->label());
        self::assertSame('Virement', PaymentMethod::TRANSFER->label());
        self::assertSame('Chèque', PaymentMethod::CHECK->label());
        self::assertSame('Assurance', PaymentMethod::INSURANCE->label());
        self::assertSame('Autre', PaymentMethod::OTHER->label());
    }

    public function testCashAndCardSettleAtTheDesk(): void
    {
        self::assertTrue(PaymentMethod::CASH->isImmediate());
        self::assertTrue(PaymentMethod::CARD->isImmediate());
        self::assertFalse(PaymentMethod::TRANSFER->isImmediate());
        self::assertFalse(PaymentMethod::CHECK->isImmediate());
    }
}
