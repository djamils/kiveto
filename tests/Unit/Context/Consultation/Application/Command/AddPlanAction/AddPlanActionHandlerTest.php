<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Application\Command\AddPlanAction;

use App\Context\Consultation\Application\Command\AddPlanAction\AddPlanAction;
use App\Context\Consultation\Application\Command\AddPlanAction\AddPlanActionHandler;
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
use App\Context\Consultation\Domain\ValueObject\PlanActionRecord;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AddPlanActionHandlerTest extends TestCase
{
    private const string CONSULTATION_ID = '11111111-1111-4111-8111-111111111111';
    private const string CLINIC_ID       = '22222222-2222-4222-8222-222222222222';
    private const string APPOINTMENT_ID  = '33333333-3333-4333-8333-333333333333';
    private const string ADMISSION_ID    = '44444444-4444-4444-8444-444444444444';
    private const string USER_ID         = '55555555-5555-4555-8555-555555555555';
    private const string PATIENT_ID      = '66666666-6666-4666-8666-666666666666';
    private const string OTHER_CLINIC_ID = '77777777-7777-4777-8777-777777777777';
    private const string CATALOG_ITEM_ID = '88888888-8888-4888-8888-888888888888';

    private ConsultationRepositoryInterface&MockObject $consultations;
    private CatalogItemProviderInterface&MockObject $catalogItems;
    private ClockInterface&MockObject $clock;
    private AddPlanActionHandler $handler;

    protected function setUp(): void
    {
        $this->consultations = $this->createMock(ConsultationRepositoryInterface::class);
        $this->catalogItems  = $this->createMock(CatalogItemProviderInterface::class);
        $this->clock         = $this->createMock(ClockInterface::class);
        $this->handler       = new AddPlanActionHandler($this->consultations, $this->catalogItems, $this->clock);
    }

    public function testAddBillableActSnapshotsTheCatalogPrice(): void
    {
        $consultation = $this->makeConsultation();
        $act          = $this->makeCatalogItem();

        $this->consultations->expects(self::once())->method('findById')->willReturn($consultation);
        $this->catalogItems->expects(self::once())
            ->method('detail')
            ->with('ACT', self::CATALOG_ITEM_ID, self::CLINIC_ID)
            ->willReturn($act)
        ;
        $this->catalogItems->expects(self::once())
            ->method('resolvePrice')
            ->with(self::identicalTo($act), self::CLINIC_ID)
            ->willReturn(new CatalogPriceDto(2500, 'EUR', 'STANDARD'))
        ;
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 09:10:00'));
        $this->consultations->expects(self::once())->method('save')->with(self::identicalTo($consultation));

        ($this->handler)($this->command('PERFORMED_ACT', self::CATALOG_ITEM_ID));

        self::assertSame(['Détartrage'], self::descriptionsOf($consultation));
        self::assertSame([2500], self::unitPricesOf($consultation));
        self::assertSame(['EUR'], self::currenciesOf($consultation));
        self::assertSame(['STANDARD'], self::taxCategoriesOf($consultation));
        self::assertSame([2500], self::billedUnitPricesOf($consultation));
    }

    public function testAddNonBillableActionNeverCallsTheCatalog(): void
    {
        $consultation = $this->makeConsultation();

        $this->consultations->expects(self::once())->method('findById')->willReturn($consultation);
        $this->catalogItems->expects(self::never())->method('detail');
        $this->catalogItems->expects(self::never())->method('resolvePrice');
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 09:10:00'));
        $this->consultations->expects(self::once())->method('save')->with(self::identicalTo($consultation));

        ($this->handler)($this->command('ADVICE', self::CATALOG_ITEM_ID));

        self::assertSame([null], self::unitPricesOf($consultation));
        self::assertSame([], self::billedUnitPricesOf($consultation));
    }

    public function testAddBillableActionWithoutCatalogItemNeverCallsTheCatalog(): void
    {
        $consultation = $this->makeConsultation();

        $this->consultations->expects(self::once())->method('findById')->willReturn($consultation);
        $this->catalogItems->expects(self::never())->method('detail');
        $this->catalogItems->expects(self::never())->method('resolvePrice');
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 09:10:00'));
        $this->consultations->expects(self::once())->method('save')->with(self::identicalTo($consultation));

        ($this->handler)($this->command('PERFORMED_ACT', null));

        self::assertSame([null], self::unitPricesOf($consultation));
        self::assertSame([], self::billedUnitPricesOf($consultation));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenCatalogActIsArchived(): void
    {
        $archived = new CatalogItemDto(
            itemType: 'ACT',
            itemId: self::CATALOG_ITEM_ID,
            name: 'Ancienne consultation',
            code: 'OLD_CONS',
            requiresPrescription: false,
            basePriceMinorUnits: 4000,
            currency: 'EUR',
            taxCategoryCode: 'STANDARD',
            status: 'ARCHIVED',
        );

        $this->consultations->expects(self::once())->method('findById')->willReturn($this->makeConsultation());
        $this->catalogItems->expects(self::once())->method('detail')->willReturn($archived);
        $this->catalogItems->expects(self::never())->method('resolvePrice');
        $this->consultations->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Catalog act not found');

        ($this->handler)($this->command('PERFORMED_ACT', self::CATALOG_ITEM_ID));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenCatalogActNotFound(): void
    {
        $this->consultations->expects(self::once())->method('findById')->willReturn($this->makeConsultation());
        $this->catalogItems->expects(self::once())->method('detail')->willReturn(null);
        $this->catalogItems->expects(self::never())->method('resolvePrice');
        $this->consultations->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Catalog act not found');

        ($this->handler)($this->command('PERFORMED_ACT', self::CATALOG_ITEM_ID));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenConsultationNotFound(): void
    {
        $this->consultations->expects(self::once())->method('findById')->willReturn(null);
        $this->catalogItems->expects(self::never())->method('detail');
        $this->consultations->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Consultation not found');

        ($this->handler)($this->command('PERFORMED_ACT', self::CATALOG_ITEM_ID));
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

        ($this->handler)($this->command('PERFORMED_ACT', self::CATALOG_ITEM_ID));
    }

    public function testFailsWhenConsultationIsClosed(): void
    {
        $this->consultations->expects(self::once())->method('findById')->willReturn($this->makeClosedConsultation());
        $this->catalogItems->expects(self::never())->method('detail');
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 09:10:00'));
        $this->consultations->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot modify a closed consultation');

        ($this->handler)($this->command('ADVICE', null));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenPlanActionKindIsUnknown(): void
    {
        $this->consultations->expects(self::once())->method('findById')->willReturn($this->makeConsultation());
        $this->catalogItems->expects(self::never())->method('detail');
        $this->consultations->expects(self::never())->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown plan action kind');

        ($this->handler)($this->command('TELEPATHY', null));
    }

    private function command(string $kind, ?string $catalogItemId): AddPlanAction
    {
        return new AddPlanAction(
            consultationId: self::CONSULTATION_ID,
            clinicId: self::CLINIC_ID,
            kind: $kind,
            description: 'Détartrage',
            catalogItemId: $catalogItemId,
            catalogCode: 'ACT-001',
            posology: null,
            durationDays: null,
            followUpDays: null,
            quantity: 1.0,
            createdByUserId: self::USER_ID,
        );
    }

    private function makeCatalogItem(): CatalogItemDto
    {
        return new CatalogItemDto(
            itemType: 'ACT',
            itemId: self::CATALOG_ITEM_ID,
            name: 'Détartrage',
            code: 'ACT-001',
            requiresPrescription: false,
            basePriceMinorUnits: 2000,
            currency: 'EUR',
            taxCategoryCode: 'STANDARD',
            status: 'ACTIVE',
        );
    }

    /**
     * @return list<string>
     */
    private static function descriptionsOf(Consultation $consultation): array
    {
        return array_map(
            static fn (PlanActionRecord $action): string => $action->getDescription(),
            $consultation->getPlanActions(),
        );
    }

    /**
     * @return list<int|null>
     */
    private static function unitPricesOf(Consultation $consultation): array
    {
        return array_map(
            static fn (PlanActionRecord $action): ?int => $action->getUnitPriceMinorUnits(),
            $consultation->getPlanActions(),
        );
    }

    /**
     * @return list<string|null>
     */
    private static function currenciesOf(Consultation $consultation): array
    {
        return array_map(
            static fn (PlanActionRecord $action): ?string => $action->getCurrency(),
            $consultation->getPlanActions(),
        );
    }

    /**
     * @return list<string|null>
     */
    private static function taxCategoriesOf(Consultation $consultation): array
    {
        return array_map(
            static fn (PlanActionRecord $action): ?string => $action->getTaxCategoryCode(),
            $consultation->getPlanActions(),
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
