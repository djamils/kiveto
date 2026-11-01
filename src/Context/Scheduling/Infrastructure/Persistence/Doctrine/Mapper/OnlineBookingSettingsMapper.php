<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Scheduling\Domain\Booking\OnlineBookingSettings;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity\OnlineBookingSettingsEntity;
use Symfony\Component\Uid\Uuid;

final readonly class OnlineBookingSettingsMapper
{
    public function toDomain(OnlineBookingSettingsEntity $entity): OnlineBookingSettings
    {
        return new OnlineBookingSettings(
            clinicId: ClinicId::fromString($entity->getClinicId()->toRfc4122()),
            enabled: $entity->isEnabled(),
            gridStepMinutes: $entity->getGridStepMinutes(),
            minLeadTimeMinutes: $entity->getMinLeadTimeMinutes(),
            minLeadTimeManualHours: $entity->getMinLeadTimeManualHours(),
            horizonDays: $entity->getHorizonDays(),
            cancellationWindowHours: $entity->getCancellationWindowHours(),
            newClientBookingEnabled: $entity->isNewClientBookingEnabled(),
        );
    }

    public function toEntity(OnlineBookingSettings $settings): OnlineBookingSettingsEntity
    {
        $entity = new OnlineBookingSettingsEntity(Uuid::fromString($settings->clinicId()->toString()));
        $this->updateEntity($settings, $entity);

        return $entity;
    }

    public function updateEntity(OnlineBookingSettings $settings, OnlineBookingSettingsEntity $entity): void
    {
        $entity->setEnabled($settings->isEnabled());
        $entity->setGridStepMinutes($settings->gridStepMinutes());
        $entity->setMinLeadTimeMinutes($settings->minLeadTimeMinutes());
        $entity->setMinLeadTimeManualHours($settings->minLeadTimeManualHours());
        $entity->setHorizonDays($settings->horizonDays());
        $entity->setCancellationWindowHours($settings->cancellationWindowHours());
        $entity->setNewClientBookingEnabled($settings->isNewClientBookingEnabled());
    }
}
