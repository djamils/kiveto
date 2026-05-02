<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Patient\Story;

use App\Context\Admission\Domain\ValueObject\ClosureReason;
use App\Context\Admission\Domain\ValueObject\IntakeChannel;
use App\Context\Admission\Domain\ValueObject\LocationStatusValue;
use App\Context\Admission\Domain\ValueObject\PresenterRole;
use App\Context\Admission\Domain\ValueObject\TriageLevel;
use App\Context\Admission\Infrastructure\Persistence\Doctrine\Entity\AdmissionEntity;
use App\Context\Patient\Domain\ValueObject\DisplayLabelKind;
use App\Context\Patient\Infrastructure\Persistence\Doctrine\Entity\PatientEntity;
use App\Context\Regulatory\Domain\ValueObject\AuthorityNotificationStatus;
use App\Context\Regulatory\Domain\ValueObject\StrayCustodyStatus;
use App\Context\Regulatory\Infrastructure\Persistence\Doctrine\Entity\StrayCustodyEntity;
use App\Fixtures\Context\Admission\Factory\AdmissionEntityFactory;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\Context\Consultation\Factory\ConsultationEntityFactory;
use App\Fixtures\Context\Patient\Factory\PatientEntityFactory;
use App\Fixtures\Context\Regulatory\Factory\AuthorityNotificationEntityFactory;
use App\Fixtures\Context\Regulatory\Factory\StrayCustodyEntityFactory;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * Fixture story covering the 4 patient / admission scenarios from the tech spec.
 *
 * Scenario 1 — Standard: appointment booked, known animal, known owner.
 * Scenario 2 — Unidentified, owner never found: emergency by authority → handed to municipality.
 * Scenario 3 — Unidentified, owner found later: emergency by third party → released to owner.
 * Scenario 4 — Reconciliation: provisional patient merged into identified patient.
 */
final class PatientAdmissionScenariosStory extends Story
{
    public function build(): void
    {
        $clinicId = ClinicDataStory::INDEPENDENT_CLINIC_ID;

        $this->buildScenario1Standard($clinicId);
        $this->buildScenario2UnidentifiedMunicipality($clinicId);
        $this->buildScenario3UnidentifiedOwnerFound($clinicId);
        $this->buildScenario4Reconciliation($clinicId);
    }

    /**
     * Scenario 1 — Standard appointment: known animal + owner, consultation created.
     */
    private function buildScenario1Standard(string $clinicId): void
    {
        $animalId = Uuid::v7()->toString();

        $patient = PatientEntityFactory::new()
            ->withClinicId($clinicId)
            ->withAnimalLinkId($animalId)
            ->afterInstantiate(static function (PatientEntity $entity): void {
                $entity->setDisplayLabelKind(DisplayLabelKind::FromAnimal);
                $entity->setDisplayLabelValue('Rex — Labrador, Sable');
                $entity->setObservedSpecies('Chien');
                $entity->setObservedColor('Sable');
            })
            ->active()
            ->create()
        ;

        $admission = AdmissionEntityFactory::new()
            ->withClinicId($clinicId)
            ->withPatientId($patient->getId()->toString())
            ->afterInstantiate(static function (AdmissionEntity $entity): void {
                $entity->setIsPatientIdentifiedAtOpening(true);
                $entity->setIntakeChannel(IntakeChannel::AppointmentBooked);
                $entity->setTriageLevel(TriageLevel::Standard);
                $entity->setLocationStatusValue(LocationStatusValue::InConsultationRoom);
                $entity->setLocationStatusEnteredAt(new \DateTimeImmutable());
            })
            ->active()
            ->create()
        ;

        ConsultationEntityFactory::new()
            ->withClinicId($clinicId)
            ->withPatientId($patient->getId()->toString())
            ->withAdmissionId($admission->getId()->toString())
            ->create()
        ;
    }

    /**
     * Scenario 2 — Unidentified stray, owner never found: handed to municipality.
     */
    private function buildScenario2UnidentifiedMunicipality(string $clinicId): void
    {
        $patient = PatientEntityFactory::new()
            ->withClinicId($clinicId)
            ->provisional('Berger croisé, sans puce, urgence 09h')
            ->afterInstantiate(static function (PatientEntity $entity): void {
                $entity->setObservedSpecies('Chien');
                $entity->setObservedColor('Fauve et blanc');
            })
            ->active()
            ->create()
        ;

        $admission = AdmissionEntityFactory::new()
            ->withClinicId($clinicId)
            ->withPatientId($patient->getId()->toString())
            ->afterInstantiate(static function (AdmissionEntity $entity): void {
                $entity->setIsPatientIdentifiedAtOpening(false);
                $entity->setIntakeChannel(IntakeChannel::EmergencyByAuthority);
                $entity->setTriageLevel(TriageLevel::Emergency);
                $entity->setPresenterName('Agent Durand');
                $entity->setPresenterPhone('0600000001');
                $entity->setPresenterRole(PresenterRole::Authority);
                $entity->setLocationStatusValue(LocationStatusValue::AtReception);
                $entity->setLocationStatusEnteredAt(new \DateTimeImmutable('-2 days'));
                $entity->setClosedAt(new \DateTimeImmutable('-1 day'));
            })
            ->closed(ClosureReason::HandedToAuthority)
            ->create()
        ;

        AuthorityNotificationEntityFactory::new()
            ->withClinicId($clinicId)
            ->withAdmissionId($admission->getId()->toString())
            ->withPatientId($patient->getId()->toString())
            ->withStatus(AuthorityNotificationStatus::Sent)
            ->create()
        ;

        StrayCustodyEntityFactory::new()
            ->withClinicId($clinicId)
            ->withAdmissionId($admission->getId()->toString())
            ->withPatientId($patient->getId()->toString())
            ->withStatus(StrayCustodyStatus::ClosedHandedToAuthority)
            ->afterInstantiate(static function (StrayCustodyEntity $entity): void {
                $entity->setDeadline(new \DateTimeImmutable('-1 day'));
            })
            ->create()
        ;
    }

