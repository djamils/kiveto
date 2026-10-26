<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Billing\Pipeline;

use App\Context\Billing\Application\Command\SendInvoiceToClient\InvoiceDispatchOutcome;
use App\Context\Billing\Application\Command\SendInvoiceToClient\SendInvoiceToClient;
use App\Context\Billing\Application\Port\InvoicePdfRendererInterface;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatch;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatchRepositoryInterface;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\InvoiceNumber;
use App\Context\Billing\Domain\ValueObject\OperationCategory;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\PayerId;
use App\Context\Billing\Domain\ValueObject\PriceMode;
use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\Civility;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClinicId as ClientClinicId;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Storage\FileStorageInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Notification\Application\Command\UpsertClinicMessagingProfile\UpsertClinicMessagingProfile;
use App\System\Notification\Domain\Message\IdempotencyKey;
use App\System\Notification\Domain\Message\OutboundMessage;
use App\System\Notification\Domain\Message\OutboundMessageRepositoryInterface;
use App\Tests\Support\Billing\SellerIdentities;
use App\Tests\Support\MessengerDrain;
use App\Tests\Unit\Context\Billing\Support\FakeInvoicePdfRenderer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * « Envoyer » through the real wiring: the request logs and queues, the
 * worker (consumed as on `async`) stores the PDF under Billing's key and
 * asks Notification for the e-mail with the file attached as Billing's.
 */
final class InvoiceDispatchPipelineTest extends KernelTestCase
{
    private const string CLINIC  = '01960000-0000-7000-8000-00000000e401';
    private const string INVOICE = '01960000-0000-7000-8000-00000000e402';
    private const string CLIENT  = '01960000-0000-7000-8000-00000000e403';

    private FakeInvoicePdfRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new FakeInvoicePdfRenderer('FAC-2026-000814.pdf');
        self::getContainer()->set(InvoicePdfRendererInterface::class, $this->renderer);

        $this->commandBus()->dispatch(new UpsertClinicMessagingProfile(self::CLINIC, 'Clinique Montmartre', 'clinique-montmartre', 'accueil@clinique-montmartre.fr', 'Montmartre', 500, '18 rue Lepic, 75018 Paris', 'Paris 18e', '01 42 64 18 00', '#4338CA', null));

        $clients = self::getContainer()->get(ClientRepositoryInterface::class);
        \assert($clients instanceof ClientRepositoryInterface);
        $clients->save(Client::create(
            ClientId::fromString(self::CLIENT),
            ClientClinicId::fromString(self::CLINIC),
            new ClientIdentity('Sophie', 'Dubois', Civility::MADAM),
            [ContactMethod::email(EmailAddress::fromString('s.dubois@email.fr'), ContactLabel::HOME, true)],
            new \DateTimeImmutable('2026-01-12 10:00:00'),
        ));

        $this->issue();
    }

    protected function tearDown(): void
    {
        // The PDF is written to the real storage (outside the test transaction).
        $files = self::getContainer()->get(FileStorageInterface::class);
        \assert($files instanceof FileStorageInterface);
        $files->delete('billing/sent/invoice/' . self::INVOICE . '.pdf');

        parent::tearDown();
    }

    public function testTheInvoiceIsEmailedWithItsStoredPdf(): void
    {
        $outcome = $this->commandBus()->dispatch(new SendInvoiceToClient(self::CLINIC, [self::INVOICE], null));
        \assert($outcome instanceof InvoiceDispatchOutcome);
        self::assertSame([self::INVOICE], $outcome->queued);
        self::assertSame([], $this->renderer->rendered, 'No PDF inside the request.');

        MessengerDrain::drain(self::getContainer(), ['async']);

        $dispatch = $this->dispatches()[0];
        $message  = $this->message('invoice:' . self::INVOICE . ':send:' . $dispatch->id()->toString());
        self::assertSame([$message->id()->toString()], $dispatch->notificationIds());
        self::assertSame(['INVOICE_SENT', 'EMAIL', self::CLIENT, 's.dubois@email.fr'], [$message->purpose()->value, $message->channel()->value, $message->clientId(), $message->recipient()?->address]);
        self::assertSame('Votre facture FAC-2026-000814 — Clinique Montmartre', $message->content()?->subject);
        self::assertStringContainsString('Reste dû', (string) $message->content()->html);

        $attachment = $message->attachments()[0];
        self::assertSame(['billing/sent/invoice/' . self::INVOICE . '.pdf', 'FAC-2026-000814.pdf', 'application/pdf', 'REQUESTER'], [$attachment->storageKey, $attachment->filename, $attachment->mime, $attachment->owner->value]);

        $files = self::getContainer()->get(FileStorageInterface::class);
        \assert($files instanceof FileStorageInterface);
        self::assertSame('%PDF-' . self::INVOICE, $files->read($attachment->storageKey));
    }

    /**
     * @return list<InvoiceDispatch>
     */
    private function dispatches(): array
    {
        $dispatches = self::getContainer()->get('test.' . InvoiceDispatchRepositoryInterface::class);
        \assert($dispatches instanceof InvoiceDispatchRepositoryInterface);

        return $dispatches->listForInvoice(ClinicId::fromString(self::CLINIC), InvoiceId::fromString(self::INVOICE));
    }

    private function message(string $key): OutboundMessage
    {
        $messages = self::getContainer()->get('test.' . OutboundMessageRepositoryInterface::class);
        \assert($messages instanceof OutboundMessageRepositoryInterface);
        $message = $messages->findByIdempotencyKey(IdempotencyKey::fromString($key));
        self::assertNotNull($message, $key);

        return $message;
    }

    private function issue(): void
    {
        $now     = new \DateTimeImmutable('2026-10-16 08:30:00', new \DateTimeZone('UTC'));
        $eur     = CurrencyCode::fromString('EUR');
        $invoice = Invoice::draft(
            id: InvoiceId::fromString(self::INVOICE),
            clinicId: ClinicId::fromString(self::CLINIC),
            admissionId: null,
            payer: Payer::create(PayerId::fromString(self::CLIENT), 'Mme Sophie Dubois', ['12 rue des Lilas']),
            currency: $eur,
            now: $now,
        );
        $invoice->addLine(InvoiceLine::create(
            id: InvoiceLineId::fromString('01960000-0000-7000-8000-00000000e404'),
            label: 'Vermifuge',
            code: null,
            quantity: '1',
            enteredUnitPrice: Money::fromMinorUnits(8870, $eur),
            priceMode: PriceMode::INCLUDING_TAX,
            taxCategoryCode: 'veterinary.drug.companion',
            taxRatePercent: '20',
            operationCategory: OperationCategory::GOODS,
            source: InvoiceLineSource::COUNTER,
            sourceReference: null,
        ), $now);
        $invoice->issue(InvoiceNumber::of(2026, 814), SellerIdentities::complete(), $now);

        $invoices = self::getContainer()->get(InvoiceRepositoryInterface::class);
        \assert($invoices instanceof InvoiceRepositoryInterface);
        $invoices->save($invoice);
    }

    private function commandBus(): CommandBusInterface
    {
        $bus = self::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);

        return $bus;
    }
}
