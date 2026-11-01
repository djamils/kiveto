<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Query\ListBookingMotifs;

use App\Context\Scheduling\Application\Query\ListBookingMotifs\BookingMotifView;
use App\Context\Scheduling\Application\Query\ListBookingMotifs\ListBookingMotifs;
use App\Context\Scheduling\Application\Query\ListBookingMotifs\ListBookingMotifsHandler;
use App\Context\Scheduling\Domain\Booking\BookingMotif;
use App\Context\Scheduling\Domain\Booking\BookingMotifId;
use App\Context\Scheduling\Domain\Booking\BookingPreventiveCareKind;
use App\Context\Scheduling\Domain\Booking\ConfirmationMode;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryBookingMotifs;
use PHPUnit\Framework\TestCase;

final class ListBookingMotifsHandlerTest extends TestCase
{
    private const string CLINIC   = '01960000-0000-7000-8000-00000000c001';
    private const string VACCIN   = '01960000-0000-7000-8000-00000000b001';
    private const string CONSULT  = '01960000-0000-7000-8000-00000000b002';
    private const string ARCHIVED = '01960000-0000-7000-8000-00000000b003';
    private const string OFFLINE  = '01960000-0000-7000-8000-00000000b004';
    private const string VET      = '01960000-0000-7000-8000-00000000d001';

    public function testListsTheActiveMotifsAsViews(): void
    {
        $views = new ListBookingMotifsHandler($this->motifs())(new ListBookingMotifs(self::CLINIC));

        self::assertSame(array_map(get_object_vars(...), [
            new BookingMotifView(self::CONSULT, 'Consultation', null, 30, true, 'manual', ['consultation', 'health_check'], [self::VET], null, true, 0, null),
            new BookingMotifView(self::VACCIN, 'Vaccin', 'Rappel', 15, true, 'auto', ['consultation'], [self::VET], 'vaccination', false, 1, null),
        ]), array_map(get_object_vars(...), $views));
    }

    public function testIncludesArchivedMotifsOnRequest(): void
    {
        $views = new ListBookingMotifsHandler($this->motifs())(new ListBookingMotifs(self::CLINIC, includeArchived: true));

        self::assertSame([self::CONSULT, self::VACCIN, self::ARCHIVED], array_map(static fn (BookingMotifView $view): string => $view->id, $views));
        self::assertSame('2026-10-01 08:00:00', $views[2]->archivedAt?->format('Y-m-d H:i:s'));
    }

    public function testListsOnlyTheMotifsOfferedOnlineToNewClientsOnRequest(): void
    {
        $motifs = $this->motifs();
        // Open to new clients but not offered online
        $motifs->save($this->motif(self::OFFLINE, 'Bilan', null, 30, ConfirmationMode::AUTO, [PlanningBlockType::CONSULTATION], null, true, 2, online: false));

        self::assertCount(4, new ListBookingMotifsHandler($motifs)(new ListBookingMotifs(self::CLINIC, includeArchived: true)));

        $views = new ListBookingMotifsHandler($motifs)(new ListBookingMotifs(self::CLINIC, newClientsOnly: true));

        self::assertSame([self::CONSULT], array_map(static fn (BookingMotifView $view): string => $view->id, $views));
    }

    private function motifs(): InMemoryBookingMotifs
    {
        $motifs = new InMemoryBookingMotifs();
        $motifs->save($this->motif(self::VACCIN, 'Vaccin', 'Rappel', 15, ConfirmationMode::AUTO, [PlanningBlockType::CONSULTATION], BookingPreventiveCareKind::VACCINATION, false, 1));
        $motifs->save($this->motif(self::CONSULT, 'Consultation', null, 30, ConfirmationMode::MANUAL, [PlanningBlockType::CONSULTATION, PlanningBlockType::HEALTH_CHECK], null, true, 0));
        $archived = $this->motif(self::ARCHIVED, 'Ancien', null, 20, ConfirmationMode::AUTO, [PlanningBlockType::SURGERY], null, false, 5);
        $archived->archive(new \DateTimeImmutable('2026-10-01 08:00:00'));
        $motifs->save($archived);

        return $motifs;
    }

    /**
     * @param list<PlanningBlockType> $types
     */
    private function motif(
        string $id,
        string $label,
        ?string $description,
        int $duration,
        ConfirmationMode $confirmation,
        array $types,
        ?BookingPreventiveCareKind $kind,
        bool $newClients,
        int $sortOrder,
        bool $online = true,
    ): BookingMotif {
        return BookingMotif::create(
            BookingMotifId::fromString($id),
            ClinicId::fromString(self::CLINIC),
            $label,
            $description,
            $duration,
            $online,
            $confirmation,
            $types,
            [UserId::fromString(self::VET)],
            $kind,
            $newClients,
            $sortOrder,
        );
    }
}
