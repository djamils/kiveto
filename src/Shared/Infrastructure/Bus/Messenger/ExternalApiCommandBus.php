<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus\Messenger;

use App\Shared\Application\Bus\ExternalApiCommandBusInterface;
use App\Shared\Application\Bus\ExternalApiCommandInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Sends on `messenger.bus.external_api`: the envelope is only queued (and stamped
 * with that bus name, so the external worker hands it back to the same bus).
 */
final readonly class ExternalApiCommandBus implements ExternalApiCommandBusInterface
{
    public function __construct(private MessageBusInterface $messageBus)
    {
    }

    public function send(ExternalApiCommandInterface $command, object ...$stamps): void
    {
        /** @var array<StampInterface> $stampsArray */
        $stampsArray = $stamps;

        $this->messageBus->dispatch(Envelope::wrap($command, $stampsArray));
    }
}
