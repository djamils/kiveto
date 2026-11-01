<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Query\Pets;

use App\Context\ClientPortal\Application\Port\CareBooking;
use App\Context\ClientPortal\Application\Query\ListOwnerCareItems\ListOwnerCareItems;
use App\Context\ClientPortal\Application\Query\ListOwnerCareItems\ListOwnerCareItemsHandler;
use App\Context\ClientPortal\Application\Query\ListOwnerCareItems\OwnerCareItem;
use App\Context\ClientPortal\Domain\Exception\PetNotFoundException;
use App\Tests\Unit\Context\ClientPortal\Support\PetWorld as W;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ListOwnerCareItemsHandlerTest extends TestCase
{
    public function testTheLatestActOfEachProtocolWithADueDateIsListedNearestFirst(): void
    {
        $world = new W();
        $world->pet();
        $world->pet(W::PET2, 'Rex');
        $world->act(W::PET, 'Rage', '2025-11-10', '2026-11-10');
        $world->act(W::PET, 'Typhus', '2025-10-01', '2026-10-01');
        $world->act(W::PET, 'Leucose', '2026-10-20', null);
        $world->act(W::PET, 'Milbemax', '2026-09-01', '2027-03-01', 'internal_parasite');
        $world->act(W::PET2, 'Rage', '2026-01-01', '2026-10-15');

        $items = $this->handler($world)(new ListOwnerCareItems(W::USER, W::CLINIC, W::PET));

        self::assertSame([
            ['vaccination', 'Typhus', '2025-10-01', '2026-10-01', 'overdue'],
            ['vaccination', 'Rage', '2025-11-10', '2026-11-10', 'due_soon'],
            ['internal_parasite', 'Milbemax', '2026-09-01', '2027-03-01', 'up_to_date'],
        ], array_map(static fn (OwnerCareItem $i): array => [$i->kind, $i->label, $i->lastDoneOn->format('Y-m-d'), $i->dueOn->format('Y-m-d'), $i->status->value], $items));
        self::assertContains('latestActs:' . W::CLINIC . ':' . W::PET, $world->fakes->calls);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideTodayIsTheClinicLocalDateCases(): iterable
    {
        // 2026-10-29 14:00 UTC is already 30 October in Noumea: due on the 29th is overdue there.
        yield 'Noumea' => ['Pacific/Noumea', '2026-10-29 14:00:00', 'overdue'];
        yield 'Paris, same instant' => ['Europe/Paris', '2026-10-29 14:00:00', 'due_soon'];
        // 2026-10-30 02:00 UTC is still 29 October in Martinique.
        yield 'Martinique' => ['America/Martinique', '2026-10-30 02:00:00', 'due_soon'];
        yield 'Paris, next day' => ['Europe/Paris', '2026-10-30 02:00:00', 'overdue'];
    }

    #[DataProvider('provideTodayIsTheClinicLocalDateCases')]
    public function testTodayIsTheClinicLocalDate(string $timeZone, string $nowUtc, string $status): void
    {
        $world = new W($timeZone);
        $world->now($nowUtc);
        $world->pet();
        $world->act(W::PET, 'Rage', '2025-10-29', '2026-10-29');

        $items = $this->handler($world)(new ListOwnerCareItems(W::USER, W::CLINIC, W::PET));

        self::assertSame([$status], array_map(static fn (OwnerCareItem $i): string => $i->status->value, $items));
    }

    /**
     * AC 155: an appointment held for a motif of the item's kind books it;
     * another kind, another pet or no motif (never a care booking) do not.
     */
    public function testAnAppointmentOfTheSameKindBooksTheItem(): void
    {
        $world = new W();
        $world->pet();
        $world->pet(W::PET2, 'Rex');
        $world->act(W::PET, 'Rage', '2025-11-08', '2026-11-08');
        $world->act(W::PET, 'Typhus', '2025-10-01', '2026-10-01');
        $world->act(W::PET, 'Milbemax', '2026-09-01', '2026-11-08', 'internal_parasite');
        $world->act(W::PET, 'Frontline', '2026-09-01', '2026-11-08', 'external_parasite');
        $world->fakes->careBookings = [
            new CareBooking(W::PET, new \DateTimeImmutable('2026-11-03 09:00:00', new \DateTimeZone('UTC')), 'vaccination'),
            new CareBooking(W::PET, new \DateTimeImmutable('2026-11-03 09:00:00', new \DateTimeZone('UTC')), 'other'),
            new CareBooking(W::PET2, new \DateTimeImmutable('2026-11-03 09:00:00', new \DateTimeZone('UTC')), 'internal_parasite'),
        ];

        $items = $this->handler($world)(new ListOwnerCareItems(W::USER, W::CLINIC, W::PET));

        self::assertSame(
            [['Typhus', 'booked'], ['Frontline', 'due_soon'], ['Milbemax', 'due_soon'], ['Rage', 'booked']],
            array_map(static fn (OwnerCareItem $i): array => [$i->label, $i->status->value], $items),
        );
        // From the clinic-local midnight of today (Paris, UTC+1 after the switch).
        self::assertContains('careBookings:' . W::CLINIC . ':' . W::PET . ':2026-10-29T00:00:00+01:00', $world->fakes->calls);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideTheBookingDayIsTheClinicLocalDateCases(): iterable
    {
        // Today is 30 October in Noumea (UTC+11), the item due that day: booked until 29 November, local.
        yield 'last local day' => ['2026-11-29 12:30:00', 'booked'];
        yield 'next local day, same UTC day' => ['2026-11-29 13:30:00', 'due_soon'];
    }

    #[DataProvider('provideTheBookingDayIsTheClinicLocalDateCases')]
    public function testTheBookingDayIsTheClinicLocalDate(string $startsAtUtc, string $status): void
    {
        $world = new W('Pacific/Noumea');
        $world->now('2026-10-29 14:00:00');
        $world->pet();
        $world->act(W::PET, 'Rage', '2025-10-29', '2026-10-30');
        $world->fakes->careBookings = [new CareBooking(W::PET, new \DateTimeImmutable($startsAtUtc, new \DateTimeZone('UTC')), 'vaccination')];

        $items = $this->handler($world)(new ListOwnerCareItems(W::USER, W::CLINIC, W::PET));

        self::assertSame([$status], array_map(static fn (OwnerCareItem $i): string => $i->status->value, $items));
    }

    public function testADeceasedOrMissingPetHasNoCareItem(): void
    {
        $world = new W();
        $world->pet(W::PET, 'Moka', 'deceased');
        $world->pet(W::PET2, 'Rex', 'missing');
        $world->act(W::PET, 'Rage', '2025-11-10', '2026-11-10');
        $world->act(W::PET2, 'Rage', '2025-11-10', '2026-11-10');

        self::assertSame([], $this->handler($world)(new ListOwnerCareItems(W::USER, W::CLINIC, W::PET)));
        self::assertSame([], $this->handler($world)(new ListOwnerCareItems(W::USER, W::CLINIC, W::PET2)));
        self::assertSame(['visiblePets:' . W::CLINIC . ':' . W::CLIENT, 'visiblePets:' . W::CLINIC . ':' . W::CLIENT], $world->fakes->calls);
    }

    public function testAPetNotVisibleToTheAccountIsNotFound(): void
    {
        $world = new W();
        $world->pet(W::PET2, 'Rex', 'alive', W::CLIENT2);

        $this->expectException(PetNotFoundException::class);
        $this->handler($world)(new ListOwnerCareItems(W::USER, W::CLINIC, W::PET2));
    }

    private function handler(W $world): ListOwnerCareItemsHandler
    {
        return new ListOwnerCareItemsHandler($world->ownerPets(), $world->careItems());
    }
}
