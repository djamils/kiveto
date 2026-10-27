<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Messaging;

use App\Context\Clinic\Application\Command\Staff\RecordVeterinarianDailyCounts\RecordVeterinarianDailyCounts;
use App\Context\Clinic\Application\Port\VeterinarianCountJobsInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * RecordVeterinarianDailyCounts has no route: a membership change counts in
 * its own transaction. The nightly fan-out sends the per-clinic command to
 * `async` explicitly, on the raw command bus (the application command bus
 * expects a handled result); each job is retried on its own.
 */
final readonly class MessengerVeterinarianCountJobs implements VeterinarianCountJobsInterface
{
    public const string TRANSPORT = 'async';

    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
    ) {
    }

    public function countLater(string $clinicId): void
    {
        $this->commandBus->dispatch(new RecordVeterinarianDailyCounts($clinicId), [new TransportNamesStamp([self::TRANSPORT])]);
    }
}
