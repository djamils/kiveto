<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Query\Pets;

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
