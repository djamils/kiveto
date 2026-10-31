<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Query\Pets;

use App\Context\ClientPortal\Application\Port\RecordedWeight;
use App\Context\ClientPortal\Application\Port\VisitWeight;
use App\Context\ClientPortal\Application\Query\GetOwnerPet\GetOwnerPet;
use App\Context\ClientPortal\Application\Query\GetOwnerPet\GetOwnerPetHandler;
use App\Context\ClientPortal\Application\Query\ListOwnerPets\ListOwnerPets;
use App\Context\ClientPortal\Application\Query\ListOwnerPets\ListOwnerPetsHandler;
use App\Context\ClientPortal\Application\Query\ListOwnerPets\OwnerPet;
use App\Context\ClientPortal\Domain\Exception\PetNotFoundException;
use App\Context\ClientPortal\Domain\Link\ClientLinkStatus;
use App\Tests\Unit\Context\ClientPortal\Support\PetWorld as W;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OwnerPetQueriesTest extends TestCase
{
    private const string OTHER_CLINIC = '01960000-0000-7000-8000-00000000c009';
    private const string CLIENT3      = '01960000-0000-7000-8000-0000000000c3';
    private const string CLIENT4      = '01960000-0000-7000-8000-0000000000c4';

    public function testThePetsOfEveryConfirmedClientOfTheClinicAreListed(): void
    {
        $world = new W();
        $world->link(W::CLIENT2);
        $world->link(self::CLIENT3, ClientLinkStatus::REVOKED);
        $world->link(self::CLIENT4, ClientLinkStatus::CONFIRMED, self::OTHER_CLINIC);
        $world->pet(W::PET, 'Moka');
        $world->pet(W::PET2, 'Rex', 'deceased', W::CLIENT2);
        $world->pet(W::PET3, 'Nala', 'alive', self::CLIENT3);

        $pets = new ListOwnerPetsHandler($world->ownerPets())(new ListOwnerPets(W::USER, W::CLINIC));

        self::assertSame([[W::PET, 'Moka', 'alive'], [W::PET2, 'Rex', 'deceased']], array_map(static fn (OwnerPet $p): array => [$p->id, $p->name, $p->lifeStatus], $pets));
        self::assertSame(['visiblePets:' . W::CLINIC . ':' . W::CLIENT . ',' . W::CLIENT2], $world->fakes->calls);

        $moka = $pets[0];
        self::assertSame(['cat', 'Chartreux', false, 'female', 'neutered', '2020-05-01'], [
            $moka->species, $moka->breedName, $moka->isMixedBreed, $moka->sex, $moka->reproductiveStatus, $moka->birthDate?->format('Y-m-d'),
        ]);
    }

    public function testAnAccountWithoutLinkToTheClinicSeesNoPet(): void
    {
        $world = new W();
        $world->pet();

        self::assertSame([], new ListOwnerPetsHandler($world->ownerPets())(new ListOwnerPets(W::USER, self::OTHER_CLINIC)));
        self::assertSame(['visiblePets:' . self::OTHER_CLINIC . ':'], $world->fakes->calls);
    }

    public function testAPetOutsideTheVisibleOnesIsNotFound(): void
    {
        $world = new W();
        $world->pet(W::PET2, 'Rex', 'alive', W::CLIENT2);

        self::assertNull($this->handler($world)(new GetOwnerPet(W::USER, W::CLINIC, W::PET2)));
        self::assertNull($this->handler($world)(new GetOwnerPet(W::USER, W::CLINIC, 'not-a-uuid')));

        $this->expectException(PetNotFoundException::class);
        $this->expectExceptionMessage(\sprintf('Pet "%s" is not visible to the owner account.', W::PET2));
        $world->ownerPets()->get(W::USER, W::CLINIC, W::PET2);
    }

    public function testTheDetailMasksTheChipAndNamesTheVetOfTheLatestSharedReport(): void
    {
        $world = new W();
        $world->pet(chip: ' 250269812345678 ');
        $world->model->rows = [W::report('01960000-0000-7000-8000-0000000f0001', '01960000-0000-7000-8000-0000000e0001', '2026-10-20 10:00:00')];

        $details = $this->handler($world)(new GetOwnerPet(W::USER, W::CLINIC, W::PET));

        self::assertNotNull($details);
        self::assertSame([W::PET, 'Moka'], [$details->pet->id, $details->pet->name]);
        self::assertSame('•••• 5678', $details->chipNumberMasked);
        self::assertSame('Dr Claire Lambert', $details->referentVet);
        self::assertNull($details->lastWeight);
        self::assertSame([[W::USER, W::CLINIC, 'report', W::PET, null, 1]], $world->model->listings);
    }

    public function testWithoutChipNorReportBothAreNull(): void
    {
        $world = new W();
        $world->pet(chip: '  ');

        $details = $this->handler($world)(new GetOwnerPet(W::USER, W::CLINIC, W::PET));

        self::assertNotNull($details);
        self::assertNull($details->chipNumberMasked);
        self::assertNull($details->referentVet);

        $world->model->rows = [W::report('01960000-0000-7000-8000-0000000f0001', '01960000-0000-7000-8000-0000000e0001', '2026-10-20 10:00:00', null)];
        self::assertNull($this->handler($world)(new GetOwnerPet(W::USER, W::CLINIC, W::PET))?->referentVet, 'a report shared without practitioner snapshot');
    }

    /**
     * @return iterable<string, array{?array{int, string}, ?array{int, string}, string, ?array{int, string}}>
     */
    public static function provideTheLatestWeightIsTheMostRecentOfBothSourcesCases(): iterable
    {
        yield 'no weight' => [null, null, 'Europe/Paris', null];
        yield 'a record only' => [[12_000, '2026-03-01'], null, 'Europe/Paris', [12_000, '2026-03-01']];
        yield 'a closed visit only, dated in the clinic' => [null, [12_400, '2026-03-10 09:00:00'], 'Europe/Paris', [12_400, '2026-03-10']];
        yield 'the visit is more recent' => [[12_000, '2026-03-01'], [12_400, '2026-03-10 09:00:00'], 'Europe/Paris', [12_400, '2026-03-10']];
        yield 'the record is more recent' => [[12_000, '2026-03-12'], [12_400, '2026-03-10 09:00:00'], 'Europe/Paris', [12_000, '2026-03-12']];
        yield 'same day: the record wins' => [[12_000, '2026-03-10'], [12_400, '2026-03-10 09:00:00'], 'Europe/Paris', [12_000, '2026-03-10']];
        yield 'Noumea: the visit is already the next day' => [[12_000, '2026-03-10'], [12_400, '2026-03-10 14:00:00'], 'Pacific/Noumea', [12_400, '2026-03-11']];
        yield 'Martinique: the visit is still the same day' => [[12_000, '2026-03-10'], [12_400, '2026-03-11 02:00:00'], 'America/Martinique', [12_000, '2026-03-10']];
    }

    /**
     * @param ?array{int, string} $record
     * @param ?array{int, string} $visit
     * @param ?array{int, string} $expected
     */
    #[DataProvider('provideTheLatestWeightIsTheMostRecentOfBothSourcesCases')]
    public function testTheLatestWeightIsTheMostRecentOfBothSources(?array $record, ?array $visit, string $timeZone, ?array $expected): void
    {
        $world = new W($timeZone);
        $world->pet();

        if (null !== $record) {
            $world->fakes->recordedWeights[W::PET] = new RecordedWeight($record[0], new \DateTimeImmutable($record[1]));
        }

        if (null !== $visit) {
            $world->fakes->visitWeights[W::PET] = new VisitWeight($visit[0], new \DateTimeImmutable($visit[1], new \DateTimeZone('UTC')));
        }

        $weight = $this->handler($world)(new GetOwnerPet(W::USER, W::CLINIC, W::PET))?->lastWeight;

        self::assertSame($expected, null === $weight ? null : [$weight->weightGrams, $weight->at->format('Y-m-d')]);
    }

    private function handler(W $world): GetOwnerPetHandler
    {
        return new GetOwnerPetHandler($world->ownerPets(), $world->fakes, $world->fakes, $world->model, $world->documents->fakes);
    }
}
