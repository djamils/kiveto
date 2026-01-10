<?php

declare(strict_types=1);

namespace App\Clinic\Infrastructure\Persistence\Doctrine\Mapper;

use App\Clinic\Domain\Clinic;
use App\Clinic\Domain\ValueObject\ClinicGroupId;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Clinic\Domain\ValueObject\ClinicSlug;
use App\Clinic\Domain\ValueObject\LocaleCode;
use App\Clinic\Domain\ValueObject\TimeZone;
use App\Clinic\Infrastructure\Persistence\Doctrine\Entity\ClinicEntity;
use Symfony\Component\Uid\Uuid;

final readonly class ClinicMapper
{
    public function toDomain(ClinicEntity $entity): Clinic
    {
        return Clinic::reconstitute(
            id: ClinicId::fromString($entity->getId()->toString()),
            name: $entity->getName(),
            slug: ClinicSlug::fromString($entity->getSlug()),
            timeZone: TimeZone::fromString($entity->getTimeZone()),
            locale: LocaleCode::fromString($entity->getLocale()),
            status: $entity->getStatus(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
            clinicGroupId: $entity->getClinicGroupId()
                ? ClinicGroupId::fromString($entity->getClinicGroupId()->toString())
                : null,
        );
    }

    public function toEntity(Clinic $clinic): ClinicEntity
    {
        $entity = new ClinicEntity();
        $entity->setId(Uuid::fromString($clinic->id()->toString()));
        $entity->setName($clinic->name());
        $entity->setSlug($clinic->slug()->toString());
        $entity->setTimeZone($clinic->timeZone()->toString());
        $entity->setLocale($clinic->locale()->toString());
        $entity->setStatus($clinic->status());
        $entity->setCreatedAt($clinic->createdAt());
        $entity->setUpdatedAt($clinic->updatedAt());

        if ($clinic->clinicGroupId()) {
            $entity->setClinicGroupId(Uuid::fromString($clinic->clinicGroupId()->toString()));
        }

        return $entity;
    }
}
