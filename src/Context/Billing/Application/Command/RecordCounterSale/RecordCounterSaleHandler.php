<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\RecordCounterSale;

use App\Context\Billing\Application\Port\CounterCatalogInterface;
use App\Context\Billing\Application\Port\PayerDirectoryInterface;
use App\Context\Billing\Application\Port\TaxResolverInterface;
use App\Context\Billing\Domain\Exception\InvalidInvoiceLineException;
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
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Bills what the desk sold — priced from the catalogue, taxed by the clinic's
 * VAT rules — issues the invoice and takes its payment in full. The sale has
 * no visit behind it; the payer is the client chosen, or « Client de passage ».
 */
#[AsMessageHandler]
final readonly class RecordCounterSaleHandler
{
    private const int MAX_QUANTITY = 999;

    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private InvoiceNumberGeneratorInterface $numbers,
        private CounterCatalogInterface $catalog,
        private TaxResolverInterface $taxResolver,
        private PayerDirectoryInterface $payerDirectory,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RecordCounterSale $command): string
    {
        if ([] === $command->lines) {
            throw new InvalidInvoiceLineException('A counter sale needs at least one article.');
        }

        $method   = PaymentMethod::from($command->method);
        $clinicId = ClinicId::fromString($command->clinicId);
        $now      = $this->clock->now();
        $lines    = [];
        $currency = null;

        foreach ($command->lines as $item) {
            $article = $this->catalog->article($command->clinicId, $item['articleId']);

            if (null === $article) {
                throw new InvalidInvoiceLineException(\sprintf('Article %s cannot be sold at the desk.', $item['articleId']));
            }

            if ($item['quantity'] < 1 || $item['quantity'] > self::MAX_QUANTITY) {
                throw new InvalidInvoiceLineException(\sprintf('Invalid quantity %d.', $item['quantity']));
            }

            $currency ??= CurrencyCode::fromString($article->currency);
            $tax = $this->taxResolver->resolve(
                $article->unitNetMinorUnits * $item['quantity'],
                $currency->toString(),
                $article->taxCategoryCode,
                $command->clinicId,
            );

            $lines[] = InvoiceLine::create(
                id: InvoiceLineId::fromString($this->uuidGenerator->generate()),
                label: $article->label,
                code: $article->code,
                quantity: (string) $item['quantity'],
                unitNetPrice: Money::fromMinorUnits($article->unitNetMinorUnits, $currency),
                taxCategoryCode: $article->taxCategoryCode,
                taxRatePercent: $tax->ratePercent,
                netAmount: Money::fromMinorUnits($tax->netMinorUnits, $currency),
                taxAmount: Money::fromMinorUnits($tax->taxMinorUnits, $currency),
                grossAmount: Money::fromMinorUnits($tax->grossMinorUnits, $currency),
                source: InvoiceLineSource::COUNTER,
                sourceReference: $article->articleId,
            );
        }

        $invoice = Invoice::draft(
            id: InvoiceId::fromString($this->uuidGenerator->generate()),
            clinicId: $clinicId,
            admissionId: null,
            payer: $this->payer($command),
            currency: $currency,
            now: $now,
        );

        foreach ($lines as $line) {
            $invoice->addLine($line, $now);
        }

        $invoice->issue($this->numbers->next($clinicId, (int) $now->format('Y')), $now);

        if ($invoice->balanceDue()->isPositive()) {
            $invoice->recordPayment(Payment::create(
                id: PaymentId::fromString($this->uuidGenerator->generate()),
                method: $method,
                amount: $invoice->balanceDue(),
                receivedAt: $now,
                reference: $command->reference,
                recordedByUserId: $command->recordedByUserId,
            ), $now);
        }

        $this->invoices->save($invoice);

        return $invoice->id()->toString();
    }

    private function payer(RecordCounterSale $command): Payer
    {
        $snapshot = null !== $command->payerClientId
            ? $this->payerDirectory->forClient($command->payerClientId, $command->clinicId)
            : null;

        if (null === $snapshot) {
            return Payer::anonymous();
        }

        return Payer::create(
            null !== $snapshot->clientId ? PayerId::fromString($snapshot->clientId) : null,
            $snapshot->displayName,
            $snapshot->addressLines,
        );
    }
}
