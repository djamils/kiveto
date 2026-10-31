<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Query\Pets;

use App\Context\ClientPortal\Application\Port\UpcomingAppointment;
use App\Context\ClientPortal\Application\Query\GetOwnerHome\GetOwnerHome;
use App\Context\ClientPortal\Application\Query\GetOwnerHome\GetOwnerHomeHandler;
use App\Context\ClientPortal\Application\Query\GetOwnerHome\HomeCareItem;
use App\Context\ClientPortal\Application\Query\ListOwnerPets\OwnerPet;
use App\Context\ClientPortal\Application\Query\ListPublishedDocuments\ListPublishedDocumentsHandler;
use App\Context\ClientPortal\Application\Query\ListPublishedDocuments\PortalDocument;
use App\Tests\Unit\Context\ClientPortal\Support\PetWorld as W;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $world->fakes->appointment   = new UpcomingAppointment(self::APPOINTMENT, W::PET2, new \DateTimeImmutable('2026-11-04 22:30:00', new \DateTimeZone('UTC')), self::VET, 'vaccin');
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
            [self::APPOINTMENT, '2026-11-05T09:30:00+11:00', W::PET2, 'Rex', 'Dr Claire Lambert', 'Vaccination'],
            [$next->id, $next->startAt->format('c'), $next->petId, $next->petName, $next->practitioner, $next->motifLabel],
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
        $world->fakes->appointment = new UpcomingAppointment(self::APPOINTMENT, W::PET3, new \DateTimeImmutable('2026-11-04 10:00:00'), self::VET, 'consult');

        self::assertNull($this->handler($world)(new GetOwnerHome(W::USER, W::CLINIC))->nextAppointment);
    }

    public function testAnUnknownPractitionerLeavesTheNameOut(): void
    {
        $world = new W();
        $world->pet(W::PET, 'Moka');
        $world->fakes->appointment = new UpcomingAppointment(self::APPOINTMENT, W::PET, new \DateTimeImmutable('2026-11-04 10:00:00'), self::VET, 'consult');

        $next = $this->handler($world)(new GetOwnerHome(W::USER, W::CLINIC))->nextAppointment;

        self::assertNotNull($next);
        self::assertSame([null, 'Consultation'], [$next->practitioner, $next->motifLabel]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideTheMotifLabelIsFixedPerCategoryCases(): iterable
    {
        yield 'vaccin' => ['vaccin', 'Vaccination'];
        yield 'suivi' => ['suivi', 'Contrôle'];
        yield 'bilan' => ['bilan', 'Bilan'];
        yield 'chir' => ['chir', 'Chirurgie'];
        yield 'urg' => ['urg', 'Urgence'];
        yield 'consult' => ['consult', 'Consultation'];
        yield 'anything else' => ['autre', 'Consultation'];
    }

    #[DataProvider('provideTheMotifLabelIsFixedPerCategoryCases')]
    public function testTheMotifLabelIsFixedPerCategory(string $category, string $label): void
    {
        self::assertSame($label, GetOwnerHomeHandler::motifLabel($category));
    }

    private function handler(W $world): GetOwnerHomeHandler
    {
        $documents = $world->documents;

        return new GetOwnerHomeHandler(
            $world->ownerPets(),
            $world->careItems(),
            $world->fakes,
            $world->fakes,
            $world->model,
            new ListPublishedDocumentsHandler($world->model, $documents->invoices, $documents->prescriptions, $documents->fakes, new BufferingLogger()),
            $documents->fakes,
            $documents->clock,
        );
    }
}
