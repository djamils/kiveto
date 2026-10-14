<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Animal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Context\Animal\Domain\Animal;
use App\Context\Animal\Domain\Repository\AnimalRepositoryInterface;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\BodyDisposal;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Domain\ValueObject\DeathCause;
use App\Context\Animal\Domain\ValueObject\Identification;
use App\Context\Animal\Domain\ValueObject\Insurance;
use App\Context\Animal\Domain\ValueObject\LifeCycle;
use App\Context\Animal\Domain\ValueObject\LifeStatus;
use App\Context\Animal\Domain\ValueObject\ReproductiveStatus;
use App\Context\Animal\Domain\ValueObject\Sex;
use App\Context\Animal\Domain\ValueObject\Species;
use App\Context\Animal\Domain\ValueObject\Transfer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * What the animal sheet added to the animal: its health insurance, and the
 * circumstances of a death with the disposal of the body.
 */
final class DoctrineAnimalSheetFieldsTest extends KernelTestCase
{
    public function testInsuranceAndDeathDetailsSurviveTheRoundTrip(): void
    {
        $clinicId = ClinicId::fromString(Uuid::v7()->toString());
        $animalId = AnimalId::fromString(Uuid::v7()->toString());
        $animal   = $this->animal($clinicId, $animalId);

        $animal->updateInsurance(new Insurance('Santévet', 'SV-2210-4471'), new \DateTimeImmutable('2026-01-02'));
        $animal->updateLifeCycle(
            new LifeCycle(LifeStatus::DECEASED, new \DateTimeImmutable('2026-04-18'), null, DeathCause::EUTHANASIA, BodyDisposal::COLLECTIVE_CREMATION),
            new \DateTimeImmutable('2026-04-18'),
        );
        $this->repository()->save($animal);
        $this->clear();

        $reloaded = $this->repository()->get($clinicId, $animalId);

        $insurance = $reloaded->insurance();
        self::assertNotNull($insurance);
        self::assertSame('Santévet', $insurance->insurerName);
        self::assertSame('SV-2210-4471', $insurance->policyNumber);
        self::assertSame(DeathCause::EUTHANASIA, $reloaded->lifeCycle()->deathCause);
        self::assertSame(BodyDisposal::COLLECTIVE_CREMATION, $reloaded->lifeCycle()->bodyDisposal);

        $view = $this->readRepository()->findById($clinicId, $animalId);
        \assert(null !== $view);

        self::assertSame('Santévet', $view->insurerName);
        self::assertSame('SV-2210-4471', $view->insurancePolicyNumber);
        self::assertSame('euthanasia', $view->lifeCycle->deathCause);
        self::assertSame('collective_cremation', $view->lifeCycle->bodyDisposal);
    }

    public function testAnUninsuredLivingAnimalHasNone(): void
    {
        $clinicId = ClinicId::fromString(Uuid::v7()->toString());
        $animalId = AnimalId::fromString(Uuid::v7()->toString());

        $this->repository()->save($this->animal($clinicId, $animalId));
        $this->clear();

        self::assertNull($this->repository()->get($clinicId, $animalId)->insurance());

        $view = $this->readRepository()->findById($clinicId, $animalId);
        \assert(null !== $view);

        self::assertNull($view->insurerName);
        self::assertNull($view->insurancePolicyNumber);
        self::assertNull($view->lifeCycle->deathCause);
        self::assertNull($view->lifeCycle->bodyDisposal);
    }

    private function animal(ClinicId $clinicId, AnimalId $animalId): Animal
    {
        return Animal::create(
            id: $animalId,
            clinicId: $clinicId,
            name: 'Minette',
            species: Species::CAT,
            sex: Sex::FEMALE,
            reproductiveStatus: ReproductiveStatus::NEUTERED,
            isMixedBreed: false,
            breedName: 'Maine coon',
            birthDate: new \DateTimeImmutable('2017-03-14'),
            color: 'Tigré brun',
            photoUrl: null,
            identification: Identification::createEmpty(),
            lifeCycle: LifeCycle::alive(),
            transfer: Transfer::none(),
            auxiliaryContact: null,
            primaryOwnerClientId: Uuid::v7()->toString(),
            secondaryOwnerClientIds: [],
            now: new \DateTimeImmutable('2026-01-01 10:00:00'),
        );
    }

    private function repository(): AnimalRepositoryInterface
    {
        $repository = self::getContainer()->get(AnimalRepositoryInterface::class);
        \assert($repository instanceof AnimalRepositoryInterface);

        return $repository;
    }

    private function readRepository(): AnimalReadRepositoryInterface
    {
        $repository = self::getContainer()->get(AnimalReadRepositoryInterface::class);
        \assert($repository instanceof AnimalReadRepositoryInterface);

        return $repository;
    }

    private function clear(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $entityManager->clear();
    }
}
