<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\ReconcileProfessionalTransmissions;

use App\Context\Billing\Application\Command\RequestDocumentTransmission\RequestDocumentTransmission;
use App\Context\Billing\Application\Port\TransmissionStatusProjectionInterface;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Professional documents still only « requested » an hour after their issue
 * are requested again (the electronic-invoicing context ignores duplicates);
 * after a day the projection says the request failed — someone must act —
 * and an error is logged.
 */
#[AsMessageHandler]
final readonly class ReconcileProfessionalTransmissionsHandler
{
    public const string RETRY_AFTER   = '-1 hour';
    public const string GIVE_UP_AFTER = '-24 hours';

    public function __construct(
        private TransmissionStatusProjectionInterface $projection,
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
            if ($document['issuedAt'] <= $giveUp) {
                $this->projection->markRequestFailed($document['documentId'], $document['kind'], $now);
                $this->logger->error('A professional document was never handed to electronic invoicing.', [
                    'documentId' => $document['documentId'],
                    'kind'       => $document['kind'],
                ]);

                continue;
            }

            $this->commandBus->dispatch(new RequestDocumentTransmission($document['clinicId'], $document['documentId'], $document['kind']));
        }
    }
}
