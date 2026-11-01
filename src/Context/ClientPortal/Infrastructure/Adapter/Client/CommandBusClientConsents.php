<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Client;

use App\Context\Client\Application\Command\SetClientConsent\SetClientConsent;
use App\Context\ClientPortal\Application\Port\ClientConsentWriterInterface;
use App\Context\ClientPortal\Domain\Exception\ConsentConflictException;
use App\Shared\Application\Bus\CommandBusInterface;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\ORM\OptimisticLockException;

/**
 * Client's SetClientConsent, signed `portal` / `portal_user`. Client locks
 * the file (`FOR UPDATE`); a lock that still fails (version mismatch,
 * deadlock, lock wait timeout) becomes a ConsentConflictException.
 */
final readonly class CommandBusClientConsents implements ClientConsentWriterInterface
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function setConsent(string $clinicId, string $clientId, string $kind, array $channels, string $userId, string $authorLabel): void
    {
        try {
            $this->commandBus->dispatch(new SetClientConsent(
                clinicId: $clinicId,
                clientId: $clientId,
                kind: $kind,
                channels: $channels,
                source: 'portal',
                actorType: 'portal_user',
                actorId: $userId,
                authorLabel: $authorLabel,
            ));
        } catch (OptimisticLockException $exception) {
            throw new ConsentConflictException($exception);
        } catch (RetryableException $exception) {
            throw new ConsentConflictException($exception);
        }
    }
}
