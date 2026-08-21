<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\BillingLineEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\ConsultationEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\DiagnosisEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\ExamSystemEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\MotifEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\PlanActionEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\PrescriptionLineEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\TypedVitalEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Round-trips every accessor of the consultation Doctrine entities, including
 * the ones the mappers never call.
 */
final class ConsultationEntitiesTest extends TestCase
{
    public function testConsultationEntityGettersAndSetters(): void
    {
        $id        = Uuid::v7()->toBinary();
        $clinicId  = Uuid::v7()->toBinary();
        $startedAt = new \DateTimeImmutable('2026-04-10 09:00:00');
        $closedAt  = new \DateTimeImmutable('2026-04-10 10:00:00');
        $createdAt = new \DateTimeImmutable('2026-04-10 08:00:00');
        $updatedAt = new \DateTimeImmutable('2026-04-10 11:00:00');

        $entity = new ConsultationEntity();
        $entity->setId($id);
        $entity->setClinicId($clinicId);
        $entity->setAppointmentId(null);
        $entity->setAdmissionId($admissionId = Uuid::v7()->toBinary());
        $entity->setPatientId($patientId = Uuid::v7()->toBinary());
        $entity->setPractitionerUserId($userId = Uuid::v7()->toBinary());
        $entity->setStatus('OPEN');
        $entity->setChiefComplaint('Boiterie');
        $entity->setSummary('Traitement local');
        $entity->setSubjectiveText('Boite depuis 3 jours');
        $entity->setObjectiveObservations('Muqueuses roses');
        $entity->setTeamMemo('Prévoir un contrôle');
        $entity->setWeightKg('12.500');
        $entity->setTemperatureC('38.20');
        $entity->setStartedAtUtc($startedAt);
        $entity->setClosedAtUtc($closedAt);
        $entity->setCreatedAtUtc($createdAt);
        $entity->setUpdatedAtUtc($updatedAt);

        self::assertSame($id, $entity->getId());
        self::assertSame($clinicId, $entity->getClinicId());
        self::assertNull($entity->getAppointmentId());
        self::assertSame($admissionId, $entity->getAdmissionId());
        self::assertSame($patientId, $entity->getPatientId());
        self::assertSame($userId, $entity->getPractitionerUserId());
        self::assertSame('OPEN', $entity->getStatus());
        self::assertSame('Boiterie', $entity->getChiefComplaint());
        self::assertSame('Traitement local', $entity->getSummary());
        self::assertSame('Boite depuis 3 jours', $entity->getSubjectiveText());
        self::assertSame('Muqueuses roses', $entity->getObjectiveObservations());
        self::assertSame('Prévoir un contrôle', $entity->getTeamMemo());
        self::assertSame('12.500', $entity->getWeightKg());
        self::assertSame('38.20', $entity->getTemperatureC());
        self::assertSame($startedAt, $entity->getStartedAtUtc());
        self::assertSame($closedAt, $entity->getClosedAtUtc());
        self::assertSame($createdAt, $entity->getCreatedAtUtc());
        self::assertSame($updatedAt, $entity->getUpdatedAtUtc());
        self::assertSame(1, $entity->getVersion());
    }

    public function testConsultationEntityNullableFields(): void
    {
        $entity = new ConsultationEntity();
        $entity->setAppointmentId($appointmentId = Uuid::v7()->toBinary());
        $entity->setChiefComplaint(null);
        $entity->setSummary(null);
        $entity->setSubjectiveText(null);
        $entity->setObjectiveObservations(null);
        $entity->setTeamMemo(null);
        $entity->setWeightKg(null);
        $entity->setTemperatureC(null);
        $entity->setClosedAtUtc(null);

        self::assertSame($appointmentId, $entity->getAppointmentId());
        self::assertNull($entity->getChiefComplaint());
        self::assertNull($entity->getSummary());
        self::assertNull($entity->getSubjectiveText());
        self::assertNull($entity->getObjectiveObservations());
        self::assertNull($entity->getTeamMemo());
        self::assertNull($entity->getWeightKg());
        self::assertNull($entity->getTemperatureC());
        self::assertNull($entity->getClosedAtUtc());
    }

