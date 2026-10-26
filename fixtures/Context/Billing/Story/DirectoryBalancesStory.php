<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Billing\Story;

use App\Context\Billing\Application\Port\SellerIdentityProviderInterface;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\Service\InvoiceNumberGeneratorInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\OperationCategory;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\PayerId;
use App\Context\Billing\Domain\ValueObject\Payment;
use App\Context\Billing\Domain\ValueObject\PaymentId;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Context\Billing\Domain\ValueObject\PriceMode;
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
        private readonly SellerIdentityProviderInterface $sellers,
    ) {
    }

    public function build(): void
    {
        $clinic = ClinicId::fromString(ClinicDataStory::INDEPENDENT_CLINIC_ID);
        $eur    = CurrencyCode::fromString('EUR');

        foreach (self::DEBTS as [$clientId, $name, $gross, $paid, $daysAgo]) {
            $at = new \DateTimeImmutable(\sprintf('-%d days 11:00', $daysAgo));

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
                enteredUnitPrice: Money::fromMinorUnits($gross, $eur),
                priceMode: PriceMode::INCLUDING_TAX,
                taxCategoryCode: 'veterinary.act.consultation',
                taxRatePercent: '20',
                operationCategory: OperationCategory::SERVICES,
                source: InvoiceLineSource::MANUAL,
                sourceReference: null,
            ), $at);

            $invoice->issue($this->numbers->next($clinic, (int) $at->format('Y')), $this->sellers->forClinic($clinic->toString()), $at);

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
