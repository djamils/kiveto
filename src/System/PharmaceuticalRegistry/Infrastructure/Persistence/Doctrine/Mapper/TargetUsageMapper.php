<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Mapper;

use App\System\PharmaceuticalRegistry\Domain\Entity\TargetUsage;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\AdministrationRoute;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\FoodProductionPurpose;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\TargetSpeciesCode;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\WithdrawalPeriod;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\WithdrawalPeriodUnit;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\AuthorizationEntity;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\TargetUsageEntity;
use Symfony\Component\Uid\Uuid;

final readonly class TargetUsageMapper
{
    public function toDomain(TargetUsageEntity $entity): TargetUsage
    {
        $withdrawalPeriod = null;

        if (null !== $entity->getWithdrawalPeriodValue() && null !== $entity->getWithdrawalPeriodUnit()) {
            $unit             = WithdrawalPeriodUnit::from($entity->getWithdrawalPeriodUnit());
            $withdrawalPeriod = match ($unit) {
                WithdrawalPeriodUnit::DAY  => WithdrawalPeriod::days($entity->getWithdrawalPeriodValue()),
                WithdrawalPeriodUnit::HOUR => WithdrawalPeriod::hours($entity->getWithdrawalPeriodValue()),
            };
        }

        return TargetUsage::of(
            administrationRoute: AdministrationRoute::from($entity->getAdministrationRoute()),
            targetSpeciesCode: TargetSpeciesCode::fromString($entity->getTargetSpeciesCode()),
            foodProductionPurpose: null !== $entity->getFoodProductionPurpose()
                ? FoodProductionPurpose::from($entity->getFoodProductionPurpose())
                : null,
            withdrawalPeriod: $withdrawalPeriod,
            jurisdictionalNote: $entity->getJurisdictionalNote(),
        );
    }

    public function toEntity(TargetUsage $targetUsage, AuthorizationEntity $authorization): TargetUsageEntity
    {
        $entity = new TargetUsageEntity();
        $entity->setId(Uuid::v7());
        $entity->setAuthorization($authorization);
        $entity->setAdministrationRoute($targetUsage->administrationRoute()->value);
        $entity->setTargetSpeciesCode($targetUsage->targetSpeciesCode()->toString());
        $entity->setFoodProductionPurpose($targetUsage->foodProductionPurpose()?->value);
        $entity->setJurisdictionalNote($targetUsage->jurisdictionalNote());

        if (null !== $targetUsage->withdrawalPeriod()) {
            $entity->setWithdrawalPeriodValue($targetUsage->withdrawalPeriod()->value());
            $entity->setWithdrawalPeriodUnit($targetUsage->withdrawalPeriod()->unit()->value);
        } else {
            $entity->setWithdrawalPeriodValue(null);
            $entity->setWithdrawalPeriodUnit(null);
        }

        return $entity;
    }
}