    /**
     * Scenario 3 — Unidentified stray, owner found later: released to owner.
     */
    private function buildScenario3UnidentifiedOwnerFound(string $clinicId): void
    {
        $animalId = Uuid::v7()->toString();

        $patient = PatientEntityFactory::new()
            ->withClinicId($clinicId)
            ->withAnimalLinkId($animalId)
            ->afterInstantiate(static function (PatientEntity $entity): void {
                $entity->setDisplayLabelKind(DisplayLabelKind::FromAnimal);
                $entity->setDisplayLabelValue('Chat tigré, puce retrouvée');
                $entity->setObservedSpecies('Chat');
                $entity->setObservedColor('Tigré');
            })
            ->active()
            ->create()
        ;

        $admission = AdmissionEntityFactory::new()
            ->withClinicId($clinicId)
            ->withPatientId($patient->getId()->toString())
            ->afterInstantiate(static function (AdmissionEntity $entity): void {
                $entity->setIsPatientIdentifiedAtOpening(false);
                $entity->setIntakeChannel(IntakeChannel::EmergencyByThirdParty);
                $entity->setTriageLevel(TriageLevel::Priority);
                $entity->setPresenterName('Marie Martin');
                $entity->setPresenterPhone('0600000002');
                $entity->setPresenterRole(PresenterRole::ThirdParty);
                $entity->setLocationStatusValue(LocationStatusValue::AtReception);
                $entity->setLocationStatusEnteredAt(new \DateTimeImmutable('-5 days'));
                $entity->setClosedAt(new \DateTimeImmutable('-3 days'));
            })
            ->closed(ClosureReason::ReleasedToOwner)
            ->create()
        ;

        StrayCustodyEntityFactory::new()
            ->withClinicId($clinicId)
            ->withAdmissionId($admission->getId()->toString())
            ->withPatientId($patient->getId()->toString())
            ->withStatus(StrayCustodyStatus::CancelledOwnerFound)
            ->afterInstantiate(static function (StrayCustodyEntity $entity): void {
                $entity->setDeadline(new \DateTimeImmutable('+3 days'));
            })
            ->create()
        ;

        AuthorityNotificationEntityFactory::new()
            ->withClinicId($clinicId)
            ->withAdmissionId($admission->getId()->toString())
            ->withPatientId($patient->getId()->toString())
            ->withStatus(AuthorityNotificationStatus::Sent)
            ->create()
        ;
    }

    /**
     * Scenario 4 — Reconciliation: provisional (source) merged into identified (target).
     *
     * The source patient is archived after the merge; the target patient inherits the
     * admission and consultation records.
     */
    private function buildScenario4Reconciliation(string $clinicId): void
    {
        // Source: provisional patient created at intake before identification
        PatientEntityFactory::new()
            ->withClinicId($clinicId)
            ->provisional('Chien croisé noir, trouvé rue de la Paix')
            ->afterInstantiate(static function (PatientEntity $entity): void {
                $entity->setObservedSpecies('Chien');
                $entity->setObservedColor('Noir');
            })
            ->archived()
            ->create()
        ;

        // Target: patient resolved to a known animal record after reconciliation
        $animalId = Uuid::v7()->toString();

        $target = PatientEntityFactory::new()
            ->withClinicId($clinicId)
            ->withAnimalLinkId($animalId)
            ->afterInstantiate(static function (PatientEntity $entity): void {
                $entity->setDisplayLabelKind(DisplayLabelKind::FromAnimal);
                $entity->setDisplayLabelValue('Buddy — Labrador Chocolat');
                $entity->setObservedSpecies('Chien');
                $entity->setObservedColor('Chocolat');
            })
            ->active()
            ->create()
        ;

        $admission = AdmissionEntityFactory::new()
            ->withClinicId($clinicId)
            ->withPatientId($target->getId()->toString())
            ->afterInstantiate(static function (AdmissionEntity $entity): void {
                $entity->setIsPatientIdentifiedAtOpening(false);
                $entity->setIntakeChannel(IntakeChannel::WalkIn);
                $entity->setTriageLevel(TriageLevel::Standard);
                $entity->setLocationStatusValue(LocationStatusValue::InConsultationRoom);
                $entity->setLocationStatusEnteredAt(new \DateTimeImmutable());
            })
            ->active()
            ->create()
        ;

        ConsultationEntityFactory::new()
            ->withClinicId($clinicId)
            ->withPatientId($target->getId()->toString())
            ->withAdmissionId($admission->getId()->toString())
            ->create()
        ;
    }
}
