<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Billing\Story;

use App\Context\Billing\Application\Command\IssueCreditNote\IssueCreditNote;
use App\Context\Billing\Application\Port\PayerDirectoryInterface;
use App\Context\Billing\Application\Port\SellerIdentityProviderInterface;
use App\Context\Billing\Application\Service\InvoiceLineFactory;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\Policy\ElectronicInvoiceReadiness;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\Service\InvoiceNumberGeneratorInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\Discount;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\Payment;
use App\Context\Billing\Domain\ValueObject\PaymentId;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Context\Billing\Domain\ValueObject\PriceMode;
use App\Fixtures\Context\Client\Story\ClientDataStory;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * Professional (B2B) invoices of the Paris clinic, one per professional whose
 * SIREN plays a scenario of the e-invoicing simulator, plus the cases the
 * fiscal model must show: a discount, goods and services mixed (framework M),
 * goods left to pay with a due date, credit notes on professional invoices
 * (internal once their invoice is refused or rejected, waiting while it is
 * not transmitted) and a draft whose buyer lacks an address (the readiness
 * banner at the counter). Prices are entered VAT included, as the clinic types them.
 */
final class ProfessionalInvoicesStory extends Story
{
    /** The nominal invoice the e-invoicing demo stops at « Reçue ». */
    public const string RECEIVED_INVOICE_ID = '01960000-0000-7000-8000-0000000e1d01';

    /** label, Taxation category, unit price incl. VAT (cents), quantity */
    private const array CONSULTATION = ['Consultation', 'veterinary.act.consultation', 6000, '1'];
    private const array VACCINE      = ['Vaccin CHPPiL', 'veterinary.act.vaccination', 7200, '1'];
    private const array FOOD         = ['Croquettes 10 kg', 'veterinary.food.therapeutic', 6499, '2'];
    private const array WORMER       = ['Vermifuge comprimés', 'veterinary.drug.companion', 1290, '3'];

