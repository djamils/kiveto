<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Adapter\Notification;

use App\Context\Clinic\Application\Service\MessagingProfilePublisher;
use App\Context\Clinic\Domain\Event\ClinicBrandingChanged;
use App\Context\Clinic\Domain\Event\ClinicCreated;
use App\Context\Clinic\Domain\Event\ClinicLetterheadUpdated;
use App\Context\Clinic\Domain\Event\ClinicMessagingSettingsChanged;
use App\Context\Clinic\Domain\Event\ClinicRenamed;
use App\Context\Clinic\Domain\Event\ClinicSlugChanged;
use App\Shared\Domain\Event\DomainEventInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Every change of what a clinic's messages show (name, slug, letterhead,
 * branding, SMS settings) is pushed to Notification's copy, in the same
 * transaction as the change.
 */
final readonly class ClinicMessagingProfileBridge
{
    public function __construct(private MessagingProfilePublisher $publisher)
    {
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onCreated(ClinicCreated $event): void
    {
        $this->publish($event);
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onRenamed(ClinicRenamed $event): void
    {
        $this->publish($event);
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onSlugChanged(ClinicSlugChanged $event): void
    {
        $this->publish($event);
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onLetterheadUpdated(ClinicLetterheadUpdated $event): void
    {
        $this->publish($event);
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onBrandingChanged(ClinicBrandingChanged $event): void
    {
        $this->publish($event);
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onMessagingSettingsChanged(ClinicMessagingSettingsChanged $event): void
    {
        $this->publish($event);
    }

    private function publish(DomainEventInterface $event): void
    {
        $this->publisher->publish($event->aggregateId());
    }
}