    public function testMotifEntityGettersAndSetters(): void
    {
        $entity = new MotifEntity();
        $entity->setId($id = Uuid::v7()->toBinary());
        $entity->setConsultationId($consultationId = Uuid::v7()->toBinary());
        $entity->setLabel('Boiterie');
        $entity->setPosition(2);

        self::assertSame($id, $entity->getId());
        self::assertSame($consultationId, $entity->getConsultationId());
        self::assertSame('Boiterie', $entity->getLabel());
        self::assertSame(2, $entity->getPosition());
    }

    public function testTypedVitalEntityGettersAndSetters(): void
    {
        $recordedAt = new \DateTimeImmutable('2026-04-10 09:00:00');

        $entity = new TypedVitalEntity();
        $entity->setId($id = Uuid::v7()->toBinary());
        $entity->setConsultationId($consultationId = Uuid::v7()->toBinary());
        $entity->setType('HEART_RATE');
        $entity->setValue('92');
        $entity->setRecordedAtUtc($recordedAt);
        $entity->setRecordedByUserId($userId = Uuid::v7()->toBinary());
        $entity->setPosition(1);

        self::assertSame($id, $entity->getId());
        self::assertSame($consultationId, $entity->getConsultationId());
        self::assertSame('HEART_RATE', $entity->getType());
        self::assertSame('92', $entity->getValue());
        self::assertSame($recordedAt, $entity->getRecordedAtUtc());
        self::assertSame($userId, $entity->getRecordedByUserId());
        self::assertSame(1, $entity->getPosition());
    }

    public function testExamSystemEntityGettersAndSetters(): void
    {
        $recordedAt = new \DateTimeImmutable('2026-04-10 09:00:00');

        $entity = new ExamSystemEntity();
        $entity->setId($id = Uuid::v7()->toBinary());
        $entity->setConsultationId($consultationId = Uuid::v7()->toBinary());
        $entity->setSystem('CARDIOVASCULAR');
        $entity->setStatus('ANOMALY');
        $entity->setNotes('Souffle systolique');
        $entity->setStructuredData(['fc' => '120']);
        $entity->setRecordedAtUtc($recordedAt);
        $entity->setRecordedByUserId($userId = Uuid::v7()->toBinary());
        $entity->setPosition(3);

        self::assertSame($id, $entity->getId());
        self::assertSame($consultationId, $entity->getConsultationId());
        self::assertSame('CARDIOVASCULAR', $entity->getSystem());
        self::assertSame('ANOMALY', $entity->getStatus());
        self::assertSame('Souffle systolique', $entity->getNotes());
        self::assertSame(['fc' => '120'], $entity->getStructuredData());
        self::assertSame($recordedAt, $entity->getRecordedAtUtc());
        self::assertSame($userId, $entity->getRecordedByUserId());
        self::assertSame(3, $entity->getPosition());

        $entity->setNotes(null);
        $entity->setStructuredData([]);

        self::assertNull($entity->getNotes());
        self::assertSame([], $entity->getStructuredData());
    }

