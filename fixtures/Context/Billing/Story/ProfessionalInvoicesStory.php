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
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * Professional (B2B) invoices of the Paris clinic, one per professional whose
 * SIREN plays a scenario of the e-invoicing simulator, plus the cases the
 * fiscal model must show: a discount, goods and services mixed (framework M),
 * goods left to pay with a due date, a credit note on a professional invoice
 * and a draft whose buyer lacks an address (the readiness banner at the
 * counter). Prices are entered VAT included, as the clinic types them.
 */
final class ProfessionalInvoicesStory extends Story
{
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
    ) {
    }

    public function build(): void
    {
        // Nominal scenario — acts paid at the desk, with a loyalty discount (S2).
        $this->invoice(ClientDataStory::CLIENT_CALANQUES_ID, [self::CONSULTATION, self::VACCINE], 20, paid: true, discount: Discount::percentage('10', 'Client fidèle'));
        // Rejected — goods left to pay (B1, due date).
        $this->invoice(ClientDataStory::CLIENT_MISTRAL_ID, [self::FOOD], 18, paid: false);
        // Refused — acts and goods paid (M2); credited afterwards.
        $refused = $this->invoice(ClientDataStory::CLIENT_CRAU_ID, [self::CONSULTATION, self::WORMER], 15, paid: true);
        // Disputed — acts left to pay (S1).
        $this->invoice(ClientDataStory::CLIENT_ALPILLES_ID, [self::VACCINE], 12, paid: false);
        // Inadmissible — goods paid (B2).
        $this->invoice(ClientDataStory::CLIENT_LUBERON_ID, [self::WORMER, self::FOOD], 9, paid: true);
        // Buyer without a platform — mixed, left to pay (M1).
        $this->invoice(ClientDataStory::CLIENT_SAINTE_BAUME_ID, [self::CONSULTATION, self::FOOD], 6, paid: false);

        $this->commandBus->dispatch(new IssueCreditNote(
            clinicId: ClinicDataStory::INDEPENDENT_CLINIC_ID,
            invoiceId: $refused->id()->toString(),
            lineIds: [$refused->lines()[1]->id()->toString()],
            reason: 'Produit retourné',
        ));

        // A professional without a street: its draft cannot be numbered yet.
        $this->draft(ClientDataStory::CLIENT_CALANQUES_AUBAGNE_ID, [self::CONSULTATION], 1);
    }

    /**
     * @param list<array{string, string, int, string}> $items
     */
    private function invoice(string $clientId, array $items, int $daysAgo, bool $paid, ?Discount $discount = null): Invoice
    {
        $invoice = $this->draft($clientId, $items, $daysAgo, $discount);
        $at      = $invoice->createdAt();
        $clinic  = ClinicId::fromString(ClinicDataStory::INDEPENDENT_CLINIC_ID);
        $seller  = $this->sellers->forClinic($clinic->toString());
        $total   = $invoice->totalGross()->minorUnits();

        new ElectronicInvoiceReadiness()->assertReady($invoice, $seller);
        $invoice->issue($this->numbers->next($clinic, (int) $at->format('Y')), $seller, $at, $paid ? $total : 0);

        if ($paid) {
            $invoice->recordPayment(Payment::create(
                id: PaymentId::fromString(Uuid::v7()->toRfc4122()),
                method: PaymentMethod::TRANSFER,
                amount: $invoice->balanceDue(),
                receivedAt: $at,
                reference: null,
            ), $at);
        }

        $this->invoices->save($invoice);

        return $invoice;
    }

    /**
     * @param list<array{string, string, int, string}> $items
     */
    private function draft(string $clientId, array $items, int $daysAgo, ?Discount $discount = null): Invoice
    {
        $clinicId = ClinicDataStory::INDEPENDENT_CLINIC_ID;
        $at       = new \DateTimeImmutable(\sprintf('-%d days 10:30', $daysAgo));
        $payer    = $this->payers->forClient($clientId, $clinicId);
        \assert(null !== $payer);

        $invoice = Invoice::draft(
            id: InvoiceId::fromString(Uuid::v7()->toRfc4122()),
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
