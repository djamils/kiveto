<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Consultation\Story;

use App\Context\Admission\Application\Command\OpenAdmissionForWalkIn\OpenAdmissionForWalkIn;
use App\Context\Animal\Application\Command\AddMedicalAlert\AddMedicalAlert;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\ActEntity;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\ArticleEntity;
use App\Context\Consultation\Application\Command\AddDiagnosis\AddDiagnosis;
use App\Context\Consultation\Application\Command\AddPlanAction\AddPlanAction;
use App\Context\Consultation\Application\Command\AddPrescriptionLine\AddPrescriptionLine;
use App\Context\Consultation\Application\Command\CloseConsultation\CloseConsultation;
use App\Context\Consultation\Application\Command\RecordChiefComplaint\RecordChiefComplaint;
use App\Context\Consultation\Application\Command\RecordExamSystem\RecordExamSystem;
use App\Context\Consultation\Application\Command\RecordTypedVital\RecordTypedVital;
use App\Context\Consultation\Application\Command\RecordVitals\RecordVitals;
use App\Context\Consultation\Application\Command\StartConsultationFromAdmission\StartConsultationFromAdmission;
use App\Context\Consultation\Application\Command\UpdateConsultationMotifs\UpdateConsultationMotifs;
use App\Context\Consultation\Application\Command\UpdateObjectiveObservations\UpdateObjectiveObservations;
use App\Context\Consultation\Application\Command\UpdateSubjectiveText\UpdateSubjectiveText;
use App\Context\Consultation\Application\Command\UpdateTeamMemo\UpdateTeamMemo;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\ConsultationEntity;
use App\Fixtures\Context\Animal\Story\AnimalDataStory;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * Demo consultations covering the cockpit's UI states: an empty open one, a rich
 * open one with every block filled, and a closed one that feeds the history
 * panel of the other two.
 *
 * Everything goes through the command bus so the demo data is built by the same
 * invariants as production writes.
 */
final class ConsultationDemoStory extends Story
{
    private const string CLINIC_ID = ClinicDataStory::INDEPENDENT_CLINIC_ID;

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function build(): void
    {
        $vetUserId = $this->veterinarianId();

        $this->seedMedicalAlerts();

        // One admission per animal: a clinic cannot have two active patients for
        // the same animal, and Rex's two consultations share his visit.
        $rexAdmissionId   = $this->openAdmission(AnimalDataStory::ANIMAL_REX_ID, 'Rex', 'Boiterie postérieure droite');
        $minouAdmissionId = $this->openAdmission(AnimalDataStory::ANIMAL_MINOU_ID, 'Minou', 'Consultation de contrôle');

        // Oldest first so the history of the newer ones is populated.
        $this->buildClosedConsultation($rexAdmissionId, $vetUserId);
        $this->buildRichOpenConsultation($rexAdmissionId, $vetUserId);
        $this->buildEmptyOpenConsultation($minouAdmissionId, $vetUserId);
    }

    /**
     * The allergy label is a molecule name on purpose: the prescription warning
     * is a plain label match against the article's active substances, so
     * "Amoxicilline" fires on Synulox while a class name such as "Pénicilline"
     * would not.
     */
    private function seedMedicalAlerts(): void
    {
        $this->commandBus->dispatch(new AddMedicalAlert(
            clinicId: self::CLINIC_ID,
            animalId: AnimalDataStory::ANIMAL_REX_ID,
            kind: 'ALLERGY',
            label: 'Amoxicilline',
            note: 'Œdème facial constaté après une cure en 2024.',
        ));

        $this->commandBus->dispatch(new AddMedicalAlert(
            clinicId: self::CLINIC_ID,
            animalId: AnimalDataStory::ANIMAL_REX_ID,
            kind: 'CHRONIC_CONDITION',
            label: 'Arthrose',
            note: 'Grasset postérieur droit, suivi semestriel.',
        ));

        $this->commandBus->dispatch(new AddMedicalAlert(
            clinicId: self::CLINIC_ID,
            animalId: AnimalDataStory::ANIMAL_MINOU_ID,
            kind: 'CHRONIC_CONDITION',
            label: 'Insuffisance rénale',
            note: 'Stade 2 IRIS, contrôle trimestriel.',
        ));
    }

