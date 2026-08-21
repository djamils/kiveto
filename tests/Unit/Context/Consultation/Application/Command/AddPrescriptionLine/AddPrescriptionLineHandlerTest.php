<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Application\Command\AddPrescriptionLine;

use App\Context\Consultation\Application\Command\AddPrescriptionLine\AddPrescriptionLine;
use App\Context\Consultation\Application\Command\AddPrescriptionLine\AddPrescriptionLineHandler;
use App\Context\Consultation\Application\Port\CatalogItemDto;
use App\Context\Consultation\Application\Port\CatalogItemProviderInterface;
use App\Context\Consultation\Application\Port\CatalogPriceDto;
use App\Context\Consultation\Domain\Consultation;
use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\AdmissionId;
use App\Context\Consultation\Domain\ValueObject\AppointmentId;
use App\Context\Consultation\Domain\ValueObject\BillingLineRecord;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\PatientId;
use App\Context\Consultation\Domain\ValueObject\PrescriptionLineRecord;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AddPrescriptionLineHandlerTest extends TestCase
{
    private const string CONSULTATION_ID = '11111111-1111-4111-8111-111111111111';
    private const string CLINIC_ID       = '22222222-2222-4222-8222-222222222222';
    private const string APPOINTMENT_ID  = '33333333-3333-4333-8333-333333333333';
    private const string ADMISSION_ID    = '44444444-4444-4444-8444-444444444444';
    private const string USER_ID         = '55555555-5555-4555-8555-555555555555';
    private const string PATIENT_ID      = '66666666-6666-4666-8666-666666666666';
    private const string OTHER_CLINIC_ID = '77777777-7777-4777-8777-777777777777';
    private const string ARTICLE_ID      = '88888888-8888-4888-8888-888888888888';

    private ConsultationRepositoryInterface&MockObject $consultations;
    private CatalogItemProviderInterface&MockObject $catalogItems;
    private ClockInterface&MockObject $clock;
    private AddPrescriptionLineHandler $handler;

    protected function setUp(): void
    {
        $this->consultations = $this->createMock(ConsultationRepositoryInterface::class);
        $this->catalogItems  = $this->createMock(CatalogItemProviderInterface::class);
        $this->clock         = $this->createMock(ClockInterface::class);
        $this->handler       = new AddPrescriptionLineHandler($this->consultations, $this->catalogItems, $this->clock);
    }

    public function testAddPrescriptionLineSnapshotsTheCatalogPrice(): void
    {
        $consultation = $this->makeConsultation();
        $article      = $this->makeCatalogItem();

        $this->consultations->expects(self::once())->method('findById')->willReturn($consultation);
        $this->catalogItems->expects(self::once())
            ->method('detail')
            ->with('ARTICLE', self::ARTICLE_ID, self::CLINIC_ID)
            ->willReturn($article)
        ;
        $this->catalogItems->expects(self::once())
            ->method('resolvePrice')
            ->with(self::identicalTo($article), self::CLINIC_ID)
            ->willReturn(new CatalogPriceDto(1250, 'EUR', 'REDUCED'))
        ;
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 09:10:00'));
        $this->consultations->expects(self::once())->method('save')->with(self::identicalTo($consultation));

        ($this->handler)($this->command());

        self::assertSame(['Amoxicilline 500mg'], self::labelsOf($consultation));
        self::assertSame(['MED-001'], self::codesOf($consultation));
        self::assertSame([1250], self::unitPricesOf($consultation));
        self::assertSame(['EUR'], self::currenciesOf($consultation));
        self::assertSame(['REDUCED'], self::taxCategoriesOf($consultation));
        self::assertSame([1250], self::billedUnitPricesOf($consultation));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenCatalogArticleIsArchived(): void
    {
        $archived = new CatalogItemDto(
            itemType: 'ARTICLE',
            itemId: self::ARTICLE_ID,
            name: 'Rilexine 300 mg',
            code: 'RILEXINE-300',
            requiresPrescription: true,
            basePriceMinorUnits: 1750,
            currency: 'EUR',
            taxCategoryCode: 'REDUCED',
            status: 'ARCHIVED',
        );

        $this->consultations->expects(self::once())->method('findById')->willReturn($this->makeConsultation());
        $this->catalogItems->expects(self::once())->method('detail')->willReturn($archived);
        $this->catalogItems->expects(self::never())->method('resolvePrice');
        $this->consultations->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Catalog article not found');

        ($this->handler)($this->command());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenCatalogArticleNotFound(): void
    {
        $this->consultations->expects(self::once())->method('findById')->willReturn($this->makeConsultation());
        $this->catalogItems->expects(self::once())->method('detail')->willReturn(null);
        $this->catalogItems->expects(self::never())->method('resolvePrice');
        $this->consultations->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Catalog article not found');

        ($this->handler)($this->command());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenConsultationNotFound(): void
    {
        $this->consultations->expects(self::once())->method('findById')->willReturn(null);
        $this->catalogItems->expects(self::never())->method('detail');
        $this->consultations->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Consultation not found');

        ($this->handler)($this->command());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenConsultationBelongsToAnotherClinic(): void
    {
        $this->consultations->expects(self::once())
            ->method('findById')
            ->willReturn($this->makeConsultation(self::OTHER_CLINIC_ID))
        ;
        $this->catalogItems->expects(self::never())->method('detail');
        $this->consultations->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Consultation not found');

        ($this->handler)($this->command());
    }

    public function testFailsWhenConsultationIsClosed(): void
    {
        $article = $this->makeCatalogItem();

        $this->consultations->expects(self::once())->method('findById')->willReturn($this->makeClosedConsultation());
        $this->catalogItems->expects(self::once())->method('detail')->willReturn($article);
        $this->catalogItems->expects(self::once())
            ->method('resolvePrice')
            ->willReturn(new CatalogPriceDto(1250, 'EUR', 'REDUCED'))
        ;
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 09:10:00'));
        $this->consultations->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot modify a closed consultation');

        ($this->handler)($this->command());
    }

    private function command(): AddPrescriptionLine
    {
        return new AddPrescriptionLine(
            consultationId: self::CONSULTATION_ID,
            clinicId: self::CLINIC_ID,
            articleId: self::ARTICLE_ID,
            dose: '1 comprimé',
            frequency: '2x/jour',
            durationDays: 7,
            route: 'Orale',
            quantity: 14.0,
            createdByUserId: self::USER_ID,
        );
    }

    private function makeCatalogItem(): CatalogItemDto
    {
        return new CatalogItemDto(
            itemType: 'ARTICLE',
            itemId: self::ARTICLE_ID,
            name: 'Amoxicilline 500mg',
            code: 'MED-001',
            requiresPrescription: true,
            basePriceMinorUnits: 1000,
            currency: 'EUR',
            taxCategoryCode: 'REDUCED',
            status: 'ACTIVE',
        );
    }

    /**
     * @return list<string>
     */
    private static function labelsOf(Consultation $consultation): array
    {
        return array_map(
            static fn (PrescriptionLineRecord $line): string => $line->getLabel(),
            $consultation->getPrescriptionLines(),
        );
    }

    /**
     * @return list<string|null>
     */
    private static function codesOf(Consultation $consultation): array
    {
        return array_map(
            static fn (PrescriptionLineRecord $line): ?string => $line->getCode(),
            $consultation->getPrescriptionLines(),
        );
    }

    /**
     * @return list<int>
     */
    private static function unitPricesOf(Consultation $consultation): array
    {
        return array_map(
            static fn (PrescriptionLineRecord $line): int => $line->getUnitPriceMinorUnits(),
            $consultation->getPrescriptionLines(),
        );
    }

    /**
     * @return list<string>
     */
    private static function currenciesOf(Consultation $consultation): array
    {
        return array_map(
            static fn (PrescriptionLineRecord $line): string => $line->getCurrency(),
            $consultation->getPrescriptionLines(),
        );
    }

    /**
     * @return list<string|null>
     */
    private static function taxCategoriesOf(Consultation $consultation): array
    {
        return array_map(
            static fn (PrescriptionLineRecord $line): ?string => $line->getTaxCategoryCode(),
            $consultation->getPrescriptionLines(),
        );
    }

    /**
     * @return list<int>
     */
    private static function billedUnitPricesOf(Consultation $consultation): array
    {
        return array_map(
            static fn (BillingLineRecord $line): int => $line->getUnitPriceMinorUnits(),
            $consultation->getBillingLines(),
        );
    }

    private function makeConsultation(string $clinicId = self::CLINIC_ID): Consultation
    {
        return Consultation::startFromAppointment(
            ConsultationId::fromString(self::CONSULTATION_ID),
            ClinicId::fromString($clinicId),
            AppointmentId::fromString(self::APPOINTMENT_ID),
            AdmissionId::fromString(self::ADMISSION_ID),
            PatientId::fromString(self::PATIENT_ID),
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10 09:00:00'),
        );
    }

    private function makeClosedConsultation(): Consultation
    {
        $consultation = $this->makeConsultation();
        $consultation->close(
            UserId::fromString(self::USER_ID),
            null,
            new \DateTimeImmutable('2026-04-10 09:30:00'),
        );

        return $consultation;
    }
}
