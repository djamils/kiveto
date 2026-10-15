<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Application\Query;

use App\Context\Billing\Application\Query\PeekNextDocumentNumbers\PeekNextDocumentNumbers;
use App\Context\Billing\Application\Query\PeekNextDocumentNumbers\PeekNextDocumentNumbersHandler;
use App\Context\Billing\Domain\Service\InvoiceNumberGeneratorInterface;
use App\Context\Billing\Domain\ValueObject\InvoiceNumber;
use PHPUnit\Framework\TestCase;

final class PeekNextDocumentNumbersHandlerTest extends TestCase
{
    public function testItReadsBothSeriesForTheYear(): void
    {
        $numbers = $this->createMock(InvoiceNumberGeneratorInterface::class);
        $numbers->expects(self::once())->method('peekNext')->with(self::anything(), 2026)->willReturn(InvoiceNumber::of(2026, 815, 'FA'));
        $numbers->expects(self::once())->method('peekNextCreditNote')->with(self::anything(), 2026)->willReturn(InvoiceNumber::of(2026, 12, 'AV'));
        $numbers->expects(self::never())->method('next');

        $next = (new PeekNextDocumentNumbersHandler($numbers))(new PeekNextDocumentNumbers('018f1b1e-1234-7890-abcd-0123456789ab', 2026));

        self::assertSame('FA-2026-000815', $next->invoice);
        self::assertSame('AV-2026-000012', $next->creditNote);
    }
}