    private function buildClosedConsultation(string $admissionId, string $vetUserId): void
    {
        $consultationId = $this->startConsultation($admissionId, $vetUserId);

        $this->commandBus->dispatch(new RecordChiefComplaint(
            consultationId: $consultationId,
            clinicId: self::CLINIC_ID,
            chiefComplaint: 'Bilan annuel, rappel vaccinal CHPPiL.',
        ));

        $this->commandBus->dispatch(new RecordVitals(
            consultationId: $consultationId,
            clinicId: self::CLINIC_ID,
            weightKg: 28.8,
            temperatureC: 38.4,
        ));

        $this->commandBus->dispatch(new UpdateConsultationMotifs(
            consultationId: $consultationId,
            clinicId: self::CLINIC_ID,
            labels: ['Vaccination', 'Bilan annuel'],
        ));

        $this->commandBus->dispatch(new AddPlanAction(
            consultationId: $consultationId,
            clinicId: self::CLINIC_ID,
            kind: 'PERFORMED_ACT',
            description: 'Vaccination CHPPiL',
            catalogItemId: $this->actId('VACC_CHPPIL'),
            catalogCode: null,
            posology: null,
            durationDays: null,
            followUpDays: null,
            quantity: 1.0,
            createdByUserId: $vetUserId,
        ));

        $this->commandBus->dispatch(new CloseConsultation(
            consultationId: $consultationId,
            closedByUserId: $vetUserId,
            summary: 'Vaccination CHPPiL réalisée. Poids stable à 28,8 kg. Aucun signe clinique anormal.',
        ));

        // Backdate so the history panel shows a plausible timeline.
        $this->backdate($consultationId, '-5 months');
    }

    private function buildRichOpenConsultation(string $admissionId, string $vetUserId): void
    {
        $consultationId = $this->startConsultation($admissionId, $vetUserId);

        $this->commandBus->dispatch(new RecordChiefComplaint(
            consultationId: $consultationId,
            clinicId: self::CLINIC_ID,
            chiefComplaint: 'Boiterie postérieure droite intermittente depuis 5 jours.',
        ));

        $this->commandBus->dispatch(new UpdateConsultationMotifs(
            consultationId: $consultationId,
            clinicId: self::CLINIC_ID,
            labels: ['Boiterie', 'Suivi chronique'],
        ));

        $this->commandBus->dispatch(new RecordVitals(
            consultationId: $consultationId,
            clinicId: self::CLINIC_ID,
            weightKg: 28.5,
            temperatureC: 38.6,
        ));

        foreach ([['HEART_RATE', '96'], ['RESPIRATORY_RATE', '22'], ['PAIN_SCORE', '2']] as [$type, $value]) {
            $this->commandBus->dispatch(new RecordTypedVital(
                consultationId: $consultationId,
                clinicId: self::CLINIC_ID,
                type: $type,
                value: $value,
                recordedByUserId: $vetUserId,
            ));
        }

        $this->commandBus->dispatch(new UpdateSubjectiveText(
            consultationId: $consultationId,
            clinicId: self::CLINIC_ID,
            text: 'Le propriétaire rapporte une boiterie postérieure droite intermittente depuis 5 jours, '
                . "plus marquée le matin au lever et après les sorties longues.\n\n"
                . 'Pas de chute identifiée. Appétit conservé, comportement habituel.',
        ));

        $this->commandBus->dispatch(new RecordExamSystem(
            consultationId: $consultationId,
            clinicId: self::CLINIC_ID,
            system: 'LOCOMOTOR',
            status: 'ANOMALY',
            notes: 'Boiterie grade 2/4 postérieur droit. Douleur à la flexion forcée du grasset. '
                . 'Tiroir antérieur négatif. Amyotrophie modérée du quadriceps droit.',
            structuredData: ['limb' => 'PD', 'grade' => '2', 'type' => 'Mécanique', 'region' => 'grasset'],
            recordedByUserId: $vetUserId,
        ));

        foreach (['CARDIOVASCULAR', 'RESPIRATORY', 'DIGESTIVE', 'URINARY', 'NEUROLOGICAL', 'SKIN'] as $system) {
            $this->commandBus->dispatch(new RecordExamSystem(
                consultationId: $consultationId,
                clinicId: self::CLINIC_ID,
                system: $system,
                status: 'NORMAL',
                notes: null,
                structuredData: [],
                recordedByUserId: $vetUserId,
            ));
        }

        $this->commandBus->dispatch(new UpdateObjectiveObservations(
            consultationId: $consultationId,
            clinicId: self::CLINIC_ID,
            text: 'Animal alerte et coopératif. Démarche raide en fin d\'examen, '
                . 'hésitation à charger le postérieur droit.',
        ));

        $this->commandBus->dispatch(new AddDiagnosis(
            consultationId: $consultationId,
            clinicId: self::CLINIC_ID,
            code: 'M.LOC.21',
            label: 'Arthrose grasset droit, stade précoce',
            certainty: 'PROBABLE',
            note: 'Race prédisposée + âge + boiterie chronique intermittente. À confirmer par radio.',
            isPrimary: true,
            source: 'MANUAL',
            createdByUserId: $vetUserId,
        ));

        $this->commandBus->dispatch(new AddDiagnosis(
            consultationId: $consultationId,
            clinicId: self::CLINIC_ID,
            code: 'M.LOC.18',
            label: 'Rupture partielle ligament croisé crânial',
            certainty: 'EXCLUDED',
            note: 'Tiroir antérieur négatif à l\'examen.',
            isPrimary: false,
            source: 'AI_SUGGESTION',
            createdByUserId: $vetUserId,
        ));

        $this->commandBus->dispatch(new AddPlanAction(
            consultationId: $consultationId,
            clinicId: self::CLINIC_ID,
            kind: 'PERFORMED_ACT',
            description: 'Radiographie du grasset (face + profil)',
            catalogItemId: $this->actId('RADIO_THORAX'),
            catalogCode: null,
            posology: null,
            durationDays: null,
            followUpDays: null,
            quantity: 2.0,
            createdByUserId: $vetUserId,
        ));

        $this->commandBus->dispatch(new AddPlanAction(
            consultationId: $consultationId,
            clinicId: self::CLINIC_ID,
            kind: 'FOLLOW_UP_APPOINTMENT',
            description: 'Recontrôle clinique',
            catalogItemId: null,
            catalogCode: null,
            posology: null,
            durationDays: null,
            followUpDays: 14,
            quantity: 1.0,
            createdByUserId: $vetUserId,
        ));

        $this->commandBus->dispatch(new AddPlanAction(
            consultationId: $consultationId,
            clinicId: self::CLINIC_ID,
            kind: 'ADVICE',
            description: 'Repos forcé, promenades calmes en laisse pendant 10 jours',
            catalogItemId: null,
            catalogCode: null,
            posology: null,
            durationDays: null,
            followUpDays: null,
            quantity: 1.0,
            createdByUserId: $vetUserId,
        ));

        $this->commandBus->dispatch(new AddPrescriptionLine(
            consultationId: $consultationId,
            clinicId: self::CLINIC_ID,
            articleId: $this->articleId('MELOXIDYL-1'),
            dose: '0,1 mg/kg',
            frequency: '1×/j',
            durationDays: 7,
            route: 'per os',
            quantity: 1.0,
            createdByUserId: $vetUserId,
        ));

        // Deliberately conflicts with Rex's amoxicillin allergy so the cockpit
        // warning is visible in the demo.
        $this->commandBus->dispatch(new AddPrescriptionLine(
            consultationId: $consultationId,
            clinicId: self::CLINIC_ID,
            articleId: $this->articleId('SYNULOX-500'),
            dose: '12,5 mg/kg',
            frequency: '2×/j',
            durationDays: 7,
            route: 'per os',
            quantity: 1.0,
            createdByUserId: $vetUserId,
        ));

        $this->commandBus->dispatch(new UpdateTeamMemo(
            consultationId: $consultationId,
            clinicId: self::CLINIC_ID,
            memo: 'Prévoir rappel SMS J+13 pour proposer le recontrôle.',
        ));
    }

