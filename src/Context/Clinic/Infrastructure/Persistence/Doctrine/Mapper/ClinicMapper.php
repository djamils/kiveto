<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Clinic\Domain\Clinic;
use App\Context\Clinic\Domain\ValueObject\ClinicGroupId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Domain\ValueObject\ClinicSlug;
use App\Context\Clinic\Domain\ValueObject\Letterhead;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Embeddable\LetterheadEmbeddable;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Entity\ClinicEntity;
use App\Shared\Domain\Localization\Locale;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\PostalAddress;
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
            locale: Locale::fromString($entity->getLocale()),
            countryCode: CountryCode::fromString($entity->getCountryCode()),
            currencyCode: CurrencyCode::fromString($entity->getCurrencyCode()),
            status: $entity->getStatus(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
            clinicGroupId: $entity->getClinicGroupId()
                ? ClinicGroupId::fromString($entity->getClinicGroupId()->toString())
                : null,
            jurisdictionCode: $entity->getJurisdictionCode(),
            letterhead: self::letterheadToDomain($entity->getLetterhead()),
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
        $entity->setCountryCode($clinic->countryCode()->toString());
        $entity->setJurisdictionCode($clinic->jurisdictionCode());
        $entity->setCurrencyCode($clinic->currencyCode()->toString());
        $entity->setStatus($clinic->status());
        $entity->setLetterhead(self::letterheadToEntity($clinic->letterhead()));
        $entity->setCreatedAt($clinic->createdAt());
        $entity->setUpdatedAt($clinic->updatedAt());

        if ($clinic->clinicGroupId()) {
            $entity->setClinicGroupId(Uuid::fromString($clinic->clinicGroupId()->toString()));
        }

        return $entity;
    }

    /**
     * A row written before the letterhead existed has every column null, which
     * reads back as an empty letterhead rather than as a half-filled address.
     */
    private static function letterheadToDomain(LetterheadEmbeddable $embeddable): Letterhead
    {
        $street  = $embeddable->streetLine1;
        $city    = $embeddable->city;
        $country = $embeddable->countryCode;

        $address = null !== $street && null !== $city && null !== $country
            ? PostalAddress::create(
                streetLine1: $street,
                city: $city,
                countryCode: $country,
                streetLine2: $embeddable->streetLine2,
                postalCode: $embeddable->postalCode,
            )
            : null;

        return Letterhead::create(
            legalName: $embeddable->legalName,
            legalForm: $embeddable->legalForm,
            shareCapital: $embeddable->shareCapital,
            siret: $embeddable->siret,
            vatNumber: $embeddable->vatNumber,
            rcsCity: $embeddable->rcsCity,
            rcsNumber: $embeddable->rcsNumber,
            apeCode: $embeddable->apeCode,
            ordinalNumber: $embeddable->ordinalNumber,
            address: $address,
            phone: $embeddable->phone,
            email: $embeddable->email,
            website: $embeddable->website,
        );
    }

    private static function letterheadToEntity(Letterhead $letterhead): LetterheadEmbeddable
    {
        return new LetterheadEmbeddable(
            legalName: $letterhead->legalName,
            legalForm: $letterhead->legalForm,
            shareCapital: $letterhead->shareCapital,
            siret: $letterhead->siret,
            vatNumber: $letterhead->vatNumber,
            rcsCity: $letterhead->rcsCity,
            rcsNumber: $letterhead->rcsNumber,
            apeCode: $letterhead->apeCode,
            ordinalNumber: $letterhead->ordinalNumber,
            streetLine1: $letterhead->address?->streetLine1,
            streetLine2: $letterhead->address?->streetLine2,
            postalCode: $letterhead->address?->postalCode,
            city: $letterhead->address?->city,
            countryCode: $letterhead->address?->countryCode,
            phone: $letterhead->phone?->toString(),
            email: $letterhead->email?->toString(),
            website: $letterhead->website,
        );
    }
}
