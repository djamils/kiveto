<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Billing\Story;

use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\Service\InvoiceNumberGeneratorInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\PayerId;
use App\Context\Billing\Domain\ValueObject\Payment;
use App\Context\Billing\Domain\ValueObject\PaymentId;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * Two Paris clients who left owing money, for the directory's « Solde »
 * column and its « Solde impayé » filter: one invoice issued and unpaid, one
 * paid in part. Past visits invoiced by hand, without a visit on the board.
 */
final class DirectoryBalancesStory extends Story
{
    /**
     * [client id (DirectoryCrowdStory), payer name, gross, paid, days ago].
     */
    private const array DEBTS = [
        ['01936e19-d1d1-7000-8000-000000000006', 'Paolo Costa', 8650, 0, 12],
        ['01936e19-d1d1-7000-8000-000000000008', 'Sophie Rey', 15000, 4900, 8],
    ];

    public function __construct(
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly InvoiceNumberGeneratorInterface $numbers,
    ) {
    }

    public function build(): void
    {
        $clinic = ClinicId::fromString(ClinicDataStory::INDEPENDENT_CLINIC_ID);
        $eur    = CurrencyCode::fromString('EUR');

        foreach (self::DEBTS as [$clientId, $name, $gross, $paid, $daysAgo]) {
            $at  = new \DateTimeImmutable(\sprintf('-%d days 11:00', $daysAgo));
            $net = (int) round($gross / 1.2);

            $invoice = Invoice::draft(
                id: InvoiceId::fromString(Uuid::v7()->toRfc4122()),
                clinicId: $clinic,
                admissionId: null,
                payer: Payer::create(PayerId::fromString($clientId), $name, ['Paris']),
                currency: $eur,
                now: $at,
            );

            $invoice->addLine(InvoiceLine::create(
                id: InvoiceLineId::fromString(Uuid::v7()->toRfc4122()),
                label: 'Consultation et soins',
                code: 'CONS_STD',
                quantity: '1',
                unitNetPrice: Money::fromMinorUnits($net, $eur),
                taxCategoryCode: 'veterinary.act.consultation',
                taxRatePercent: '20',
                netAmount: Money::fromMinorUnits($net, $eur),
                taxAmount: Money::fromMinorUnits($gross - $net, $eur),
                grossAmount: Money::fromMinorUnits($gross, $eur),
                source: InvoiceLineSource::MANUAL,
                sourceReference: null,
            ), $at);

            $invoice->issue($this->numbers->next($clinic, (int) $at->format('Y')), $at);

            if ($paid > 0) {
                $invoice->recordPayment(Payment::create(
                    id: PaymentId::fromString(Uuid::v7()->toRfc4122()),
                    method: PaymentMethod::CASH,
                    amount: Money::fromMinorUnits($paid, $eur),
                    receivedAt: $at,
                    reference: null,
                ), $at);
            }

            $this->invoices->save($invoice);
        }
    }
}
