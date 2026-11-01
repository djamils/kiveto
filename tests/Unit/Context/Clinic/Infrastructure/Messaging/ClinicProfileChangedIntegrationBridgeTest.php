<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Infrastructure\Messaging;

use App\Context\Clinic\Application\Port\ClinicReadRepositoryInterface;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\ListClinics\ClinicCollection;
use App\Context\Clinic\Domain\Event\ClinicActivated;
use App\Context\Clinic\Domain\Event\ClinicClosed;
use App\Context\Clinic\Domain\Event\ClinicCreated;
use App\Context\Clinic\Domain\Event\ClinicGroupActivated;
use App\Context\Clinic\Domain\Event\ClinicGroupSuspended;
use App\Context\Clinic\Domain\Event\ClinicLetterheadUpdated;
use App\Context\Clinic\Domain\Event\ClinicOpeningHoursUpdated;
use App\Context\Clinic\Domain\Event\ClinicProfileChangedIntegrationEvent;
use App\Context\Clinic\Domain\Event\ClinicRenamed;
use App\Context\Clinic\Domain\Event\ClinicSuspended;
use App\Context\Clinic\Domain\Event\ClinicTimeZoneChanged;
use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Context\Clinic\Infrastructure\Messaging\Consumer\ClinicProfileChangedIntegrationBridge;
use App\Shared\Application\Bus\IntegrationEventBusInterface;
use App\Shared\Application\Event\IntegrationEventPublisher;
use PHPUnit\Framework\TestCase;

/**
 * Task 46 (S7c): every change of what a clinic's public profile shows is
 * announced once for that clinic; a group suspended or reactivated announces
 * each of its member clinics.
 */
final class ClinicProfileChangedIntegrationBridgeTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000c461';
    private const string GROUP  = '01960000-0000-7000-8000-00000000c4a1';
    private const string MEMBER = '01960000-0000-7000-8000-00000000c462';

    /** @var list<object> */
    private array $published = [];

    /** @var list<string|null> */
    private array $groupsRead = [];

    public function testEveryBridgedClinicEventAnnouncesTheClinic(): void
    {
        $bridge = $this->bridge();

        $bridge->onCreated(new ClinicCreated(self::CLINIC, 'Clinique du Parc', 'parc', 'Europe/Paris', 'fr-FR', null));
        $bridge->onRenamed(new ClinicRenamed(self::CLINIC, 'Clinique du Lac'));
        $bridge->onOpeningHoursUpdated(new ClinicOpeningHoursUpdated(self::CLINIC, false));
        $bridge->onLetterheadUpdated(new ClinicLetterheadUpdated(self::CLINIC, false));
        $bridge->onTimeZoneChanged(new ClinicTimeZoneChanged(self::CLINIC, 'Indian/Reunion'));
        $bridge->onSuspended(new ClinicSuspended(self::CLINIC));
        $bridge->onActivated(new ClinicActivated(self::CLINIC));
        $bridge->onClosed(new ClinicClosed(self::CLINIC));

        self::assertCount(8, $this->published);
        foreach ($this->published as $event) {
            self::assertInstanceOf(ClinicProfileChangedIntegrationEvent::class, $event);
            self::assertSame(self::CLINIC, $event->aggregateId());
            self::assertSame(['clinicId' => self::CLINIC], $event->payload());
            self::assertSame('clinic.clinic-profile-changed-integration.event.v1', $event->name());
        }
        self::assertSame([], $this->groupsRead);
    }

    public function testAGroupSuspendedOrReactivatedAnnouncesEachOfItsClinics(): void
    {
        $bridge = $this->bridge([self::CLINIC, self::MEMBER]);

        $bridge->onGroupSuspended(new ClinicGroupSuspended(self::GROUP));
        $bridge->onGroupActivated(new ClinicGroupActivated(self::GROUP));

        self::assertSame([self::GROUP, self::GROUP], $this->groupsRead);
        self::assertSame(
            [self::CLINIC, self::MEMBER, self::CLINIC, self::MEMBER],
            array_map(static fn (object $event): string => $event instanceof ClinicProfileChangedIntegrationEvent ? $event->clinicId : '', $this->published),
        );
    }

    public function testAnEmptyGroupAnnouncesNothing(): void
    {
        $this->bridge()->onGroupSuspended(new ClinicGroupSuspended(self::GROUP));

        self::assertSame([], $this->published);
    }

    /**
     * @param list<string> $members
     */
    private function bridge(array $members = []): ClinicProfileChangedIntegrationBridge
    {
        $bus = $this->createStub(IntegrationEventBusInterface::class);
        $bus->method('publish')->willReturnCallback(function (array $stamps, object ...$events): void {
            $this->published = [...$this->published, ...array_values($events)];
        });

        $clinics = $this->createStub(ClinicReadRepositoryInterface::class);
        $clinics->method('findAllFiltered')->willReturnCallback(function (?ClinicStatus $status = null, ?string $clinicGroupId = null) use ($members): ClinicCollection {
            $this->groupsRead[] = $clinicGroupId;
            $dtos               = array_map(static fn (string $id): ClinicDto => new ClinicDto($id, 'Clinique', 'slug-' . $id, 'Europe/Paris', 'fr-FR', 'FR', null, 'EUR', ClinicStatus::SUSPENDED, self::GROUP, 'c', 'u'), $members);

            return new ClinicCollection($dtos, \count($dtos));
        });

        return new ClinicProfileChangedIntegrationBridge(new IntegrationEventPublisher($bus), $clinics);
    }
}
