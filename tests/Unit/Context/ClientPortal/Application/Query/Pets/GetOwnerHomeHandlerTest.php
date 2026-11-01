<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Query\Pets;

use App\Context\ClientPortal\Application\Port\OwnerAppointment;
use App\Context\ClientPortal\Application\Port\UpcomingAppointment;
use App\Context\ClientPortal\Application\Query\GetOwnerHome\GetOwnerHome;
use App\Context\ClientPortal\Application\Query\GetOwnerHome\GetOwnerHomeHandler;
use App\Context\ClientPortal\Application\Query\GetOwnerHome\HomeCareItem;
use App\Context\ClientPortal\Application\Query\ListOwnerPets\OwnerPet;
use App\Context\ClientPortal\Application\Query\ListPublishedDocuments\ListPublishedDocumentsHandler;
use App\Context\ClientPortal\Application\Query\ListPublishedDocuments\PortalDocument;
use App\Context\ClientPortal\Application\Service\PortalAppointments;
use App\Context\ClientPortal\Application\Service\RenewalStatuses;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryQuestionnaires;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryRenewalRequests;
use App\Tests\Unit\Context\ClientPortal\Support\PetWorld as W;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\BufferingLogger;

final class GetOwnerHomeHandlerTest extends TestCase
{
    private const string APPOINTMENT = '01960000-0000-7000-8000-0000000b0001';
    private const string VET         = '01960000-0000-7000-8000-0000000d0001';

    public function testTheHomeShowsTheAlivePetsTheirNearestCareItemsTheNextAppointmentAndTheLatestDocuments(): void
    {
        $world = new W('Pacific/Noumea');
        $world->pet(W::PET, 'Moka');
        $world->pet(W::PET2, 'Rex');
        $world->pet(W::PET3, 'Nala', 'deceased');
        $world->act(W::PET, 'Rage', '2025-11-10', '2026-11-10');
        $world->act(W::PET, 'Typhus', '2025-10-01', '2026-10-01');
        $world->act(W::PET2, 'Rage', '2025-12-01', '2026-12-01');
        $world->act(W::PET2, 'Leptospirose', '2025-10-20', '2026-10-20');
        $world->act(W::PET3, 'Rage', '2025-01-01', '2026-01-01');
        $world->fakes->appointment   = self::upcoming(W::PET2);
        $world->fakes->appointments  = [self::appointment(W::PET2)];
        $world->fakes->practitioners = [self::VET => 'Dr Claire Lambert'];
        $world->model->rows          = [
            W::report('01960000-0000-7000-8000-0000000f0001', '01960000-0000-7000-8000-0000000e0001', '2026-10-20 10:00:00'),
            W::report('01960000-0000-7000-8000-0000000f0002', '01960000-0000-7000-8000-0000000e0002', '2026-10-10 10:00:00'),
            W::report('01960000-0000-7000-8000-0000000f0003', '01960000-0000-7000-8000-0000000e0003', '2026-10-01 10:00:00'),
        ];

        $home = $this->handler($world)(new GetOwnerHome(W::USER, W::CLINIC));

        self::assertSame(['Moka', 'Rex'], array_map(static fn (OwnerPet $p): string => $p->name, $home->pets));
        self::assertSame(
            [[W::PET, 'Moka', 'Typhus', 'overdue'], [W::PET2, 'Rex', 'Leptospirose', 'overdue'], [W::PET, 'Moka', 'Rage', 'due_soon']],
            array_map(static fn (HomeCareItem $i): array => [$i->petId, $i->petName, $i->care->label, $i->care->status->value], $home->careItems),
        );
        self::assertSame(
            ['01960000-0000-7000-8000-0000000f0001', '01960000-0000-7000-8000-0000000f0002'],
            array_map(static fn (PortalDocument $d): string => $d->id, $home->latestDocuments),
        );
        self::assertSame([[W::USER, W::CLINIC, null, null, null, 2]], $world->model->listings);

        $next = $home->nextAppointment;
        self::assertNotNull($next);
        self::assertSame(
            [self::APPOINTMENT, '2026-11-05T09:30:00+11:00', '2026-11-05T10:00:00+11:00', W::PET2, 'Rex', 'Rappel vaccin', 'Dr Claire Lambert', 'confirmed'],
            [$next->id, $next->startAt->format('c'), $next->endAt->format('c'), $next->petId, $next->petName, $next->motifLabel, $next->practitionerName, $next->status],
        );
        self::assertContains('nextFor:' . W::CLINIC . ':' . W::PET . ',' . W::PET2 . ':2026-10-29T09:00:00+00:00', $world->fakes->calls);
        self::assertContains('namesOf:' . W::CLINIC . ':' . self::VET, $world->fakes->calls);
    }

