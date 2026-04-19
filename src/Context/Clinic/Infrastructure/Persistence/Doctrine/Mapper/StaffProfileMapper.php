<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Clinic\Domain\Staff\StaffProfile;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\DisplayName;
use App\Context\Clinic\Domain\Staff\ValueObject\HexColor;
use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalRegistrationNumber;
use App\Context\Clinic\Domain\Staff\ValueObject\SignatureImageKey;
use App\Context\Clinic\Domain\Staff\ValueObject\StaffProfileId;
use App\Context\Clinic\Domain\Staff\VeterinaryCredentials;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Entity\StaffProfileEntity;
use App\Shared\Domain\ValueObject\PhoneNumber;
use Symfony\Component\Uid\Uuid;

final class StaffProfileMapper
{
    public function toDomain(StaffProfileEntity $entity): StaffProfile
    {
        $credentials = null;

        if (null !== $entity->getRegistrationNumber() && null !== $entity->getProfessionalTitle()) {
            $signatureImageKey = null !== $entity->getSignatureImageKey()
                ? SignatureImageKey::fromString($entity->getSignatureImageKey())
                : null;

            $credentials = new VeterinaryCredentials(
                registrationNumber: ProfessionalRegistrationNumber::fromString($entity->getRegistrationNumber()),
                professionalTitle: $entity->getProfessionalTitle(),
                signatureImageKey: $signatureImageKey,
            );
        }

        return StaffProfile::reconstitute(
            id: StaffProfileId::fromString($entity->getId()->toRfc4122()),
            membershipId: ClinicMembershipId::fromString($entity->getMembershipId()->toRfc4122()),
            firstName: $entity->getFirstName(),
            lastName: $entity->getLastName(),
            displayName: DisplayName::fromString($entity->getDisplayName()),
            phoneNumber: null !== $entity->getPhoneNumber() ? PhoneNumber::fromString($entity->getPhoneNumber()) : null,
            agendaColor: HexColor::fromString($entity->getAgendaColor()),
            sortOrder: $entity->getSortOrder(),
            isVisibleInAgenda: $entity->getIsVisibleInAgenda(),
            credentials: $credentials,
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }

    public function toEntity(StaffProfile $profile, ?StaffProfileEntity $entity = null): StaffProfileEntity
    {
        $entity ??= new StaffProfileEntity();

        $entity->setId(Uuid::fromString($profile->id()->toString()));
        $entity->setMembershipId(Uuid::fromString($profile->membershipId()->toString()));
        $entity->setFirstName($profile->firstName());
        $entity->setLastName($profile->lastName());
        $entity->setDisplayName($profile->displayName()->toString());
        $entity->setPhoneNumber($profile->phoneNumber()?->toString());
        $entity->setAgendaColor($profile->agendaColor()->toString());
        $entity->setSortOrder($profile->sortOrder());
        $entity->setIsVisibleInAgenda($profile->isVisibleInAgenda());
        $entity->setCreatedAt($profile->createdAt());
        $entity->setUpdatedAt($profile->updatedAt());

        $credentials = $profile->credentials();
        $entity->setRegistrationNumber($credentials?->registrationNumber()->toString());
        $entity->setProfessionalTitle($credentials?->professionalTitle());
        $entity->setSignatureImageKey($credentials?->signatureImageKey()?->toString());

        return $entity;
    }
}
