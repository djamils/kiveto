<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\ReconcileProfessionalTransmissions;

use App\Context\Billing\Application\Command\RequestDocumentTransmission\RequestDocumentTransmission;
use App\Context\Billing\Application\Port\ElectronicInvoiceTransmitterInterface;
use App\Context\Billing\Application\Port\TransmissionStatusProjectionInterface;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Professional documents still only « requested » an hour after their issue:
 * when electronic invoicing has them already, their current status is
 * projected; otherwise they are requested again (duplicates are ignored
 * there) and, after a day, the projection says the request failed — someone
 * must act — and an error is logged. Each document is handled on its own: a
 * failure is logged and the next one goes on.
 */
#[AsMessageHandler]
final readonly class ReconcileProfessionalTransmissionsHandler
{
    public const string RETRY_AFTER   = '-1 hour';
    public const string GIVE_UP_AFTER = '-24 hours';

    public function __construct(
        private TransmissionStatusProjectionInterface $projection,
        private ElectronicInvoiceTransmitterInterface $transmitter,
        private CommandBusInterface $commandBus,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ReconcileProfessionalTransmissions $command): void
    {
        $now    = $this->clock->now();
        $giveUp = $now->modify(self::GIVE_UP_AFTER);

        foreach ($this->projection->stillRequested($now->modify(self::RETRY_AFTER)) as $document) {
            try {
                $this->reconcile($document, $giveUp, $now);
            } catch (\Throwable $e) {
                $this->logger->error('A professional document could not be reconciled with electronic invoicing.', [
                    'documentId' => $document['documentId'],
                    'kind'       => $document['kind'],
                    'exception'  => $e,
                ]);
            }
        }
    }

    /**
     * @param array{clinicId: string, documentId: string, kind: string, issuedAt: \DateTimeImmutable} $document
     */
    private function reconcile(array $document, \DateTimeImmutable $giveUp, \DateTimeImmutable $now): void
    {
        $status = $this->transmitter->status($document['documentId']);

        if (null !== $status) {
            $this->projection->apply($status);

            return;
        }

        if ($document['issuedAt'] <= $giveUp) {
            $this->projection->markRequestFailed($document['documentId'], $document['kind'], $now);
            $this->logger->error('A professional document was never handed to electronic invoicing.', [
                'documentId' => $document['documentId'],
                'kind'       => $document['kind'],
            ]);

            return;
        }

        $this->commandBus->dispatch(new RequestDocumentTransmission($document['clinicId'], $document['documentId'], $document['kind']));
    }
}
