<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Domain\ValueObject;

use App\Context\Billing\Domain\Exception\InvalidPaymentException;
use App\Context\Billing\Domain\ValueObject\Payment;
use App\Context\Billing\Domain\ValueObject\PaymentId;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class PaymentTest extends TestCase
{
    private const string PAYMENT_ID = '01960000-0000-7000-8000-0000000000f2';
    private const string USER_ID    = '01960000-0000-7000-8000-0000000000f3';

    public function testItRecordsWhatWasReceived(): void
    {
        $payment = Payment::create(
            id: PaymentId::fromString(self::PAYMENT_ID),
            method: PaymentMethod::CHECK,
            amount: Money::fromMinorUnits(4500, CurrencyCode::fromString('EUR')),
            receivedAt: new \DateTimeImmutable('2026-08-22 11:00:00'),
            reference: '  CHQ-4412  ',
            recordedByUserId: self::USER_ID,
        );

        self::assertSame(self::PAYMENT_ID, $payment->id()->toString());
        self::assertSame(PaymentMethod::CHECK, $payment->method());
        self::assertSame(4500, $payment->amount()->minorUnits());
        self::assertSame('CHQ-4412', $payment->reference());
        self::assertSame(self::USER_ID, $payment->recordedByUserId());
        self::assertSame('2026-08-22 11:00:00', $payment->receivedAt()->format('Y-m-d H:i:s'));
    }

    public function testABlankReferenceBecomesNull(): void
    {
        $payment = $this->payment(1000, '   ');

        self::assertNull($payment->reference());
        self::assertNull($payment->recordedByUserId());
    }

    public function testAZeroPaymentIsRejected(): void
    {
        $this->expectException(InvalidPaymentException::class);
        $this->expectExceptionMessage('A payment must be a positive amount.');

        $this->payment(0);
    }

    public function testANegativePaymentIsRejected(): void
    {
        $this->expectException(InvalidPaymentException::class);

        $this->payment(-100);
    }

    private function payment(int $amount, ?string $reference = null): Payment
    {
        return Payment::create(
            id: PaymentId::fromString(self::PAYMENT_ID),
            method: PaymentMethod::CASH,
            amount: Money::fromMinorUnits($amount, CurrencyCode::fromString('EUR')),
            receivedAt: new \DateTimeImmutable('2026-08-22 11:00:00'),
            reference: $reference,
        );
    }
}
