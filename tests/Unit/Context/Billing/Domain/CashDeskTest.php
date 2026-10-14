<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Domain;

use App\Context\Billing\Domain\CashClosing;
use App\Context\Billing\Domain\ChequeDeposit;
use App\Context\Billing\Domain\Exception\InvalidCashClosingException;
use App\Context\Billing\Domain\Exception\InvalidChequeDepositException;
use App\Context\Billing\Domain\ValueObject\CashClosingId;
use App\Context\Billing\Domain\ValueObject\ChequeDepositId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\PaymentId;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

/**
 * The till: cheques handed to the bank, and the end-of-day count.
 */
final class CashDeskTest extends TestCase
{
    private const string ID        = '01960000-0000-7000-8000-00000000e101';
    private const string CLINIC_ID = '01960000-0000-7000-8000-00000000e102';
    private const string CHEQUE_A  = '01960000-0000-7000-8000-00000000e103';
    private const string CHEQUE_B  = '01960000-0000-7000-8000-00000000e104';
    private const string USER      = '01960000-0000-7000-8000-00000000e105';

    public function testADepositHoldsItsCheques(): void
    {
        $deposit = ChequeDeposit::record(
            ChequeDepositId::fromString(self::ID),
            ClinicId::fromString(self::CLINIC_ID),
            [PaymentId::fromString(self::CHEQUE_A), PaymentId::fromString(self::CHEQUE_B)],
            $this->eur(12000),
            new \DateTimeImmutable('2026-10-14 17:30'),
            self::USER,
        );

        self::assertSame(self::ID, $deposit->id()->toString());
        self::assertSame(self::CLINIC_ID, $deposit->clinicId()->toString());
        self::assertCount(2, $deposit->paymentIds());
        self::assertSame(12000, $deposit->total()->minorUnits());
        self::assertSame('17:30', $deposit->depositedAt()->format('H:i'));
        self::assertSame(self::USER, $deposit->depositedByUserId());

        $back = ChequeDeposit::reconstitute($deposit->id(), $deposit->clinicId(), $deposit->paymentIds(), $deposit->total(), $deposit->depositedAt(), null);
        self::assertNull($back->depositedByUserId());
    }

    public function testADepositNeedsACheque(): void
    {
        $this->expectException(InvalidChequeDepositException::class);

        ChequeDeposit::record(ChequeDepositId::fromString(self::ID), ClinicId::fromString(self::CLINIC_ID), [], $this->eur(100), new \DateTimeImmutable());
    }

    public function testAChequeGoesToTheBankOnce(): void
    {
        $this->expectException(InvalidChequeDepositException::class);

        ChequeDeposit::record(
            ChequeDepositId::fromString(self::ID),
            ClinicId::fromString(self::CLINIC_ID),
            [PaymentId::fromString(self::CHEQUE_A), PaymentId::fromString(self::CHEQUE_A)],
            $this->eur(100),
            new \DateTimeImmutable(),
        );
    }

    public function testADepositCarriesMoney(): void
    {
        $this->expectException(InvalidChequeDepositException::class);

        ChequeDeposit::record(ChequeDepositId::fromString(self::ID), ClinicId::fromString(self::CLINIC_ID), [PaymentId::fromString(self::CHEQUE_A)], $this->eur(0), new \DateTimeImmutable());
    }

    public function testAClosingComparesTheCountWithTheTakings(): void
    {
        $closing = $this->close(countedCash: 9800, countedCheques: 4500, comment: '  Erreur de rendu  ');

        self::assertSame(self::ID, $closing->id()->toString());
        self::assertSame(self::CLINIC_ID, $closing->clinicId()->toString());
        self::assertSame(4, $closing->number());
        self::assertSame('2026-10-13', $closing->businessDate());
        self::assertSame(10000, $closing->expectedFor(PaymentMethod::CASH));
        self::assertSame(0, $closing->expectedFor(PaymentMethod::TRANSFER));
        self::assertSame(-200, $closing->gapMinorUnits());
        self::assertSame(29500, $closing->totalExpectedMinorUnits());
        self::assertSame(['CARD' => 15000, 'CASH' => 10000, 'CHECK' => 4500], $closing->expectedMinorUnits());
        self::assertSame(9800, $closing->countedCashMinorUnits());
        self::assertSame(4500, $closing->countedChequesMinorUnits());
        self::assertSame('Erreur de rendu', $closing->comment());
        self::assertSame('EUR', $closing->currency()->toString());
        self::assertSame('19:00', $closing->closedAt()->format('H:i'));
        self::assertSame(self::USER, $closing->closedByUserId());
    }

    public function testAnUncountedDrawerIsTakenAsMatching(): void
    {
        $closing = $this->close(countedCash: null, countedCheques: null, comment: '   ');

        self::assertSame(0, $closing->gapMinorUnits());
        self::assertNull($closing->comment());
    }

    public function testADifferenceHasToBeExplained(): void
    {
        $this->expectException(InvalidCashClosingException::class);

        $this->close(countedCash: 9000, countedCheques: null, comment: null);
    }

    public function testNumbersStartAtOne(): void
    {
        $this->expectException(InvalidCashClosingException::class);

        $this->close(number: 0);
    }

    public function testTheBusinessDateIsADay(): void
    {
        $this->expectException(InvalidCashClosingException::class);

        $this->close(date: '13/10/2026');
    }

    public function testTheTakingsAreByPaymentMethod(): void
    {
        $this->expectException(InvalidCashClosingException::class);

        $this->close(expected: ['BITCOIN' => 100]);
    }

    public function testACountCannotBeNegative(): void
    {
        $this->expectException(InvalidCashClosingException::class);

        $this->close(countedCash: -1, comment: 'x');
    }

    public function testItComesBackAsStored(): void
    {
        $closing = CashClosing::reconstitute(
            CashClosingId::fromString(self::ID),
            ClinicId::fromString(self::CLINIC_ID),
            2,
            '2026-10-12',
            ['CASH' => 100],
            50,
            null,
            'Écart',
            CurrencyCode::fromString('EUR'),
            new \DateTimeImmutable('2026-10-12 19:00'),
            null,
        );

        self::assertSame(-50, $closing->gapMinorUnits());
        self::assertNull($closing->closedByUserId());
    }

    /**
     * @param array<string, int>|null $expected
     */
    private function close(
        ?int $countedCash = 10000,
        ?int $countedCheques = 4500,
        ?string $comment = null,
        int $number = 4,
        string $date = '2026-10-13',
        ?array $expected = null,
    ): CashClosing {
        return CashClosing::close(
            id: CashClosingId::fromString(self::ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            number: $number,
            businessDate: $date,
            expectedMinorUnits: $expected ?? ['CARD' => 15000, 'CASH' => 10000, 'CHECK' => 4500],
            countedCashMinorUnits: $countedCash,
            countedChequesMinorUnits: $countedCheques,
            comment: $comment,
            currency: CurrencyCode::fromString('EUR'),
            now: new \DateTimeImmutable('2026-10-13 19:00'),
            closedByUserId: self::USER,
        );
    }

    private function eur(int $minorUnits): Money
    {
        return Money::fromMinorUnits($minorUnits, CurrencyCode::fromString('EUR'));
    }
}