    public function __construct(
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly InvoiceNumberGeneratorInterface $numbers,
        private readonly SellerIdentityProviderInterface $sellers,
        private readonly PayerDirectoryInterface $payers,
        private readonly InvoiceLineFactory $lines,
        private readonly CommandBusInterface $commandBus,
        private readonly DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function build(): void
    {
        // Nominal scenario — acts paid at the desk, with a loyalty discount (S2).
        $this->invoice(ClientDataStory::CLIENT_CALANQUES_ID, [self::CONSULTATION, self::VACCINE], 20, paid: true, discount: Discount::percentage('10', 'Client fidèle'));
        // Rejected — goods left to pay (B1, due date); credited afterwards.
        $rejected = $this->invoice(ClientDataStory::CLIENT_MISTRAL_ID, [self::FOOD], 18, paid: false);
        // Refused — acts and goods paid (M2); credited afterwards.
        $refused = $this->invoice(ClientDataStory::CLIENT_CRAU_ID, [self::CONSULTATION, self::WORMER], 15, paid: true);
        // Disputed — acts left to pay (S1).
        $this->invoice(ClientDataStory::CLIENT_ALPILLES_ID, [self::VACCINE], 12, paid: false);
        // Inadmissible — goods paid (B2).
        $this->invoice(ClientDataStory::CLIENT_LUBERON_ID, [self::WORMER, self::FOOD], 9, paid: true);
        // Buyer without a platform — mixed, left to pay (M1).
        $this->invoice(ClientDataStory::CLIENT_SAINTE_BAUME_ID, [self::CONSULTATION, self::FOOD], 6, paid: false);

        // The credit notes of a refused and of a rejected invoice stay internal.
        $this->creditNote($refused, 1, 'Produit retourné');
        $this->creditNote($rejected, 0, 'Facture rejetée par le client');

        // Nominal too, issued yesterday: the e-invoicing demo stops it at « Reçue ».
        $this->invoice(ClientDataStory::CLIENT_CALANQUES_ID, [self::VACCINE], 1, paid: true, id: self::RECEIVED_INVOICE_ID);
        // Lyon and Cassis: their company (one SIREN) has no platform connection.
        $lyon = $this->invoice(ClientDataStory::CLIENT_RHONE_ID, [self::CONSULTATION, self::WORMER], 4, paid: true, clinicId: ClinicDataStory::GROUP_CLINIC_ID);
        // Its credit note waits for the invoice, itself waiting for a platform.
        $this->creditNote($lyon, 1, 'Vermifuge non délivré');
        $this->invoice(ClientDataStory::CLIENT_CALANCO_ID, [self::VACCINE], 3, paid: false, clinicId: ClinicDataStory::CALM_CLINIC_ID);

        // A professional without a street: its draft cannot be numbered yet.
        $this->draft(ClientDataStory::CLIENT_CALANQUES_AUBAGNE_ID, [self::CONSULTATION], 1);
    }

    /**
     * @param list<array{string, string, int, string}> $items
     */
    private function invoice(
        string $clientId,
        array $items,
        int $daysAgo,
        bool $paid,
        ?Discount $discount = null,
        ?string $id = null,
        string $clinicId = ClinicDataStory::INDEPENDENT_CLINIC_ID,
    ): Invoice {
        $invoice = $this->draft($clientId, $items, $daysAgo, $discount, $id, $clinicId);
        $at      = $invoice->createdAt();
        $clinic  = ClinicId::fromString($clinicId);
        $seller  = $this->sellers->forClinic($clinic->toString());

        new ElectronicInvoiceReadiness()->assertReady($invoice, $seller);
        $invoice->issue($this->numbers->next($clinic, (int) $at->format('Y')), $seller, $at, $paid ? Payment::create(
            id: PaymentId::fromString(Uuid::v7()->toRfc4122()),
            method: PaymentMethod::TRANSFER,
            amount: $invoice->totalGross(),
            receivedAt: $at,
            reference: null,
        ) : null);

        $this->invoices->save($invoice);
        // As at the desk: issuing freezes the document to transmit.
        $this->domainEventPublisher->publish($invoice);

        return $invoice;
    }

    private function creditNote(Invoice $invoice, int $line, string $reason): void
    {
        $this->commandBus->dispatch(new IssueCreditNote(
            clinicId: $invoice->clinicId()->toString(),
            invoiceId: $invoice->id()->toString(),
            lineIds: [$invoice->lines()[$line]->id()->toString()],
            reason: $reason,
        ));
    }

    /**
     * @param list<array{string, string, int, string}> $items
     */
    private function draft(
        string $clientId,
        array $items,
        int $daysAgo,
        ?Discount $discount = null,
        ?string $id = null,
        string $clinicId = ClinicDataStory::INDEPENDENT_CLINIC_ID,
    ): Invoice {
        $at    = new \DateTimeImmutable(\sprintf('-%d days 10:30', $daysAgo));
        $payer = $this->payers->forClient($clientId, $clinicId);
        \assert(null !== $payer);

        $invoice = Invoice::draft(
            id: InvoiceId::fromString($id ?? Uuid::v7()->toRfc4122()),
            clinicId: ClinicId::fromString($clinicId),
            admissionId: null,
            payer: $payer->toPayer(),
            currency: CurrencyCode::fromString('EUR'),
            now: $at,
        );

        foreach ($items as [$label, $category, $price, $quantity]) {
            $invoice->addLine($this->lines->line(
                clinicId: $clinicId,
                label: $label,
                code: null,
                quantity: $quantity,
                enteredUnitPrice: Money::fromMinorUnits($price, CurrencyCode::fromString('EUR')),
                priceMode: PriceMode::INCLUDING_TAX,
                taxCategoryCode: $category,
                source: InvoiceLineSource::MANUAL,
            ), $at);
        }

        $invoice->applyDiscount($discount, $at);
        $this->invoices->save($invoice);

        return $invoice;
    }
}