    public function testDiagnosisEntityGettersAndSetters(): void
    {
        $createdAt = new \DateTimeImmutable('2026-04-10 09:00:00');

        $entity = new DiagnosisEntity();
        $entity->setId($id = Uuid::v7()->toBinary());
        $entity->setConsultationId($consultationId = Uuid::v7()->toBinary());
        $entity->setCode('OTI-01');
        $entity->setLabel('Otite externe');
        $entity->setCertainty('PROBABLE');
        $entity->setNote('Oreille droite');
        $entity->setIsPrimary(true);
        $entity->setSource('MANUAL');
        $entity->setCreatedAtUtc($createdAt);
        $entity->setCreatedByUserId($userId = Uuid::v7()->toBinary());
        $entity->setPosition(0);

        self::assertSame($id, $entity->getId());
        self::assertSame($consultationId, $entity->getConsultationId());
        self::assertSame('OTI-01', $entity->getCode());
        self::assertSame('Otite externe', $entity->getLabel());
        self::assertSame('PROBABLE', $entity->getCertainty());
        self::assertSame('Oreille droite', $entity->getNote());
        self::assertTrue($entity->isPrimary());
        self::assertSame('MANUAL', $entity->getSource());
        self::assertSame($createdAt, $entity->getCreatedAtUtc());
        self::assertSame($userId, $entity->getCreatedByUserId());
        self::assertSame(0, $entity->getPosition());

        $entity->setCode(null);
        $entity->setNote(null);
        $entity->setIsPrimary(false);

        self::assertNull($entity->getCode());
        self::assertNull($entity->getNote());
        self::assertFalse($entity->isPrimary());
    }

    public function testPlanActionEntityGettersAndSetters(): void
    {
        $createdAt = new \DateTimeImmutable('2026-04-10 09:00:00');

        $entity = new PlanActionEntity();
        $entity->setId($id = Uuid::v7()->toBinary());
        $entity->setConsultationId($consultationId = Uuid::v7()->toBinary());
        $entity->setKind('PERFORMED_ACT');
        $entity->setDescription('Otoscopie');
        $entity->setCatalogCode('ACT-OTO');
        $entity->setPosology('2 fois par jour');
        $entity->setDurationDays(7);
        $entity->setFollowUpDays(14);
        $entity->setQuantity('2.00');
        $entity->setUnitPriceMinorUnits(3500);
        $entity->setCurrency('EUR');
        $entity->setTaxCategoryCode('veterinary.act.consultation');
        $entity->setCreatedAtUtc($createdAt);
        $entity->setCreatedByUserId($userId = Uuid::v7()->toBinary());
        $entity->setPosition(4);

        self::assertSame($id, $entity->getId());
        self::assertSame($consultationId, $entity->getConsultationId());
        self::assertSame('PERFORMED_ACT', $entity->getKind());
        self::assertSame('Otoscopie', $entity->getDescription());
        self::assertSame('ACT-OTO', $entity->getCatalogCode());
        self::assertSame('2 fois par jour', $entity->getPosology());
        self::assertSame(7, $entity->getDurationDays());
        self::assertSame(14, $entity->getFollowUpDays());
        self::assertSame('2.00', $entity->getQuantity());
        self::assertSame(3500, $entity->getUnitPriceMinorUnits());
        self::assertSame('EUR', $entity->getCurrency());
        self::assertSame('veterinary.act.consultation', $entity->getTaxCategoryCode());
        self::assertSame($createdAt, $entity->getCreatedAtUtc());
        self::assertSame($userId, $entity->getCreatedByUserId());
        self::assertSame(4, $entity->getPosition());

        $entity->setCatalogCode(null);
        $entity->setPosology(null);
        $entity->setDurationDays(null);
        $entity->setFollowUpDays(null);
        $entity->setUnitPriceMinorUnits(null);
        $entity->setCurrency(null);
        $entity->setTaxCategoryCode(null);

        self::assertNull($entity->getCatalogCode());
        self::assertNull($entity->getPosology());
        self::assertNull($entity->getDurationDays());
        self::assertNull($entity->getFollowUpDays());
        self::assertNull($entity->getUnitPriceMinorUnits());
        self::assertNull($entity->getCurrency());
        self::assertNull($entity->getTaxCategoryCode());
    }