    private function buildEmptyOpenConsultation(string $admissionId, string $vetUserId): void
    {
        $this->startConsultation($admissionId, $vetUserId);
    }

    private function openAdmission(string $animalId, string $animalName, string $triageNotes): string
    {
        $admissionId = $this->commandBus->dispatch(new OpenAdmissionForWalkIn(
            clinicId: self::CLINIC_ID,
            triageLevel: 'standard',
            knownAnimalId: $animalId,
            animalName: $animalName,
            provisionalLabel: null,
            triageNotes: $triageNotes,
        ));

        \assert(\is_string($admissionId));

        return $admissionId;
    }

    private function startConsultation(string $admissionId, string $vetUserId): string
    {
        $consultationId = $this->commandBus->dispatch(new StartConsultationFromAdmission(
            admissionId: $admissionId,
            startedByUserId: $vetUserId,
        ));

        \assert(\is_string($consultationId));

        return $consultationId;
    }

    private function backdate(string $consultationId, string $modifier): void
    {
        $entity = $this->entityManager->find(
            ConsultationEntity::class,
            Uuid::fromString($consultationId)->toBinary(),
        );

        if (null === $entity) {
            return;
        }

        $startedAt = $entity->getStartedAtUtc()->modify($modifier);
        $entity->setStartedAtUtc($startedAt);
        $entity->setCreatedAtUtc($startedAt);
        $entity->setClosedAtUtc($startedAt->modify('+35 minutes'));
        $entity->setUpdatedAtUtc($startedAt->modify('+35 minutes'));

        $this->entityManager->flush();
    }

    private function veterinarianId(): string
    {
        $vetUser = ClinicUserFactory::repository()->findOneBy(['email' => 'vet@kiveto.local']);

        if (null === $vetUser) {
            throw new \RuntimeException('vet@kiveto.local user not found. ClinicVetStory must be loaded first.');
        }

        return $vetUser->getId()->toRfc4122();
    }

    private function actId(string $code): string
    {
        $act = $this->entityManager->getRepository(ActEntity::class)->findOneBy([
            'code'     => $code,
            'tenantId' => Uuid::fromString(self::CLINIC_ID),
        ]);

        if (null === $act) {
            throw new \RuntimeException(\sprintf('Catalog act "%s" not found.', $code));
        }

        return $act->getId()->toRfc4122();
    }

    private function articleId(string $code): string
    {
        $article = $this->entityManager->getRepository(ArticleEntity::class)->findOneBy([
            'code'     => $code,
            'tenantId' => Uuid::fromString(self::CLINIC_ID),
        ]);

        if (null === $article) {
            throw new \RuntimeException(\sprintf('Catalog article "%s" not found.', $code));
        }

        return $article->getId()->toRfc4122();
    }
}
