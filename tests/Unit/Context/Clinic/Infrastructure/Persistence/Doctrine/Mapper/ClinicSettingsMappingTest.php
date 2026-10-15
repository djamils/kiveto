<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Context\Clinic\Domain\ValueObject\OrdinalCouncil;
use App\Context\Clinic\Domain\ValueObject\VatRegime;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Embeddable\LetterheadEmbeddable;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Entity\ClinicEntity;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Mapper\ClinicMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The settings the page writes go to the row and come back from it.
 */
final class ClinicSettingsMappingTest extends TestCase
{
    public function testTheBrandingTheLetterheadAndTheBillingChoicesRoundTrip(): void
    {
        $entity = self::entity();
        $entity->setBrandColor('#4f86c6');
        $entity->setLogoKey('2026/10/15/clinic-logos/a.png');
        $entity->setLetterhead(new LetterheadEmbeddable(
            legalName: 'SELARL',
            ordinalCouncil: 'idf',
            liabilityInsurer: 'MACSF',
            liabilityPolicyNumber: '4401220788',
        ));
        $entity->setBillingSettings([
            'invoiceNumberPrefix'          => 'FAC',
            'creditNotePrefix'             => 'AV',
            'professionalPaymentTermsDays' => 45,
            'vatRegime'                    => 'MONTHLY',
            'vatOnDebits'                  => true,
            'acceptedPaymentMethods'       => ['CARD', 'CASH'],
        ]);

        $mapper = new ClinicMapper();
        $clinic = $mapper->toDomain($entity);

        self::assertSame('#4f86c6', $clinic->brandColor()?->toString());
        self::assertSame('2026/10/15/clinic-logos/a.png', $clinic->logoKey());
        self::assertSame(OrdinalCouncil::ILE_DE_FRANCE, $clinic->letterhead()->ordinalCouncil);
        self::assertSame('MACSF', $clinic->letterhead()->liabilityInsurer);
        self::assertSame('AV', $clinic->billingSettings()->creditNotePrefix);
        self::assertSame(45, $clinic->billingSettings()->professionalPaymentTermsDays);
        self::assertSame(VatRegime::MONTHLY, $clinic->billingSettings()->vatRegime);
        self::assertTrue($clinic->billingSettings()->vatOnDebits);
        self::assertSame(['CARD', 'CASH'], $clinic->billingSettings()->acceptedPaymentMethods);

        $back = $mapper->toEntity($clinic);

        self::assertSame('#4f86c6', $back->getBrandColor());
        self::assertSame('2026/10/15/clinic-logos/a.png', $back->getLogoKey());
        self::assertSame('idf', $back->getLetterhead()->ordinalCouncil);
        self::assertSame('4401220788', $back->getLetterhead()->liabilityPolicyNumber);
        self::assertSame('MONTHLY', $back->getBillingSettings()['vatRegime']);
        self::assertSame(['CARD', 'CASH'], $back->getBillingSettings()['acceptedPaymentMethods']);
        self::assertTrue($back->getBillingSettings()['vatOnDebits']);
    }

    public function testWhatAnOlderRowLacksReadsAsNothingChosen(): void
    {
        $entity = self::entity();
        $entity->setBillingSettings(['invoiceNumberPrefix' => 'FAC', 'vatRegime' => 'WEEKLY', 'acceptedPaymentMethods' => 'CARD']);

        $clinic = (new ClinicMapper())->toDomain($entity);

        self::assertNull($clinic->brandColor());
        self::assertNull($clinic->logoKey());
        self::assertNull($clinic->billingSettings()->vatRegime);
        self::assertFalse($clinic->billingSettings()->vatOnDebits);
        self::assertNull($clinic->billingSettings()->acceptedPaymentMethods);
        self::assertNull($clinic->billingSettings()->professionalPaymentTermsDays);
    }

    private static function entity(): ClinicEntity
    {
        $entity = new ClinicEntity();
        $entity->setId(Uuid::v7());
        $entity->setName('Clinique');
        $entity->setSlug('clinique');
        $entity->setTimeZone('Europe/Paris');
        $entity->setLocale('fr-FR');
        $entity->setCountryCode('FR');
        $entity->setCurrencyCode('EUR');
        $entity->setStatus(ClinicStatus::ACTIVE);
        $entity->setCreatedAt(new \DateTimeImmutable('2026-01-01T10:00:00Z'));
        $entity->setUpdatedAt(new \DateTimeImmutable('2026-01-01T10:00:00Z'));

        return $entity;
    }
}
