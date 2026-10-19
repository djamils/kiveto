<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\RecordCounterSale;

use App\Context\Billing\Application\Port\CounterCatalogInterface;
use App\Context\Billing\Application\Port\CounterClientRegistryInterface;
use App\Context\Billing\Application\Port\PayerDirectoryInterface;
use App\Context\Billing\Application\Service\InvoiceLineFactory;
use App\Context\Billing\Domain\Exception\CounterClientAlreadyKnownException;
use App\Context\Billing\Domain\Exception\CounterClientNotReadBackException;
use App\Context\Billing\Domain\Exception\InvalidCounterClientException;
use App\Context\Billing\Domain\Exception\InvalidCounterClientTaxProfileException;
use App\Context\Billing\Domain\Exception\InvalidInvoiceLineException;
use App\Context\Billing\Domain\Exception\InvalidPayerException;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\Service\InvoiceNumberGeneratorInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\CounterSalePayer;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\PayerId;
use App\Context\Billing\Domain\ValueObject\Payment;
use App\Context\Billing\Domain\ValueObject\PaymentId;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Context\Billing\Domain\ValueObject\PriceMode;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\FrenchVatNumber;
use App\Shared\Domain\ValueObject\Siren;
use App\Shared\Money\Domain\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Bills what the desk sold — priced from the catalogue, taxed by the clinic's
 * VAT rules — issues the invoice and takes its payment in full. The sale has
 * no visit behind it; it is made out to the client chosen, to the newcomer
 * whose record it creates (unless their phone already belongs to a client),
 * or — when the client refuses to give any contact details — to nobody, the
 * refusal being kept with the sale. A professional is billed under its legal
 * name, to the attention of its contact.
 */