    public function testPrescriptionLineEntityGettersAndSetters(): void
    {
        $createdAt = new \DateTimeImmutable('2026-04-10 09:00:00');

        $entity = new PrescriptionLineEntity();
        $entity->setId($id = Uuid::v7()->toBinary());
        $entity->setConsultationId($consultationId = Uuid::v7()->toBinary());
        $entity->setArticleId($articleId = Uuid::v7()->toBinary());
        $entity->setCode('ART-AMOX');
        $entity->setLabel('Amoxicilline');
        $entity->setDose('250 mg');
        $entity->setFrequency('2 fois par jour');
        $entity->setDurationDays(10);
        $entity->setRoute('ORAL');
        $entity->setQuantity('20.00');
        $entity->setUnitPriceMinorUnits(1200);
        $entity->setCurrency('EUR');
        $entity->setTaxCategoryCode('veterinary.medicine.oral');
        $entity->setCreatedAtUtc($createdAt);
        $entity->setCreatedByUserId($userId = Uuid::v7()->toBinary());
        $entity->setPosition(5);

        self::assertSame($id, $entity->getId());
        self::assertSame($consultationId, $entity->getConsultationId());
        self::assertSame($articleId, $entity->getArticleId());
        self::assertSame('ART-AMOX', $entity->getCode());
        self::assertSame('Amoxicilline', $entity->getLabel());
        self::assertSame('250 mg', $entity->getDose());
        self::assertSame('2 fois par jour', $entity->getFrequency());
        self::assertSame(10, $entity->getDurationDays());
        self::assertSame('ORAL', $entity->getRoute());
        self::assertSame('20.00', $entity->getQuantity());
        self::assertSame(1200, $entity->getUnitPriceMinorUnits());
        self::assertSame('EUR', $entity->getCurrency());
        self::assertSame('veterinary.medicine.oral', $entity->getTaxCategoryCode());
        self::assertSame($createdAt, $entity->getCreatedAtUtc());
        self::assertSame($userId, $entity->getCreatedByUserId());
        self::assertSame(5, $entity->getPosition());

        $entity->setArticleId(null);
        $entity->setCode(null);
        $entity->setDose(null);
        $entity->setFrequency(null);
        $entity->setDurationDays(null);
        $entity->setRoute(null);
        $entity->setTaxCategoryCode(null);

        self::assertNull($entity->getArticleId());
        self::assertNull($entity->getCode());
        self::assertNull($entity->getDose());
        self::assertNull($entity->getFrequency());
        self::assertNull($entity->getDurationDays());
        self::assertNull($entity->getRoute());
        self::assertNull($entity->getTaxCategoryCode());
    }

    public function testBillingLineEntityGettersAndSetters(): void
    {
        $entity = new BillingLineEntity();
        $entity->setId($id = Uuid::v7()->toBinary());
        $entity->setConsultationId($consultationId = Uuid::v7()->toBinary());
        $entity->setSourceLineId($sourceLineId = Uuid::v7()->toBinary());
        $entity->setSource('PLAN');
        $entity->setLabel('Otoscopie');
        $entity->setCode('ACT-OTO');
        $entity->setQuantity('2.00');
        $entity->setUnitPriceMinorUnits(3500);
        $entity->setCurrency('EUR');
        $entity->setTaxCategoryCode('veterinary.act.consultation');
        $entity->setPosition(6);

        self::assertSame($id, $entity->getId());
        self::assertSame($consultationId, $entity->getConsultationId());
        self::assertSame($sourceLineId, $entity->getSourceLineId());
        self::assertSame('PLAN', $entity->getSource());
        self::assertSame('Otoscopie', $entity->getLabel());
        self::assertSame('ACT-OTO', $entity->getCode());
        self::assertSame('2.00', $entity->getQuantity());
        self::assertSame(3500, $entity->getUnitPriceMinorUnits());
        self::assertSame('EUR', $entity->getCurrency());
        self::assertSame('veterinary.act.consultation', $entity->getTaxCategoryCode());
        self::assertSame(6, $entity->getPosition());

        $entity->setCode(null);
        $entity->setTaxCategoryCode(null);

        self::assertNull($entity->getCode());
        self::assertNull($entity->getTaxCategoryCode());
    }
}
