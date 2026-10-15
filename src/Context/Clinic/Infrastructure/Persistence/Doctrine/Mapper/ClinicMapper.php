<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Clinic\Domain\Clinic;
use App\Context\Clinic\Domain\Staff\ValueObject\HexColor;
use App\Context\Clinic\Domain\ValueObject\BillingSettings;
use App\Context\Clinic\Domain\ValueObject\ClinicGroupId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Domain\ValueObject\ClinicSlug;
use App\Context\Clinic\Domain\ValueObject\Letterhead;
use App\Context\Clinic\Domain\ValueObject\OpeningHours;
use App\Context\Clinic\Domain\ValueObject\VatRegime;
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
            openingHours: self::openingHoursToDomain($entity->getOpeningHours()),
            billingSettings: self::billingSettingsToDomain($entity->getBillingSettings()),
            brandColor: null !== $entity->getBrandColor() ? HexColor::fromString($entity->getBrandColor()) : null,
            logoKey: $entity->getLogoKey(),
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
        $entity->setOpeningHours($clinic->openingHours()->toArray());
        $entity->setBillingSettings(self::billingSettingsToArray($clinic->billingSettings()));
        $entity->setBrandColor($clinic->brandColor()?->toString());
        $entity->setLogoKey($clinic->logoKey());
        $entity->setCreatedAt($clinic->createdAt());
        $entity->setUpdatedAt($clinic->updatedAt());

        if ($clinic->clinicGroupId()) {
            $entity->setClinicGroupId(Uuid::fromString($clinic->clinicGroupId()->toString()));
        }

        return $entity;
    }

    /**
     * A row written before the opening hours existed carries an empty object,
     * which reads back as a clinic that has not said when it opens.
     *
     * @param array<string, mixed> $stored
     */
    private static function openingHoursToDomain(array $stored): OpeningHours
    {
        if ([] === $stored) {
            return OpeningHours::empty();
        }

        /* @var array{week?: array<int|string, list<array{opensAt?: string, closesAt?: string}>>, closures?: list<array{date?: string, reason?: string|null}>} $stored */
        return OpeningHours::fromArray($stored);
    }

    /**
     * @param array<string, mixed> $stored
     */
    private static function billingSettingsToDomain(array $stored): BillingSettings
    {
        if ([] === $stored) {
            return BillingSettings::empty();
        }

        $address = null;
        $raw     = $stored['billingAddress'] ?? null;

        if (\is_array($raw)) {
            $street  = self::stringOrNull($raw, 'streetLine1');
            $city    = self::stringOrNull($raw, 'city');
            $country = self::stringOrNull($raw, 'countryCode');

            if (null !== $street && null !== $city && null !== $country) {
                $address = PostalAddress::create(
                    streetLine1: $street,
                    city: $city,
                    countryCode: $country,
                    streetLine2: self::stringOrNull($raw, 'streetLine2'),
                    postalCode: self::stringOrNull($raw, 'postalCode'),
                );
            }
        }

        $terms    = $stored['paymentTermsDays'] ?? null;
        $proTerms = $stored['professionalPaymentTermsDays'] ?? null;
        $regime   = $stored['vatRegime'] ?? null;
        $methods  = $stored['acceptedPaymentMethods'] ?? null;

        return BillingSettings::create(
            billingAddress: $address,
            invoiceNumberPrefix: self::stringOrNull($stored, 'invoiceNumberPrefix'),
            paymentTermsDays: \is_int($terms) ? $terms : null,
            iban: self::stringOrNull($stored, 'iban'),
            bic: self::stringOrNull($stored, 'bic'),
            invoiceFooterNote: self::stringOrNull($stored, 'invoiceFooterNote'),
            creditNotePrefix: self::stringOrNull($stored, 'creditNotePrefix'),
            professionalPaymentTermsDays: \is_int($proTerms) ? $proTerms : null,
            vatRegime: \is_string($regime) ? VatRegime::tryFrom($regime) : null,
            vatOnDebits: true === ($stored['vatOnDebits'] ?? false),
            acceptedPaymentMethods: \is_array($methods) ? $methods : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function billingSettingsToArray(BillingSettings $settings): array
    {
        $address = $settings->billingAddress;

        return [
            'billingAddress' => null === $address ? null : [
                'streetLine1' => $address->streetLine1,
                'streetLine2' => $address->streetLine2,
                'postalCode'  => $address->postalCode,
                'city'        => $address->city,
                'countryCode' => $address->countryCode,
            ],
            'invoiceNumberPrefix'          => $settings->invoiceNumberPrefix,
            'paymentTermsDays'             => $settings->paymentTermsDays,
            'iban'                         => $settings->iban,
            'bic'                          => $settings->bic,
            'invoiceFooterNote'            => $settings->invoiceFooterNote,
            'creditNotePrefix'             => $settings->creditNotePrefix,
            'professionalPaymentTermsDays' => $settings->professionalPaymentTermsDays,
            'vatRegime'                    => $settings->vatRegime?->value,
            'vatOnDebits'                  => $settings->vatOnDebits,
            'acceptedPaymentMethods'       => $settings->acceptedPaymentMethods,
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function stringOrNull(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return \is_string($value) && '' !== $value ? $value : null;
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
            ordinalCouncil: $embeddable->ordinalCouncil,
            liabilityInsurer: $embeddable->liabilityInsurer,
            liabilityPolicyNumber: $embeddable->liabilityPolicyNumber,
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
            ordinalCouncil: $letterhead->ordinalCouncil?->value,
            liabilityInsurer: $letterhead->liabilityInsurer,
            liabilityPolicyNumber: $letterhead->liabilityPolicyNumber,
        );
    }
}