#[AsMessageHandler]
final readonly class RecordCounterSaleHandler
{
    private const int MAX_QUANTITY = 999;
    private const int MAX_NAME     = 40;
    private const int MAX_EMAIL    = 80;
    private const int MAX_LEGAL    = 255;

    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private InvoiceNumberGeneratorInterface $numbers,
        private CounterCatalogInterface $catalog,
        private InvoiceLineFactory $lineFactory,
        private PayerDirectoryInterface $payerDirectory,
        private CounterClientRegistryInterface $clientRegistry,
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

            $lines[] = $this->lineFactory->line(
                clinicId: $command->clinicId,
                label: $article->label,
                code: $article->code,
                quantity: (string) $item['quantity'],
                enteredUnitPrice: Money::fromMinorUnits($article->unitPriceMinorUnits, $currency),
                priceMode: PriceMode::from($article->unitPriceMode),
                taxCategoryCode: $article->taxCategoryCode,
                source: InvoiceLineSource::COUNTER,
                sourceReference: $article->articleId,
            );
        }

        [$payer, $kind] = $this->payer($command);

        $invoice = Invoice::draft(
            id: InvoiceId::fromString($this->uuidGenerator->generate()),
            clinicId: $clinicId,
            admissionId: null,
            payer: $payer,
            currency: $currency,
            now: $now,
            counterSalePayer: $kind,
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

    /**
     * @return array{Payer, CounterSalePayer}
     */
    private function payer(RecordCounterSale $command): array
    {
        if ($command->contactDetailsRefused) {
            return [Payer::contactDetailsRefused(), CounterSalePayer::REFUSED];
        }

        if (null !== $command->newClient) {
            return [$this->register($command->clinicId, $command->newClient), CounterSalePayer::NEW_CLIENT];
        }

        $snapshot = null !== $command->payerClientId
            ? $this->payerDirectory->forClient($command->payerClientId, $command->clinicId)
            : null;

        if (null === $snapshot) {
            throw new InvalidPayerException('A counter sale is made out to a client of the clinic.');
        }

        return [
            Payer::create(
                null !== $snapshot->clientId ? PayerId::fromString($snapshot->clientId) : null,
                $snapshot->displayName,
                $snapshot->addressLines,
                $snapshot->attention,
            ),
            CounterSalePayer::CLIENT,
        ];
    }

    /**
     * Opens the newcomer's record — a name, and a phone or an e-mail to find
     * and reach them by — and makes the invoice out to them. A professional is
     * read back from its record, to be billed under its legal name.
     */
    private function register(string $clinicId, CounterSaleNewClient $client): Payer
    {
        $this->validate($client);

        if ('' !== $client->phone) {
            $known = $this->payerDirectory->clientWithPhone($client->internationalPhone(), $clinicId);

            if (null !== $known) {
                throw new CounterClientAlreadyKnownException($known);
            }
        }

        $clientId = $this->clientRegistry->register($clinicId, $client);

        if ($client->isProfessional()) {
            $snapshot = $this->payerDirectory->forClient($clientId, $clinicId)
                ?? throw new CounterClientNotReadBackException($clientId);

            return Payer::create(PayerId::fromString($clientId), $snapshot->displayName, $snapshot->addressLines, $snapshot->attention);
        }

        return Payer::create(PayerId::fromString($clientId), $client->displayName());
    }

    private function validate(CounterSaleNewClient $client): void
    {
        if (!\in_array($client->civility, [CounterSaleNewClient::MISTER, CounterSaleNewClient::MADAM], true)) {
            throw new InvalidCounterClientException(\sprintf('Unknown civility "%s".', $client->civility));
        }

        foreach ([$client->lastName, $client->firstName] as $name) {
            if (mb_strlen(mb_trim($name)) < 1 || mb_strlen($name) > self::MAX_NAME) {
                throw new InvalidCounterClientException('A newcomer needs a first and a last name.');
            }
        }

        if (mb_strlen(mb_trim($client->lastName)) < 2) {
            throw new InvalidCounterClientException('The last name is too short.');
        }

        if ('' !== $client->phone && 1 !== preg_match('/^0[1-9]\d{8}$/', $client->phone)) {
            throw new InvalidCounterClientException('Invalid phone number.');
        }

        if ('' !== $client->email && (mb_strlen($client->email) > self::MAX_EMAIL || false === filter_var($client->email, \FILTER_VALIDATE_EMAIL) || 1 !== preg_match('/\.[a-z]{2,}$/i', $client->email))) {
            throw new InvalidCounterClientException('Invalid e-mail address.');
        }

        if ('' === $client->phone && '' === $client->email) {
            throw new InvalidCounterClientException('A newcomer needs a mobile or an e-mail.');
        }

        if (null !== $client->taxProfile) {
            $this->validateTaxProfile($client->taxProfile);
        }
    }

    /**
     * A structure opened at the desk: a known status, a legal name and, when
     * VAT-liable, a valid SIREN and VAT number. Checked here so that the
     * Client context never refuses what the desk sends.
     */
    private function validateTaxProfile(ClientTaxProfileInput $profile): void
    {
        if (!\in_array($profile->status, [ClientTaxProfileInput::INDIVIDUAL, ClientTaxProfileInput::FRENCH_BUSINESS], true)) {
            throw new InvalidCounterClientTaxProfileException(\sprintf('Unknown tax status "%s".', $profile->status));
        }

        $legalName = mb_trim((string) $profile->legalName);

        if ('' === $legalName || mb_strlen($legalName) > self::MAX_LEGAL) {
            throw new InvalidCounterClientTaxProfileException('A structure needs a legal name of at most 255 characters.');
        }

        if (ClientTaxProfileInput::FRENCH_BUSINESS !== $profile->status) {
            return;
        }

        try {
            $siren = Siren::fromInput((string) $profile->siren);

            if (null !== $profile->vatNumber && '' !== mb_trim($profile->vatNumber)) {
                FrenchVatNumber::fromInput($profile->vatNumber, $siren);
            }
        } catch (\InvalidArgumentException $e) { // InvalidSirenException or InvalidFrenchVatNumberException
            throw new InvalidCounterClientTaxProfileException($e->getMessage(), previous: $e);
        }
    }
}