    public function testAnEmptyHomeHasEmptySectionsAndNoAppointment(): void
    {
        $world = new W();

        $home = $this->handler($world)(new GetOwnerHome(W::USER, W::CLINIC));

        self::assertNull($home->nextAppointment);
        self::assertSame([[], [], []], [$home->pets, $home->careItems, $home->latestDocuments]);
        self::assertContains('nextFor:' . W::CLINIC . '::2026-10-29T09:00:00+00:00', $world->fakes->calls);
    }

    public function testAnAppointmentOfAPetThatIsNotAliveAndVisibleIsNotShown(): void
    {
        $world = new W();
        $world->pet(W::PET, 'Moka');
        $world->fakes->appointment  = self::upcoming(W::PET3);
        $world->fakes->appointments = [self::appointment(W::PET3)];

        self::assertNull($this->handler($world)(new GetOwnerHome(W::USER, W::CLINIC))->nextAppointment);
    }

    public function testAnAppointmentGoneMeanwhileIsNotShown(): void
    {
        $world = new W();
        $world->pet(W::PET, 'Moka');
        $world->fakes->appointment = self::upcoming(W::PET);

        self::assertNull($this->handler($world)(new GetOwnerHome(W::USER, W::CLINIC))->nextAppointment);
    }

    public function testAnUnknownPractitionerLeavesTheNameOut(): void
    {
        $world = new W();
        $world->pet(W::PET, 'Moka');
        $world->fakes->appointment  = self::upcoming(W::PET);
        $world->fakes->appointments = [self::appointment(W::PET)];

        $next = $this->handler($world)(new GetOwnerHome(W::USER, W::CLINIC))->nextAppointment;

        self::assertNotNull($next);
        self::assertNull($next->practitionerName);
    }

    private static function upcoming(string $petId): UpcomingAppointment
    {
        return new UpcomingAppointment(self::APPOINTMENT, $petId, new \DateTimeImmutable('2026-11-04 22:30:00', new \DateTimeZone('UTC')), self::VET, 'vaccin');
    }

    private static function appointment(string $petId): OwnerAppointment
    {
        $utc = new \DateTimeZone('UTC');

        return new OwnerAppointment(
            self::APPOINTMENT,
            W::CLIENT,
            $petId,
            self::VET,
            new \DateTimeImmutable('2026-11-04 22:30:00', $utc),
            30,
            'confirmed',
            true,
            false,
            'portal',
            '01960000-0000-7000-8000-0000000c0001',
            'Rappel vaccin',
            null,
            new \DateTimeImmutable('2026-10-01 08:00:00', $utc),
            null,
        );
    }

    private function handler(W $world): GetOwnerHomeHandler
    {
        $documents = $world->documents;

        return new GetOwnerHomeHandler(
            $world->ownerPets(),
            $world->careItems(),
            $world->fakes,
            new PortalAppointments($world->careItems(), $world->fakes, $documents->fakes, new InMemoryQuestionnaires()),
            $world->model,
            new ListPublishedDocumentsHandler($world->model, $documents->invoices, $documents->prescriptions, $documents->fakes, new RenewalStatuses(new InMemoryRenewalRequests()), new BufferingLogger()),
            $documents->clock,
        );
    }
}
