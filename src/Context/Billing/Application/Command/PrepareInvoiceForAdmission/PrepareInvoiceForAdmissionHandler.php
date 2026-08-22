<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\PrepareInvoiceForAdmission;

use App\Context\Billing\Application\Port\BillableLineDto;
use App\Context\Billing\Application\Port\ConsultationBillingProviderInterface;
use App\Context\Billing\Application\Port\PayerDirectoryInterface;
use App\Context\Billing\Application\Port\TaxResolverInterface;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\AdmissionId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\PayerId;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Turns a visit into a draft invoice.
 *
 * Idempotent on purpose: the desk can walk back to the checkout screen as many
 * times as it likes, and a visit that already has an invoice — draft, issued or
 * paid — is never charged twice. Only a cancelled one is replaced.
 */
#[AsMessageHandler]
final readonly class PrepareInvoiceForAdmissionHandler
{
    private const string DEFAULT_CURRENCY = 'EUR';

    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private ConsultationBillingProviderInterface $consultationBilling,
        private TaxResolverInterface $taxResolver,
        private PayerDirectoryInterface $payerDirectory,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(PrepareInvoiceForAdmission $command): string
    {
        $clinicId    = ClinicId::fromString($command->clinicId);
        $admissionId = AdmissionId::fromString($command->admissionId);

        $existing = $this->invoices->findForAdmission($clinicId, $admissionId);

        if (null !== $existing && !$existing->status()->isEditable()) {
            return $existing->id()->toString();
        }

        $lines = $this->consultationBilling->billableLinesForAdmission(
            $command->admissionId,
            $command->clinicId,
        );
        $currency = CurrencyCode::fromString($this->currencyOf($lines));
        $now      = $this->clock->now();

        // A draft is rebuilt rather than patched: the practitioner may have
        // added or removed acts since the last time the desk looked at it.
        $invoice = Invoice::draft(
            id: null !== $existing ? $existing->id() : InvoiceId::fromString($this->uuidGenerator->generate()),
            clinicId: $clinicId,
            admissionId: $admissionId,
            payer: $this->payerFor($command),
            currency: $currency,
            now: $now,
        );

        foreach ($lines as $line) {
            $invoice->addLine($this->toInvoiceLine($line, $currency, $command->clinicId), $now);
        }

        $this->invoices->save($invoice);

        return $invoice->id()->toString();
    }

    /**
     * @param list<BillableLineDto> $lines
     */
    private function currencyOf(array $lines): string
    {
        foreach ($lines as $line) {
            if ('' !== $line->currency) {
                return $line->currency;
            }
        }

        return self::DEFAULT_CURRENCY;
    }

    private function payerFor(PrepareInvoiceForAdmission $command): Payer
    {
        $snapshot = $this->payerDirectory->forAdmission($command->admissionId, $command->clinicId);

        if (null === $snapshot) {
            return Payer::anonymous();
        }

        return Payer::create(
            null !== $snapshot->clientId ? PayerId::fromString($snapshot->clientId) : null,
            $snapshot->displayName,
            $snapshot->addressLines,
        );
    }

    private function toInvoiceLine(
        BillableLineDto $line,
        CurrencyCode $currency,
        string $clinicId,
    ): InvoiceLine {
        $net = (int) round((float) $line->quantity * $line->unitPriceMinorUnits);

        $tax = $this->taxResolver->resolve(
            $net,
            $currency->toString(),
            $line->taxCategoryCode,
            $clinicId,
        );

        return InvoiceLine::create(
            id: InvoiceLineId::fromString($this->uuidGenerator->generate()),
            label: $line->label,
            code: $line->code,
            quantity: $line->quantity,
            unitNetPrice: Money::fromMinorUnits($line->unitPriceMinorUnits, $currency),
            taxCategoryCode: $line->taxCategoryCode,
            taxRatePercent: $tax->ratePercent,
            netAmount: Money::fromMinorUnits($tax->netMinorUnits, $currency),
            taxAmount: Money::fromMinorUnits($tax->taxMinorUnits, $currency),
            grossAmount: Money::fromMinorUnits($tax->grossMinorUnits, $currency),
            source: InvoiceLineSource::CONSULTATION,
            sourceReference: $line->sourceLineId,
        );
    }
}
