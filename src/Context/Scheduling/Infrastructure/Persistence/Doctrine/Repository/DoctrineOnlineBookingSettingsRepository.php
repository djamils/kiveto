<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Scheduling\Domain\Booking\OnlineBookingSettings;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettingsRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity\OnlineBookingSettingsEntity;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Mapper\OnlineBookingSettingsMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineOnlineBookingSettingsRepository implements OnlineBookingSettingsRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OnlineBookingSettingsMapper $mapper,
    ) {
    }

    public function forClinic(ClinicId $clinicId): OnlineBookingSettings
    {
        $entity = $this->entityManager->find(OnlineBookingSettingsEntity::class, Uuid::fromString($clinicId->toString()));

        return null === $entity ? OnlineBookingSettings::defaults($clinicId) : $this->mapper->toDomain($entity);
    }

    public function save(OnlineBookingSettings $settings): void
    {
        $entity = $this->entityManager->find(OnlineBookingSettingsEntity::class, Uuid::fromString($settings->clinicId()->toString()));

        if (null === $entity) {
            $this->entityManager->persist($this->mapper->toEntity($settings));
        } else {
            $this->mapper->updateEntity($settings, $entity);
        }

        $this->entityManager->flush();
    }
}
