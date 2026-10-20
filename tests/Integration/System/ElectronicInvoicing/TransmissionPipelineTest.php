<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\ElectronicInvoicing;

use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\ElectronicInvoicing\Application\Command\OpenPlatformConnection\OpenPlatformConnection;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\RequestInvoiceTransmission;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableInvoice;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableLine;
use App\System\ElectronicInvoicing\Application\Command\SyncAllPlatforms\SyncAllPlatforms;
use App\System\ElectronicInvoicing\Application\Command\VerifyPlatformConnection\VerifyPlatformConnection;
use App\System\ElectronicInvoicing\Application\Query\GetTransmissionStatus\GetTransmissionStatus;
use App\System\ElectronicInvoicing\Application\Query\GetTransmissionStatus\TransmissionStatusView;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionRepositoryInterface;
use App\System\ElectronicInvoicing\Domain\Transmission\TrackingId;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulationScenario;
use App\Tests\Support\MessengerDrain;
use App\Tests\Unit\System\ElectronicInvoicing\Support\TransmittableInvoices;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The whole way, through the real buses, the Doctrine transports consumed in
 * process, the CII renderer, the XSD and the local platform simulator.
 */
final class TransmissionPipelineTest extends KernelTestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-0000000ee0c1';

    /**
     * AC 22, 24, 28: waiting, released by the connection, submitted, then the
     * nominal lifecycle read back by the synchronisation.
     */
    public function testANominalInvoiceGoesAllTheWay(): void
    {
        $this->request(TransmittableInvoices::invoice());

        self::assertSame('pending_connection', $this->viewOf('doc-1')->state);
        self::assertSame(0, $this->queued('external'));

        $this->connect();
        self::assertSame(1, $this->queued('external'));
        self::assertSame('queued', $this->viewOf('doc-1')->state);

        $this->runWorkers();

        $status = $this->viewOf('doc-1');
        self::assertSame('accepted', $status->state);
        self::assertSame(205, $status->statusCode);
        self::assertSame('Approuvée', $status->statusLabel);
        self::assertSame('0225:523456788', $status->electronicAddress);

        $transmission = $this->transmission('doc-1');
        self::assertSame([200, 202, 203, 205], array_map(static fn ($e): int => $e->code->value, $transmission->lifecycle()));
        self::assertNotNull($transmission->flowId());

        // Replaying the synchronisation records nothing new.
        $version = $transmission->version();
        $this->runWorkers();
        self::assertSame($version, $this->transmission('doc-1')->version());
    }

    /**
     * AC 23: the same request twice gives one transmission and one flow.
     */
    public function testTheSameRequestTwiceGivesOneTransmission(): void
    {
        $this->connect();
        $this->request(TransmittableInvoices::invoice());
        $this->request(TransmittableInvoices::invoice());
        $this->runWorkers();

        self::assertSame(1, $this->rowCount('SELECT COUNT(*) FROM electronic_invoicing__outbound_transmissions'));
        self::assertSame(1, $this->rowCount('SELECT COUNT(*) FROM electronic_invoicing__simulated_flows'));
    }

    /**
     * AC 29: every simulator scenario reaches its end state.
     */
    public function testEveryScenarioReachesItsEndState(): void
    {
        $this->connect();
        $buyers = [
            SimulationScenario::BUYER_REJECTED        => [213, 'REJ_SEMAN', 'accepted'],
            SimulationScenario::BUYER_REFUSED         => [210, 'DOUBLON', 'accepted'],
            SimulationScenario::BUYER_DISPUTED        => [207, 'TX_TVA_ERR', 'accepted'],
            SimulationScenario::BUYER_INADMISSIBLE    => [501, 'IRR_SYNTAX', 'inadmissible'],
            SimulationScenario::BUYER_NOT_TRANSMITTED => [200, 'NON_TRANSMISE', 'accepted'],
        ];

        foreach (array_keys($buyers) as $i => $buyer) {
            $this->request(TransmittableInvoices::invoice('doc-' . $i, 'PAR-' . $i, $buyer));
        }

        $this->runWorkers();

        foreach (array_keys($buyers) as $i => $buyer) {
            [$code, $reason, $state] = $buyers[$buyer];
            $status                  = $this->viewOf('doc-' . $i);

            self::assertSame($code, $status->statusCode, $buyer);
            self::assertSame($reason, $status->reasonCode, $buyer);
            self::assertSame($state, $status->state, $buyer);
        }

        // AC 25: the buyer without a platform keeps the default address.
        self::assertSame('0225:000000000', $this->viewOf('doc-4')->electronicAddress);
        self::assertFalse($this->viewOf('doc-4')->requiresAction);
        self::assertTrue($this->viewOf('doc-1')->requiresAction);
    }

    /**
     * AC 27: a payload that does not render into a valid file is blocked.
     */
    public function testAnInvalidDocumentIsBlocked(): void
    {
        $this->connect();
        $valid   = TransmittableInvoices::invoice();
        $invalid = TransmittableInvoice::fromArray(array_replace($valid->toArray(), [
            'lines' => [new TransmittableLine('1', 'Consultation', '1', 'C62', '50.000000', 5000, 'S', '20,00')->toArray()],
        ]));

        $this->request($invalid);
        $this->runWorkers();

        $status = $this->viewOf('doc-1');
        self::assertSame('blocked', $status->state);
        self::assertSame('RENDERING_FAILED', $status->blockReason);
        self::assertTrue($status->requiresAction);
        self::assertSame(0, $this->rowCount('SELECT COUNT(*) FROM electronic_invoicing__simulated_flows'));
    }

    /**
     * AC 27d: a credit note follows its invoice; a credit note of a refused
     * invoice is not transmitted.
     */
    public function testCreditNotesFollowTheirInvoice(): void
    {
        $this->request(TransmittableInvoices::invoice('doc-1', 'PAR-1', SimulationScenario::BUYER_REFUSED));
        $this->request(TransmittableInvoices::creditNote('cn-1', 'AV-1', 'PAR-1'));
        $this->connect();
        $this->runWorkers();

        self::assertSame(210, $this->viewOf('doc-1')->statusCode);

        $note = $this->viewOf('cn-1');
        self::assertContains($note->state, ['not_required', 'queued', 'accepted', 'submitted']);

        // Once the invoice is refused, a new credit note is not required.
        $this->request(TransmittableInvoices::creditNote('cn-2', 'AV-2', 'PAR-1'));
        self::assertSame('not_required', $this->viewOf('cn-2')->state);
    }

    private function connect(): void
    {
        $this->commands()->dispatch(new OpenPlatformConnection(TransmittableInvoices::SELLER));
        $this->commands()->dispatch(new VerifyPlatformConnection(TransmittableInvoices::SELLER));
    }

    private function request(TransmittableInvoice $invoice): void
    {
        $this->commands()->dispatch(new RequestInvoiceTransmission(self::CLINIC, $invoice));
    }

    /**
     * What the workers and the scheduler would do: send, let the simulator
     * play its scenario, synchronise.
     */
    private function runWorkers(): void
    {
        MessengerDrain::drain(self::getContainer());
        $this->commands()->dispatch(new SyncAllPlatforms());
        MessengerDrain::drain(self::getContainer());
    }

    private function viewOf(string $documentId): TransmissionStatusView
    {
        $queries = self::getContainer()->get(QueryBusInterface::class);
        \assert($queries instanceof QueryBusInterface);
        $view = $queries->ask(new GetTransmissionStatus($documentId));
        \assert($view instanceof TransmissionStatusView);

        return $view;
    }

    private function transmission(string $documentId): \App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmission
    {
        $repository = self::getContainer()->get('test.' . OutboundTransmissionRepositoryInterface::class);
        \assert($repository instanceof OutboundTransmissionRepositoryInterface);
        $transmission = $repository->byTrackingId(TrackingId::fromString($documentId));
        \assert(null !== $transmission);

        return $transmission;
    }

    private function queued(string $queue): int
    {
        return $this->rowCount('SELECT COUNT(*) FROM shared__messenger_messages WHERE queue_name = :q', ['q' => $queue]);
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
