<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Billing;

use App\Context\Billing\Application\Command\ReconcileProfessionalTransmissions\ReconcileProfessionalTransmissions;
use App\Context\Billing\Application\Command\RecordCounterSale\ClientTaxProfileInput;
use App\Context\Billing\Application\Command\RecordCounterSale\CounterSaleNewClient;
use App\Context\Billing\Application\Command\RecordCounterSale\RecordCounterSale;
use App\Context\Billing\Application\Command\RecordTransmissionStatus\RecordTransmissionStatus;
use App\Context\Billing\Application\Query\GetInvoiceDetails\GetInvoiceDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\TransmissionView;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Embeddable\LetterheadEmbeddable;
use App\Fixtures\Context\Catalog\Factory\ArticleEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\System\Taxation\Story\TaxonomyBootstrapStory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\ElectronicInvoicing\Application\Command\OpenPlatformConnection\OpenPlatformConnection;
use App\System\ElectronicInvoicing\Application\Command\SyncAllPlatforms\SyncAllPlatforms;
use App\System\ElectronicInvoicing\Application\Command\VerifyPlatformConnection\VerifyPlatformConnection;
use App\Tests\Support\MessengerDrain;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * Billing → ElectronicInvoicing and back, through the real buses, the outbox
 * and the local platform simulator.
 */
final class ElectronicInvoicingBridgeTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID = '01960000-0000-7000-8000-0000000e1b01';
    private const string SELLER    = '812456903';

    private const string OUTBOX = "SELECT COUNT(*) FROM shared__messenger_messages WHERE body LIKE '%ProfessionalDocumentIssuedIntegrationEvent%'";
    private bool $clinicCreated = false;

    /**
     * AC 33: the request is written with the issue — and only with it.
     */
    public function testAProfessionalSaleWritesItsRequestInTheSameTransaction(): void
    {
        $invoiceId = $this->sell();

        self::assertSame('requested', $this->transmissionOf($invoiceId)?->state);
        self::assertSame(1, $this->rowCount(self::OUTBOX));

        $connection = $this->connection();
        $connection->beginTransaction();
        $rolledBack = $this->sell('0491223366');
        $connection->rollBack();

        self::assertSame(0, $this->rowCount('SELECT COUNT(*) FROM billing__invoices WHERE id = UUID_TO_BIN(:id)', ['id' => $rolledBack]));
        self::assertSame(1, $this->rowCount(self::OUTBOX));
    }

    /**
     * AC 39: a private sale requests nothing.
     */
    public function testAPrivateSaleRequestsNothing(): void
    {
        $invoiceId = $this->sell(professional: false);

        self::assertNull($this->transmissionOf($invoiceId));
        self::assertSame(0, $this->rowCount(self::OUTBOX));
    }

    /**
     * AC 22, 37: unconnected, the invoice waits; connected, it goes all the
     * way and the projection follows.
     */
    public function testTheProjectionFollowsTheTransmission(): void
    {
        $invoiceId = $this->sell();
        MessengerDrain::drain(self::getContainer());

        self::assertSame('pending_connection', $this->transmissionOf($invoiceId)?->state);

        $this->commands()->dispatch(new OpenPlatformConnection(self::SELLER));
        $this->commands()->dispatch(new VerifyPlatformConnection(self::SELLER));
        MessengerDrain::drain(self::getContainer());
        $this->commands()->dispatch(new SyncAllPlatforms());
        MessengerDrain::drain(self::getContainer());

        $transmission = $this->transmissionOf($invoiceId);
        self::assertSame('accepted', $transmission->state);
        self::assertSame(205, $transmission->statusCode);
        self::assertSame('Approuvée', $transmission->statusLabel);
        self::assertFalse($transmission->requiresAction);
    }

    /**
     * AC 35: duplicates and late reports never move the projection back.
     */
    public function testOnlyANewerReportChangesTheProjection(): void
    {
        $invoiceId = $this->sell();

        self::assertTrue($this->report($invoiceId, 7, 205, 'Approuvée'));
        self::assertFalse($this->report($invoiceId, 5, 203, 'Mise à disposition'));
        self::assertFalse($this->report($invoiceId, 7, 205, 'Approuvée'));

        $transmission = $this->transmissionOf($invoiceId);
        self::assertSame(205, $transmission?->statusCode);
        self::assertSame('Approuvée', $transmission->statusLabel);

        self::assertTrue($this->report($invoiceId, 8, 210, 'Refusée', 'DOUBLON', str_repeat('x', 600), true));
        $refused = $this->transmissionOf($invoiceId);
        self::assertTrue($refused?->requiresAction);
        self::assertSame('DOUBLON', $refused->reasonCode);
        self::assertSame(512, mb_strlen((string) $refused->reason));

        // A report about a document Billing does not know changes nothing.
        self::assertFalse($this->report(Uuid::v7()->toRfc4122(), 1, 205, 'Approuvée'));
    }

    /**
     * AC 33: requested again after an hour, « request failed » after a day.
     */
    public function testTheReconciliationCatchesUpThenGivesUp(): void
    {
        $recent = $this->sell();
        $stale  = $this->sell('0491223366');
        $this->connection()->executeStatement('UPDATE billing__invoices SET issued_at = NOW() - INTERVAL 2 HOUR WHERE id = UUID_TO_BIN(:id)', ['id' => $recent]);
        $this->connection()->executeStatement('UPDATE billing__invoices SET issued_at = NOW() - INTERVAL 2 DAY WHERE id = UUID_TO_BIN(:id)', ['id' => $stale]);

        $this->commands()->dispatch(new ReconcileProfessionalTransmissions());

        $failed = $this->transmissionOf($stale);
        self::assertSame('request_failed', $failed?->state);
        self::assertTrue($failed->requiresAction);

        // The request dispatched again reached ElectronicInvoicing, whose
        // report replaces Billing's own state.
        $this->connection()->executeStatement("DELETE FROM shared__messenger_messages WHERE body LIKE '%ProfessionalDocumentIssuedIntegrationEvent%'");
        MessengerDrain::drain(self::getContainer());
        self::assertSame('pending_connection', $this->transmissionOf($recent)?->state);
        self::assertSame('request_failed', $this->transmissionOf($stale)?->state);
    }

    private function sell(string $phone = '0491223355', bool $professional = true): string
    {
        $this->clinic();

        $article = ArticleEntityFactory::new()->forClinic(self::CLINIC_ID)->create(['basePriceMinorUnits' => 1500, 'taxCategoryCode' => 'veterinary.consumable.medical']);
        $profile = $professional ? new ClientTaxProfileInput('french_business', 'Élevage des Calanques', '523456788', 'FR31523456788') : null;

        $invoiceId = $this->commands()->dispatch(new RecordCounterSale(
            clinicId: self::CLINIC_ID,
            payerClientId: null,
            lines: [['articleId' => $article->getId()->toRfc4122(), 'quantity' => 1]],
            method: 'CARD',
            newClient: new CounterSaleNewClient(CounterSaleNewClient::MADAM, 'Rostand', 'Claire', $phone, taxProfile: $profile, street: 'Chemin des Goudes', postalCode: '13008', city: 'Marseille'),
        ));
        \assert(\is_string($invoiceId));

        return $invoiceId;
    }

    private function clinic(): void
    {
        if ($this->clinicCreated) {
            return;
        }

        $this->clinicCreated = true;

        TaxonomyBootstrapStory::load();
        ClinicEntityFactory::new()
            ->with(['id' => Uuid::fromString(self::CLINIC_ID)])
            ->withLetterhead(new LetterheadEmbeddable(
                legalName: 'SELARL Clinique Vétérinaire de Paris',
                siret: '81245690300028',
                vatNumber: 'FR20812456903',
                streetLine1: '18 rue des Abbesses',
                postalCode: '75018',
                city: 'Paris',
                countryCode: 'FR',
            ))
            ->create()
        ;
    }

    private function report(string $documentId, int $sequence, int $code, string $label, ?string $reasonCode = null, ?string $reason = null, bool $requiresAction = false): bool
    {
        $changed = $this->commands()->dispatch(new RecordTransmissionStatus(
            documentId: $documentId,
            sequence: $sequence,
            state: 'accepted',
            blockReason: null,
            statusCode: $code,
            statusLabel: $label,
            reasonCode: $reasonCode,
            reasonText: $reason,
            requiresAction: $requiresAction,
            occurredAt: '2026-10-21T10:00:00+02:00',
        ));
        \assert(\is_bool($changed));

        return $changed;
    }

    private function transmissionOf(string $invoiceId): ?TransmissionView
    {
        $queries = self::getContainer()->get(QueryBusInterface::class);
        \assert($queries instanceof QueryBusInterface);
        $details = $queries->ask(new GetInvoiceDetails(self::CLINIC_ID, $invoiceId));
        \assert($details instanceof InvoiceDetails);

        return $details->transmission;
    }

    /**
     * @param array<string, string> $params
     */
    private function rowCount(string $sql, array $params = []): int
    {
        $count = $this->connection()->fetchOne($sql, $params);

        return is_numeric($count) ? (int) $count : -1;
    }

    private function commands(): CommandBusInterface
    {
        $bus = self::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);

        return $bus;
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return $connection;
    }
}
