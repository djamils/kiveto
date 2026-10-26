<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Domain\Dispatch;

use App\Context\Billing\Domain\Dispatch\DispatchKind;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatch;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatchId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use PHPUnit\Framework\TestCase;

final class InvoiceDispatchTest extends TestCase
{
    private const string ID      = '01960000-0000-7000-8000-00000000d001';
    private const string CLINIC  = '01960000-0000-7000-8000-00000000d002';
    private const string INVOICE = '01960000-0000-7000-8000-00000000d003';
    private const string AUTHOR  = '01960000-0000-7000-8000-00000000d004';

    public function testALoggedDispatchWaitsForItsMessages(): void
    {
        $at       = new \DateTimeImmutable('2026-10-26 09:00:00');
        $dispatch = InvoiceDispatch::log(
            InvoiceDispatchId::fromString(self::ID),
            ClinicId::fromString(self::CLINIC),
            InvoiceId::fromString(self::INVOICE),
            DispatchKind::REMINDER,
            [InvoiceDispatch::EMAIL, InvoiceDispatch::SMS, InvoiceDispatch::EMAIL],
            self::AUTHOR,
            $at,
        );

        self::assertSame(self::ID, $dispatch->id()->toString());
        self::assertSame(self::CLINIC, $dispatch->clinicId()->toString());
        self::assertSame(self::INVOICE, $dispatch->invoiceId()->toString());
        self::assertSame(DispatchKind::REMINDER, $dispatch->kind());
        self::assertSame(['EMAIL', 'SMS'], $dispatch->channels());
        self::assertTrue($dispatch->sendsOn('SMS'));
        self::assertSame(self::AUTHOR, $dispatch->authorUserId());
        self::assertSame($at, $dispatch->createdAt());
        self::assertFalse($dispatch->wasSent());
        self::assertSame([], $dispatch->notificationIds());

        $dispatch->recordNotifications(['m1', 'm2']);

        self::assertTrue($dispatch->wasSent());
        self::assertSame(['m1', 'm2'], $dispatch->notificationIds());
    }

    public function testTheMessagesAreRecordedOnce(): void
    {
        $dispatch = InvoiceDispatch::reconstitute(
            InvoiceDispatchId::fromString(self::ID),
            ClinicId::fromString(self::CLINIC),
            InvoiceId::fromString(self::INVOICE),
            DispatchKind::SEND,
            ['EMAIL'],
            ['m1'],
            null,
            new \DateTimeImmutable(),
        );

        $this->expectException(\LogicException::class);
        $dispatch->recordNotifications(['m2']);
    }

    public function testAnInvoiceGoesByEmailOrSmsOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        InvoiceDispatch::log(InvoiceDispatchId::fromString(self::ID), ClinicId::fromString(self::CLINIC), InvoiceId::fromString(self::INVOICE), DispatchKind::SEND, ['FAX'], null, new \DateTimeImmutable());
    }

    public function testADispatchHasAChannel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        InvoiceDispatch::log(InvoiceDispatchId::fromString(self::ID), ClinicId::fromString(self::CLINIC), InvoiceId::fromString(self::INVOICE), DispatchKind::SEND, [], null, new \DateTimeImmutable());
    }
}
