<?php

declare(strict_types=1);

namespace Tests\Unit\System\PharmaceuticalRegistry\Domain;

use App\System\PharmaceuticalRegistry\Domain\Entity\Presentation;
use App\System\PharmaceuticalRegistry\Domain\Event\MarketingAuthorizationCreated;
use App\System\PharmaceuticalRegistry\Domain\Event\PresentationAdded;
use App\System\PharmaceuticalRegistry\Domain\Exception\DuplicateJurisdictionalIdException;
use App\System\PharmaceuticalRegistry\Domain\MarketingAuthorization;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\AtcVetCode;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\CommercialName;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\Gtin;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\HolderLaboratory;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportSource;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\JurisdictionalIdentifier;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\MarketingAuthorizationDate;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\MarketingAuthorizationId;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\MarketingAuthorizationStatus;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PharmaceuticalForm;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PrescriptionRequirement;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PresentationDescription;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PresentationId;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ProductNature;
use PHPUnit\Framework\TestCase;

final class MarketingAuthorizationTest extends TestCase
{
    public function testCreateWithThreePresentationsRecordsFourEvents(): void
    {
        $ma = $this->createMa(presentationCount: 3);

        $domainEvents      = $ma->pullDomainEvents();
        $integrationEvents = $ma->pullIntegrationEvents();

        self::assertCount(1, $domainEvents);
        self::assertInstanceOf(MarketingAuthorizationCreated::class, $domainEvents[0]);

        self::assertCount(3, $integrationEvents);

        foreach ($integrationEvents as $event) {
            self::assertInstanceOf(PresentationAdded::class, $event);
        }
    }

    public function testAddJurisdictionalIdentifierThrowsOnDuplicate(): void
    {
        $ma         = $this->createMa();
        $identifier = JurisdictionalIdentifier::anmv('FR/H/0042');

        $this->expectException(DuplicateJurisdictionalIdException::class);
        $ma->addJurisdictionalIdentifier($identifier);
    }

    public function testAddPresentationRefusedWhenWithdrawn(): void
    {
        $ma = $this->createMa(status: MarketingAuthorizationStatus::WITHDRAWN);

        $this->expectException(\DomainException::class);
        $ma->addPresentation(
            Presentation::create(
                id: PresentationId::fromString('018f1b1e-0000-7000-8000-000000000099'),
                description: PresentationDescription::fromString('New'),
                unitCount: null,
                packaging: null,
                gtin: null,
                euPackIdentifier: null,
                prescriptionRequirement: PrescriptionRequirement::none(),
            ),
            new \DateTimeImmutable(),
        );
    }

    public function testWithdrawIsIdempotent(): void
    {
        $ma = $this->createMa();
        self::assertEmpty($ma->pullIntegrationEvents());

        $now = new \DateTimeImmutable();
        $ma->withdraw($now);
        $firstEvents = $ma->pullIntegrationEvents();

        $ma->withdraw($now);
        $secondEvents = $ma->pullIntegrationEvents();

        self::assertNotEmpty($firstEvents);
        self::assertEmpty($secondEvents);
        self::assertSame(MarketingAuthorizationStatus::WITHDRAWN, $ma->status());
    }

    private function createMa(
        int $presentationCount = 0,
        MarketingAuthorizationStatus $status = MarketingAuthorizationStatus::ACTIVE,
    ): MarketingAuthorization {
        $presentations = [];

        for ($i = 0; $i < $presentationCount; ++$i) {
            $presentations[] = Presentation::create(
                id: PresentationId::fromString('018f1b1e-0000-0000-0000-' . str_pad((string) $i, 12, '0', \STR_PAD_LEFT)),
                description: PresentationDescription::fromString("Presentation $i"),
                unitCount: null,
                packaging: null,
                gtin: Gtin::fromString('12345678'),
                euPackIdentifier: null,
                prescriptionRequirement: PrescriptionRequirement::none(),
            );
        }

        return MarketingAuthorization::create(
            id: MarketingAuthorizationId::fromString('018f1b1e-0000-7000-8000-000000000001'),
            commercialName: CommercialName::fromString('Apoquel'),
            holderLaboratory: HolderLaboratory::fromString('Zoetis'),
            status: $status,
            authorizationDate: MarketingAuthorizationDate::fromDateTime(new \DateTimeImmutable('2015-01-01')),
            nature: ProductNature::CHEMICAL,
            pharmaceuticalForm: PharmaceuticalForm::fromString('Comprimé'),
            atcVetCode: AtcVetCode::fromString('QD11AH91'),
            permanentIdentifier: null,
            controlledSubstance: null,
            identifiers: [JurisdictionalIdentifier::anmv('FR/H/0042')],
            initialPresentations: $presentations,
            compositions: [],
            targetUsages: [],
            summary: null,
            source: ImportSource::ANMV,
            now: new \DateTimeImmutable('2024-01-01'),
        );
    }
}
