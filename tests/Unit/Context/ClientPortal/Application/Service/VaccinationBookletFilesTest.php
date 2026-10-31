<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Application\Port\PetIdentity;
use App\Context\ClientPortal\Application\Port\VaccinationRecord;
use App\Context\ClientPortal\Application\Service\VaccinationBookletFiles;
use App\Context\ClientPortal\Domain\Exception\PetNotFoundException;
use App\Shared\Application\Document\DocumentClinic;
use App\Tests\Unit\Context\ClientPortal\Support\PetWorld as W;
use App\Tests\Unit\System\Notification\Support\InMemoryFiles;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VaccinationBookletFilesTest extends TestCase
{
    private const string RECORD = '01960000-0000-7000-8000-0000000c0001';

    private W $world;
    private InMemoryFiles $storage;

    protected function setUp(): void
    {
        $this->world                                       = new W();
        $this->storage                                     = new InMemoryFiles();
        $this->world->fakes->identities[W::PET]            = self::identity();
        $this->world->fakes->vaccinations[W::PET]          = [self::rabies()];
        $this->world->documents->fakes->headers[W::CLINIC] = new DocumentClinic('Clinique des Lilas', null, '#4338ca', 'clinic/logo.png');
    }

    public function testTheFirstCallRendersStoresAndRecordsTheBooklet(): void
    {
        $key = $this->files()->ensure(W::CLINIC, W::PET);

        self::assertMatchesRegularExpression('#^client_portal/booklets/' . W::PET . '-[0-9a-f]{64}\.pdf$#', $key);
        self::assertSame([$key => '%PDF booklet 1'], $this->storage->files);
        self::assertSame([$key], array_values($this->world->fakes->booklets));
        self::assertSame(['saveCurrent:' . W::CLINIC . ':' . W::PET . ':' . $key . ':2026-10-29T09:00:00+00:00'], $this->world->fakes->calls);
        self::assertSame([], $this->world->documents->sources->jobs, 'no previous booklet to delete');

        $booklet = $this->world->fakes->rendered[0];
        self::assertSame(['Clinique des Lilas', 'Moka', [self::RECORD]], [
            $booklet->clinic?->name,
            $booklet->pet->name,
            array_map(static fn (VaccinationRecord $r): string => $r->id, $booklet->vaccinations),
        ]);
    }

    public function testUnchangedInputsKeepTheSameKeyWithoutRendering(): void
    {
        $first  = $this->files()->ensure(W::CLINIC, W::PET);
        $second = $this->files()->ensure(W::CLINIC, W::PET);

        self::assertSame($first, $second);
        self::assertCount(1, $this->world->fakes->rendered);
        self::assertSame([], $this->world->documents->sources->jobs);
    }

    /**
     * @return iterable<string, array{\Closure(W): void}>
     */
    public static function provideAChangedInputRendersANewBookletAndDeletesThePreviousCases(): iterable
    {
        yield 'a new vaccination' => [static function (W $world): void {
            $world->fakes->vaccinations[W::PET][] = new VaccinationRecord('01960000-0000-7000-8000-0000000c0002', 'Typhus', new \DateTimeImmutable('2026-10-01'), null, null);
        }];
        yield 'a renamed animal' => [static function (W $world): void {
            $world->fakes->identities[W::PET] = self::identity(name: 'Mokka');
        }];
        yield 'a new chip' => [static function (W $world): void {
            $world->fakes->identities[W::PET] = self::identity(chip: '250269800000001');
        }];
        yield 'a new due date' => [static function (W $world): void {
            $world->fakes->vaccinations[W::PET] = [self::rabies('2027-11-10')];
        }];
        yield 'a new clinic header' => [static function (W $world): void {
            $world->documents->fakes->headers[W::CLINIC] = new DocumentClinic('Clinique des Lilas', null, '#4338ca', 'clinic/logo-2.png');
        }];
    }

    /**
     * @param \Closure(W): void $change
     */
    #[DataProvider('provideAChangedInputRendersANewBookletAndDeletesThePreviousCases')]
    public function testAChangedInputRendersANewBookletAndDeletesThePrevious(\Closure $change): void
    {
        $previous = $this->files()->ensure(W::CLINIC, W::PET);
        $change($this->world);

        $key = $this->files()->ensure(W::CLINIC, W::PET);

        self::assertNotSame($previous, $key);
        self::assertSame($key, $this->world->fakes->booklets[W::PET]);
        self::assertCount(2, $this->world->fakes->rendered);
        self::assertSame(['delete:' . $previous], $this->world->documents->sources->jobs);
    }

    public function testATemplateVersionBumpRendersANewBooklet(): void
    {
        $previous = $this->files()->ensure(W::CLINIC, W::PET);

        $key = $this->files(VaccinationBookletFiles::TEMPLATE_VERSION + 1)->ensure(W::CLINIC, W::PET);

        self::assertNotSame($previous, $key);
        self::assertSame(['delete:' . $previous], $this->world->documents->sources->jobs);
    }

    public function testAnAnimalWithoutVaccinationStillGetsABooklet(): void
    {
        $this->world->fakes->vaccinations       = [];
        $this->world->documents->fakes->headers = [];

        $key = $this->files()->ensure(W::CLINIC, W::PET);

        self::assertArrayHasKey($key, $this->storage->files);
        self::assertSame([], $this->world->fakes->rendered[0]->vaccinations);
        self::assertNull($this->world->fakes->rendered[0]->clinic);
    }

    public function testAnUnknownAnimalIsNotFound(): void
    {
        $this->expectException(PetNotFoundException::class);
        $this->files()->ensure(W::CLINIC, W::PET2);
    }

    private function files(int $templateVersion = VaccinationBookletFiles::TEMPLATE_VERSION): VaccinationBookletFiles
    {
        $fakes = $this->world->fakes;

        return new VaccinationBookletFiles(
            $fakes,
            $fakes,
            $this->world->documents->fakes,
            $fakes,
            $this->storage,
            $fakes,
            $this->world->documents->sources,
            $this->world->documents->clock,
            $templateVersion,
        );
    }

    private static function identity(string $name = 'Moka', ?string $chip = '250269812345678'): PetIdentity
    {
        return new PetIdentity($name, 'cat', null, true, 'female', new \DateTimeImmutable('2020-05-01'), $chip);
    }

    private static function rabies(string $nextDueOn = '2026-11-10'): VaccinationRecord
    {
        return new VaccinationRecord(self::RECORD, 'Rage', new \DateTimeImmutable('2025-11-10'), 'LOT-42', new \DateTimeImmutable($nextDueOn));
    }
}
