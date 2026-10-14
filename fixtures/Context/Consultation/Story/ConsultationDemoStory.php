<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Consultation\Story;

use App\Context\Admission\Application\Command\CloseAdmission\CloseAdmission;
use App\Context\Admission\Application\Command\OpenAdmissionForWalkIn\OpenAdmissionForWalkIn;
use App\Context\Admission\Application\Command\OpenEmergencyAdmission\OpenEmergencyAdmission;
use App\Context\Admission\Application\Command\UpdateAdmissionLocationStatus\UpdateAdmissionLocationStatus;
use App\Context\Admission\Infrastructure\Persistence\Doctrine\Entity\AdmissionEntity;
use App\Context\Animal\Application\Command\AddMedicalAlert\AddMedicalAlert;
use App\Context\Animal\Application\Command\CreateAnimal\CreateAnimal;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\ActEntity;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\ArticleEntity;
use App\Context\Client\Application\Command\CreateClient\ContactMethodDto;
use App\Context\Client\Application\Command\CreateClient\CreateClient;
use App\Context\Consultation\Application\Command\AddConsultationAddendum\AddConsultationAddendum;
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
 * Demo admissions and consultations for both clinics.
 *
 * Two things are covered at once: the cockpit's UI states (an empty open
 * consultation, a rich one with every block filled, closed ones that feed the
 * history panel) and every column of the daily board — waiting room at each
 * triage level, consultation room, surgery, hospitalisation, checkout queue and
 * discharged.
 *
 * Everything goes through the command bus so the demo data is built by the same
 * invariants as production writes.
 */
final class ConsultationDemoStory extends Story
{
    private const string PARIS = ClinicDataStory::INDEPENDENT_CLINIC_ID;
    private const string LYON  = ClinicDataStory::GROUP_CLINIC_ID;

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function build(): void
    {
        $this->seedMedicalAlerts();

        $this->buildParisBoard($this->userId('vet@kiveto.local'));
        $this->buildLyonBoard($this->userId('vet.lyon@kiveto.local'));
    }

    /**
     * The allergy labels are molecule names on purpose: the prescription warning
     * is a plain label match against the article's active substances, so
     * "Amoxicilline" fires on Synulox while a class name such as "Pénicilline"
     * would not.
     */
    private function seedMedicalAlerts(): void
    {
        $alerts = [
            [self::PARIS, AnimalDataStory::ANIMAL_REX_ID, 'ALLERGY', 'Amoxicilline', 'Œdème facial constaté après une cure en 2024.'],
            [self::PARIS, AnimalDataStory::ANIMAL_REX_ID, 'CHRONIC_CONDITION', 'Arthrose', 'Grasset postérieur droit, suivi semestriel.'],
            [self::PARIS, AnimalDataStory::ANIMAL_MINOU_ID, 'CHRONIC_CONDITION', 'Insuffisance rénale', 'Stade 2 IRIS, contrôle trimestriel.'],
            [self::PARIS, AnimalDataStory::ANIMAL_MAX_ID, 'ALLERGY', 'Méloxicam', 'Vomissements répétés après administration orale.'],
            [self::PARIS, AnimalDataStory::ANIMAL_LUNA_ID, 'CHRONIC_CONDITION', 'Cardiomyopathie hypertrophique', 'Écho cardiaque annuelle, traitement au long cours.'],
            [self::PARIS, AnimalDataStory::ANIMAL_ROCKY_ID, 'CHRONIC_CONDITION', 'Épilepsie idiopathique', 'Crises espacées, dernier épisode il y a 8 mois.'],
            [self::PARIS, AnimalDataStory::ANIMAL_FILOU_ID, 'ALLERGY', 'Sélamectine', 'Réaction cutanée locale à la pipette.'],
            [self::LYON, AnimalDataStory::ANIMAL_FELIX_ID, 'CHRONIC_CONDITION', 'Diabète sucré', 'Insulinothérapie, courbe glycémique tous les 3 mois.'],
            [self::LYON, AnimalDataStory::ANIMAL_MOKA_ID, 'ALLERGY', 'Enrofloxacine', 'Urticaire généralisée en 2025.'],
            [self::LYON, AnimalDataStory::ANIMAL_CHARLIE_ID, 'CHRONIC_CONDITION', 'Dysplasie coxo-fémorale', 'Bilatérale, prise en charge médicale.'],
            [self::LYON, AnimalDataStory::ANIMAL_COCO_ID, 'CHRONIC_CONDITION', 'Dermatite atopique', 'Poussées saisonnières au printemps.'],
        ];

        foreach ($alerts as [$clinicId, $animalId, $kind, $label, $note]) {
            $this->commandBus->dispatch(new AddMedicalAlert(
                clinicId: $clinicId,
                animalId: $animalId,
                kind: $kind,
                label: $label,
                note: $note,
            ));
        }
    }

    private function buildParisBoard(string $vetUserId): void
    {
        // One admission per animal: a clinic cannot have two active patients for
        // the same animal, and Rex's two consultations share his visit.
        $rex   = $this->openAdmission(self::PARIS, AnimalDataStory::ANIMAL_REX_ID, 'Rex', 'standard', 'Boiterie postérieure droite');
        $minou = $this->openAdmission(self::PARIS, AnimalDataStory::ANIMAL_MINOU_ID, 'Minou', 'standard', 'Consultation de contrôle');

        // Oldest first so the history of the newer ones is populated.
        $this->buildClosedConsultation(self::PARIS, $rex, $vetUserId);
        $this->buildRichOpenConsultation(self::PARIS, $rex, $vetUserId);
        $this->buildEmptyOpenConsultation($minou, $vetUserId);

        // Waiting room, one card per triage level.
        $rocky = $this->openAdmission(self::PARIS, AnimalDataStory::ANIMAL_ROCKY_ID, 'Rocky', 'vital_emergency', 'Crise convulsive survenue à domicile il y a 20 minutes.');
        $max   = $this->openAdmission(self::PARIS, AnimalDataStory::ANIMAL_MAX_ID, 'Max', 'priority', 'Vomissements répétés depuis ce matin, abattement.');
        $filou = $this->openAdmission(self::PARIS, AnimalDataStory::ANIMAL_FILOU_ID, 'Filou', 'standard', 'Contrôle post-opératoire, retrait des points.');

        // Surgery and hospitalisation columns.
        $luna = $this->openAdmission(self::PARIS, AnimalDataStory::ANIMAL_LUNA_ID, 'Luna', 'standard', 'Stérilisation programmée.');
        $this->buildSurgeryConsultation(self::PARIS, $luna, $vetUserId);
        $this->moveTo(self::PARIS, $luna, 'in_surgery');

        $caramel = $this->openAdmission(self::PARIS, AnimalDataStory::ANIMAL_CARAMEL_ID, 'Caramel', 'priority', 'Pancréatite aiguë, sous perfusion.');
        $this->moveTo(self::PARIS, $caramel, 'in_hospitalization');

        // Checkout queue: consultation closed, patient waiting to pay.
        $pepere = $this->openAdmission(self::PARIS, AnimalDataStory::ANIMAL_PEPERE_ID, 'Pépère', 'standard', 'Vaccination annuelle.');
        $this->buildBillableConsultation(
            self::PARIS,
            $pepere,
            $vetUserId,
            'Rappel vaccinal annuel et bilan de routine.',
            ['Vaccination', 'Bilan annuel'],
            [['VACC_CHPPIL', 'Vaccination CHPPiL', 1.0], ['CONS_STD', 'Consultation standard', 1.0]],
            'Rappel CHPPiL effectué, examen clinique sans anomalie.',
        );

        // Already paid and gone: fills the "Partis" section of the exit column.
        $noisette = $this->openAdmission(self::PARIS, AnimalDataStory::ANIMAL_NOISETTE_ID, 'Noisette', 'standard', 'Retrait de fils.');
        $this->buildBillableConsultation(
            self::PARIS,
            $noisette,
            $vetUserId,
            'Retrait des points de suture à J+12.',
            ['Suivi post-opératoire'],
            [['CONS_SUIVI', 'Consultation de suivi', 1.0]],
            'Cicatrisation complète, points retirés. Aucun soin supplémentaire.',
        );
        $this->closeAdmission(self::PARIS, $noisette, 'released_to_owner');

        $cannelle = $this->openAdmission(self::PARIS, AnimalDataStory::ANIMAL_CANNELLE_ID, 'Cannelle', 'standard', 'Vermifugation et pesée.');
        $this->buildBillableConsultation(
            self::PARIS,
            $cannelle,
            $vetUserId,
            'Vermifugation semestrielle.',
            ['Prévention'],
            [['VERMIF', 'Vermifugation', 1.0], ['CONS_STD', 'Consultation standard', 1.0]],
            'Vermifuge administré, poids stable.',
        );
        $this->closeAdmission(self::PARIS, $cannelle, 'released_to_owner');

        $this->buildBusyParisFlow($vetUserId);

        // Arrival times spread over the morning, so the board's chronos, the
        // red « long wait » and the emergency banner (> 5 min) all show.
        $this->backdateAdmission($rex, 55, 40);
        $this->backdateAdmission($minou, 35, 22);
        $this->backdateAdmission($luna, 70, 45);
        $this->backdateAdmission($rocky, 18, 18);
        $this->backdateAdmission($max, 12, 12);
        $this->backdateAdmission($filou, 17, 17);
        $this->backdateAdmission($pepere, 50, 6);
    }

    /**
     * The extra patients of Paris' busy day on the flow board: a second
     * emergency admitted before anyone could identify it, a longer normal
     * queue, a third patient in a room, a second one at the desk and more
     * departures. Their animals are created here, after the day's bookings,
     * so none of them also waits in the « Planifié » column.
     */
    private function buildBusyParisFlow(string $vetUserId): void
    {
        $simba = $this->createPatient(self::PARIS, 'Inès', 'Torres', '+33698765432', 'Simba', 'cat', 'Maine coon', '2022-05-14');
        $oslo  = $this->createPatient(self::PARIS, 'Julien', 'Girard', '+33612345678', 'Oslo', 'dog', 'Labrador', '2020-03-02');
        $plume = $this->createPatient(self::PARIS, 'Paul', 'Weiss', '+33677889900', 'Plume', 'cat', 'Chartreux', '2014-09-21');
        $pixel = $this->createPatient(self::PARIS, 'Linh', 'Nguyen', '+33766554433', 'Pixel', 'nac', 'Bélier nain', '2024-01-10');
        $bisou = $this->createPatient(self::PARIS, 'Emma', 'Roux', '+33644556677', 'Bisou', 'cat', 'Européen', '2023-06-30');

        $unknown = $this->commandBus->dispatch(new OpenEmergencyAdmission(
            clinicId: self::PARIS,
            intakeChannel: 'emergency',
            triageLevel: 'emergency',
            provisionalLabel: 'Urgence ' . new \DateTimeImmutable('-8 minutes', new \DateTimeZone('Europe/Paris'))->format('H:i'),
            knownAnimalId: null,
            animalName: null,
            observedSpecies: 'Chien',
            observedColor: null,
            presenterName: null,
            presenterPhone: null,
            presenterRole: null,
            physicalDescription: null,
            triageNotes: 'Trauma / AVP — renversé devant la clinique.',
        ));
        \assert(\is_string($unknown));
        $this->backdateAdmission($unknown, 8, 8);

        $simbaVisit = $this->openAdmission(self::PARIS, $simba, 'Simba', 'standard', 'Boiterie antérieure gauche.');
        $this->backdateAdmission($simbaVisit, 6, 6);

        $osloVisit = $this->openAdmission(self::PARIS, $oslo, 'Oslo', 'standard', 'Otite externe, se gratte l\'oreille droite.');
        $this->startConsultation($osloVisit, $vetUserId);
        $this->backdateAdmission($osloVisit, 28, 14);

        $plumeVisit = $this->openAdmission(self::PARIS, $plume, 'Plume', 'standard', 'Contrôle insuffisance rénale.');
        $this->buildBillableConsultation(
            self::PARIS,
            $plumeVisit,
            $vetUserId,
            'Contrôle trimestriel de l\'insuffisance rénale.',
            ['Suivi chronique'],
            [['CONS_SUIVI', 'Consultation de suivi', 1.0]],
            'Créatinine stable, poursuite du régime rénal.',
        );
        $this->backdateAdmission($plumeVisit, 40, 3);

        foreach ([[$pixel, 'Pixel', 'Coupe des griffes.', 150], [$bisou, 'Bisou', 'Rappel vaccinal typhus-coryza.', 95]] as [$animalId, $name, $motif, $minutesAgo]) {
            $visit = $this->openAdmission(self::PARIS, $animalId, $name, 'standard', $motif);
            $this->closeAdmission(self::PARIS, $visit, 'released_to_owner');
            $this->backdateAdmission($visit, $minutesAgo, $minutesAgo, $minutesAgo - 25);
        }
    }

    private function createPatient(
        string $clinicId,
        string $firstName,
        string $lastName,
        string $phone,
        string $animal,
        string $species,
        string $breed,
        string $birthDate,
    ): string {
        $clientId = $this->commandBus->dispatch(new CreateClient(
            clinicId: $clinicId,
            firstName: $firstName,
            lastName: $lastName,
            contactMethods: [new ContactMethodDto(type: 'phone', label: 'mobile', value: $phone, isPrimary: true)],
        ));
        \assert(\is_string($clientId));

        $animalId = $this->commandBus->dispatch(new CreateAnimal(
            clinicId: $clinicId,
            name: $animal,
            species: $species,
            sex: 'unknown',
            reproductiveStatus: 'unknown',
            isMixedBreed: false,
            breedName: $breed,
            birthDate: $birthDate,
            color: null,
            photoUrl: null,
            microchipNumber: null,
            tattooNumber: null,
            passportNumber: null,
            registryType: 'none',
            registryNumber: null,
            registryReference: null,
            lifeStatus: 'alive',
            deceasedAt: null,
            missingSince: null,
            transferStatus: 'none',
            soldAt: null,
            givenAt: null,
            auxiliaryContactFirstName: null,
            auxiliaryContactLastName: null,
            auxiliaryContactPhoneNumber: null,
            primaryOwnerClientId: $clientId,
            secondaryOwnerClientIds: [],
        ));
        \assert(\is_string($animalId));

        return $animalId;
    }

    /**
     * Moves an admission's arrival (and the time it entered its current
     * place) back in time; a departure also gets its leaving time.
     */
    private function backdateAdmission(string $admissionId, int $openedMinutesAgo, int $placeMinutesAgo, ?int $closedMinutesAgo = null): void
    {
        $entity = $this->entityManager->find(AdmissionEntity::class, Uuid::fromString($admissionId));

        if (null === $entity) {
            return;
        }

        $now = $entity->getCreatedAt();
        $entity->setOpenedAt($now->modify(\sprintf('-%d minutes', $openedMinutesAgo)));
        $entity->setLocationStatusEnteredAt($now->modify(\sprintf('-%d minutes', $placeMinutesAgo)));

        if (null !== $closedMinutesAgo) {
            $entity->setClosedAt($now->modify(\sprintf('-%d minutes', $closedMinutesAgo)));
        }

        $this->entityManager->flush();
    }

    private function buildLyonBoard(string $vetUserId): void
    {
        $felix = $this->openAdmission(self::LYON, AnimalDataStory::ANIMAL_FELIX_ID, 'Felix', 'standard', 'Suivi diabétique, courbe glycémique.');
        $this->buildLyonHistoryConsultation($felix, $vetUserId);
        $this->buildLyonOpenConsultation($felix, $vetUserId);

        $this->openAdmission(self::LYON, AnimalDataStory::ANIMAL_COCO_ID, 'Coco', 'priority', 'Prurit intense et léchage des pattes.');
        $this->openAdmission(self::LYON, AnimalDataStory::ANIMAL_JAVA_ID, 'Java', 'standard', 'Boiterie légère depuis hier soir.');
        $this->openAdmission(self::LYON, AnimalDataStory::ANIMAL_SUSHI_ID, 'Sushi', 'vital_emergency', 'Chute de plusieurs étages, détresse respiratoire.');

        $moka = $this->openAdmission(self::LYON, AnimalDataStory::ANIMAL_MOKA_ID, 'Moka', 'emergency', 'Blocage urinaire suspecté, sondage en cours.');
        $this->moveTo(self::LYON, $moka, 'in_hospitalization');

        $gribouille = $this->openAdmission(self::LYON, AnimalDataStory::ANIMAL_GRIBOUILLE_ID, 'Gribouille', 'standard', 'Coupe des incisives sous anesthésie.');
        $this->moveTo(self::LYON, $gribouille, 'in_surgery');

        $charlie = $this->openAdmission(self::LYON, AnimalDataStory::ANIMAL_CHARLIE_ID, 'Charlie', 'standard', 'Contrôle de la dysplasie, radiographies.');
        $this->buildBillableConsultation(
            self::LYON,
            $charlie,
            $vetUserId,
            'Contrôle annuel de la dysplasie coxo-fémorale.',
            ['Suivi chronique', 'Imagerie'],
            [['CONS_SUIVI', 'Consultation de suivi', 1.0], ['RADIO_MEMBRE', 'Radiographie des hanches', 2.0]],
            'Arthrose coxo-fémorale stable. Poursuite du traitement, contrôle dans 6 mois.',
            prescribe: [['MELOXIDYL-1', '0,1 mg/kg', '1×/j', 30, 'per os', 2.0]],
        );

        $nala = $this->openAdmission(self::LYON, AnimalDataStory::ANIMAL_NALA_ID, 'Nala', 'standard', 'Vaccination typhus-coryza.');
        $this->buildBillableConsultation(
            self::LYON,
            $nala,
            $vetUserId,
            'Rappel vaccinal typhus-coryza.',
            ['Vaccination'],
            [['VACC_TYPHUS', 'Vaccination typhus-coryza', 1.0]],
            'Rappel effectué. Prochaine échéance dans 12 mois.',
        );
        $this->closeAdmission(self::LYON, $nala, 'released_to_owner');

        $buddy = $this->openAdmission(self::LYON, AnimalDataStory::ANIMAL_BUDDY_ID, 'Buddy', 'standard', 'Détartrage programmé.');
        $this->buildBillableConsultation(
            self::LYON,
            $buddy,
            $vetUserId,
            'Détartrage sous anesthésie.',
            ['Dentaire'],
            [['DETARTRAGE', 'Détartrage complet', 1.0], ['HOSP_JOUR', 'Surveillance post-anesthésique', 1.0]],
            'Détartrage réalisé, réveil sans incident.',
        );
        $this->closeAdmission(self::LYON, $buddy, 'released_to_owner');
    }

    private function buildClosedConsultation(string $clinicId, string $admissionId, string $vetUserId): void
    {
        $consultationId = $this->startConsultation($admissionId, $vetUserId);

        $this->commandBus->dispatch(new RecordChiefComplaint(
            consultationId: $consultationId,
            clinicId: $clinicId,
            chiefComplaint: 'Bilan annuel, rappel vaccinal CHPPiL.',
        ));

        $this->commandBus->dispatch(new RecordVitals(
            consultationId: $consultationId,
            clinicId: $clinicId,
            weightKg: 28.8,
            temperatureC: 38.4,
        ));

        $this->commandBus->dispatch(new UpdateConsultationMotifs(
            consultationId: $consultationId,
            clinicId: $clinicId,
            labels: ['Vaccination', 'Bilan annuel'],
        ));

        $this->commandBus->dispatch(new AddPlanAction(
            consultationId: $consultationId,
            clinicId: $clinicId,
            kind: 'PERFORMED_ACT',
            description: 'Vaccination CHPPiL',
            catalogItemId: $this->actId($clinicId, 'VACC_CHPPIL'),
            catalogCode: null,
            posology: null,
            durationDays: null,
            followUpDays: null,
            quantity: 1.0,
            createdByUserId: $vetUserId,
        ));

        // A closed record with every block filled: the read-only cockpit shows
        // results instead of toggles, and an addendum signed after closing.
        $this->commandBus->dispatch(new UpdateSubjectiveText(
            consultationId: $consultationId,
            clinicId: $clinicId,
            text: 'Visite annuelle. Le propriétaire signale une mauvaise haleine depuis quelques semaines ; appétit et transit normaux.',
        ));

        $this->commandBus->dispatch(new RecordTypedVital(
            consultationId: $consultationId,
            clinicId: $clinicId,
            type: 'HEART_RATE',
            value: '88',
            recordedByUserId: $vetUserId,
        ));

        foreach ([['GENERAL', 'NORMAL', null], ['CARDIOVASCULAR', 'NORMAL', null], ['DENTAL', 'ANOMALY', 'Tartre modéré sur les carnassières, gingivite légère.']] as [$system, $status, $notes]) {
            $this->commandBus->dispatch(new RecordExamSystem(
                consultationId: $consultationId,
                clinicId: $clinicId,
                system: $system,
                status: $status,
                notes: $notes,
                structuredData: [],
                recordedByUserId: $vetUserId,
            ));
        }

        $this->commandBus->dispatch(new UpdateObjectiveObservations(
            consultationId: $consultationId,
            clinicId: $clinicId,
            text: 'Chien en bon état général, note d\'état corporel 5/9.',
        ));

        $this->commandBus->dispatch(new AddDiagnosis(
            consultationId: $consultationId,
            clinicId: $clinicId,
            code: '',
            label: 'Maladie parodontale stade 1',
            certainty: 'PROBABLE',
            note: null,
            isPrimary: true,
            source: 'MANUAL',
            createdByUserId: $vetUserId,
        ));

        $this->commandBus->dispatch(new AddPrescriptionLine(
            consultationId: $consultationId,
            clinicId: $clinicId,
            articleId: $this->articleId($clinicId, 'FRONTLINE-CB'),
            dose: '1 pipette',
            frequency: '1×/mois',
            durationDays: 90,
            route: 'cutanée',
            quantity: 1.0,
            createdByUserId: $vetUserId,
        ));

        foreach ([['ADVICE', 'Brossage des dents 3×/semaine', null], ['FOLLOW_UP_APPOINTMENT', 'Rappel vaccin annuel', 365]] as [$kind, $description, $followUp]) {
            $this->commandBus->dispatch(new AddPlanAction(
                consultationId: $consultationId,
                clinicId: $clinicId,
                kind: $kind,
                description: $description,
                catalogItemId: null,
                catalogCode: null,
                posology: null,
                durationDays: null,
                followUpDays: $followUp,
                quantity: 1.0,
                createdByUserId: $vetUserId,
            ));
        }

        $this->commandBus->dispatch(new CloseConsultation(
            consultationId: $consultationId,
            closedByUserId: $vetUserId,
            summary: 'Vaccination CHPPiL réalisée. Poids stable à 28,8 kg. Aucun signe clinique anormal.',
        ));

        $this->commandBus->dispatch(new AddConsultationAddendum(
            consultationId: $consultationId,
            clinicId: $clinicId,
            content: 'Le propriétaire a rappelé : détartrage à programmer à l\'automne, devis remis à l\'accueil.',
            createdByUserId: $vetUserId,
        ));

        // Backdate so the history panel shows a plausible timeline.
        $this->backdate($consultationId, '-5 months');
    }

    private function buildRichOpenConsultation(string $clinicId, string $admissionId, string $vetUserId): void
    {
        $consultationId = $this->startConsultation($admissionId, $vetUserId);

        $this->commandBus->dispatch(new RecordChiefComplaint(
            consultationId: $consultationId,
            clinicId: $clinicId,
            chiefComplaint: 'Boiterie postérieure droite intermittente depuis 5 jours.',
        ));

        $this->commandBus->dispatch(new UpdateConsultationMotifs(
            consultationId: $consultationId,
            clinicId: $clinicId,
            labels: ['Boiterie', 'Suivi chronique'],
        ));

        $this->commandBus->dispatch(new RecordVitals(
            consultationId: $consultationId,
            clinicId: $clinicId,
            weightKg: 28.5,
            temperatureC: 38.6,
        ));

        foreach ([['HEART_RATE', '96'], ['RESPIRATORY_RATE', '22'], ['PAIN_SCORE', '2']] as [$type, $value]) {
            $this->commandBus->dispatch(new RecordTypedVital(
                consultationId: $consultationId,
                clinicId: $clinicId,
                type: $type,
                value: $value,
                recordedByUserId: $vetUserId,
            ));
        }

        $this->commandBus->dispatch(new UpdateSubjectiveText(
            consultationId: $consultationId,
            clinicId: $clinicId,
            text: 'Le propriétaire rapporte une boiterie postérieure droite intermittente depuis 5 jours, '
                . "plus marquée le matin au lever et après les sorties longues.\n\n"
                . 'Pas de chute identifiée. Appétit conservé, comportement habituel.',
        ));

        $this->commandBus->dispatch(new RecordExamSystem(
            consultationId: $consultationId,
            clinicId: $clinicId,
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
                clinicId: $clinicId,
                system: $system,
                status: 'NORMAL',
                notes: null,
                structuredData: [],
                recordedByUserId: $vetUserId,
            ));
        }

        $this->commandBus->dispatch(new UpdateObjectiveObservations(
            consultationId: $consultationId,
            clinicId: $clinicId,
            text: 'Animal alerte et coopératif. Démarche raide en fin d\'examen, '
                . 'hésitation à charger le postérieur droit.',
        ));

        $this->commandBus->dispatch(new AddDiagnosis(
            consultationId: $consultationId,
            clinicId: $clinicId,
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
            clinicId: $clinicId,
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
            clinicId: $clinicId,
            kind: 'PERFORMED_ACT',
            description: 'Radiographie du grasset (face + profil)',
            catalogItemId: $this->actId($clinicId, 'RADIO_MEMBRE'),
            catalogCode: null,
            posology: null,
            durationDays: null,
            followUpDays: null,
            quantity: 2.0,
            createdByUserId: $vetUserId,
        ));

        $this->commandBus->dispatch(new AddPlanAction(
            consultationId: $consultationId,
            clinicId: $clinicId,
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
            clinicId: $clinicId,
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
            clinicId: $clinicId,
            articleId: $this->articleId($clinicId, 'MELOXIDYL-1'),
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
            clinicId: $clinicId,
            articleId: $this->articleId($clinicId, 'SYNULOX-500'),
            dose: '12,5 mg/kg',
            frequency: '2×/j',
            durationDays: 7,
            route: 'per os',
            quantity: 1.0,
            createdByUserId: $vetUserId,
        ));

        $this->commandBus->dispatch(new UpdateTeamMemo(
            consultationId: $consultationId,
            clinicId: $clinicId,
            memo: 'Prévoir rappel SMS J+13 pour proposer le recontrôle.',
        ));
    }

    private function buildEmptyOpenConsultation(string $admissionId, string $vetUserId): void
    {
        $this->startConsultation($admissionId, $vetUserId);
    }

    /**
     * A partly filled open consultation: enough to look alive in the cockpit
     * without repeating the exhaustive Paris one.
     */
    private function buildLyonOpenConsultation(string $admissionId, string $vetUserId): void
    {
        $consultationId = $this->startConsultation($admissionId, $vetUserId);

        $this->commandBus->dispatch(new RecordChiefComplaint(
            consultationId: $consultationId,
            clinicId: self::LYON,
            chiefComplaint: 'Suivi de diabète sucré, polyurie-polydipsie encore présente.',
        ));

        $this->commandBus->dispatch(new UpdateConsultationMotifs(
            consultationId: $consultationId,
            clinicId: self::LYON,
            labels: ['Suivi chronique', 'Bilan sanguin'],
        ));

        $this->commandBus->dispatch(new RecordVitals(
            consultationId: $consultationId,
            clinicId: self::LYON,
            weightKg: 4.6,
            temperatureC: 38.2,
        ));

        $this->commandBus->dispatch(new UpdateSubjectiveText(
            consultationId: $consultationId,
            clinicId: self::LYON,
            text: 'Propriétaire observant sur les injections. Boit toujours beaucoup, '
                . "appétit conservé.\n\nPas d'épisode d'hypoglycémie signalé depuis le dernier contrôle.",
        ));

        $this->commandBus->dispatch(new RecordExamSystem(
            consultationId: $consultationId,
            clinicId: self::LYON,
            system: 'URINARY',
            status: 'ANOMALY',
            notes: 'Vessie modérément distendue, miction fréquente rapportée.',
            structuredData: [],
            recordedByUserId: $vetUserId,
        ));

        $this->commandBus->dispatch(new AddPlanAction(
            consultationId: $consultationId,
            clinicId: self::LYON,
            kind: 'PERFORMED_ACT',
            description: 'Bilan rénal et glycémique',
            catalogItemId: $this->actId(self::LYON, 'BILAN_RENAL'),
            catalogCode: null,
            posology: null,
            durationDays: null,
            followUpDays: null,
            quantity: 1.0,
            createdByUserId: $vetUserId,
        ));

        $this->commandBus->dispatch(new UpdateTeamMemo(
            consultationId: $consultationId,
            clinicId: self::LYON,
            memo: 'Résultats du laboratoire attendus en fin de journée.',
        ));
    }

    private function buildLyonHistoryConsultation(string $admissionId, string $vetUserId): void
    {
        $consultationId = $this->startConsultation($admissionId, $vetUserId);

        $this->commandBus->dispatch(new RecordChiefComplaint(
            consultationId: $consultationId,
            clinicId: self::LYON,
            chiefComplaint: 'Amaigrissement et soif excessive depuis un mois.',
        ));

        $this->commandBus->dispatch(new RecordVitals(
            consultationId: $consultationId,
            clinicId: self::LYON,
            weightKg: 4.2,
            temperatureC: 38.5,
        ));

        $this->commandBus->dispatch(new AddDiagnosis(
            consultationId: $consultationId,
            clinicId: self::LYON,
            code: 'E.END.11',
            label: 'Diabète sucré',
            certainty: 'CERTAIN',
            note: 'Glycémie et fructosamine élevées.',
            isPrimary: true,
            source: 'MANUAL',
            createdByUserId: $vetUserId,
        ));

        $this->commandBus->dispatch(new AddPlanAction(
            consultationId: $consultationId,
            clinicId: self::LYON,
            kind: 'PERFORMED_ACT',
            description: 'Bilan sanguin complet',
            catalogItemId: $this->actId(self::LYON, 'BILAN_SANG'),
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
            summary: 'Diabète sucré confirmé. Mise en place de l\'insulinothérapie et suivi rapproché.',
        ));

        $this->backdate($consultationId, '-3 months');
    }

    /**
     * A consultation closed today with billable acts: the visit lands in the
     * checkout queue with a non-zero amount to collect.
     *
     * @param list<string>                                                              $motifs
     * @param list<array{0: string, 1: string, 2: float}>                               $acts      catalog code, label, quantity
     * @param list<array{0: string, 1: string, 2: string, 3: int, 4: string, 5: float}> $prescribe
     *                                                                                             catalog code, dose, frequency, days, route, quantity
     */
    private function buildBillableConsultation(
        string $clinicId,
        string $admissionId,
        string $vetUserId,
        string $chiefComplaint,
        array $motifs,
        array $acts,
        string $summary,
        array $prescribe = [],
    ): void {
        $consultationId = $this->startConsultation($admissionId, $vetUserId);

        $this->commandBus->dispatch(new RecordChiefComplaint(
            consultationId: $consultationId,
            clinicId: $clinicId,
            chiefComplaint: $chiefComplaint,
        ));

        $this->commandBus->dispatch(new UpdateConsultationMotifs(
            consultationId: $consultationId,
            clinicId: $clinicId,
            labels: $motifs,
        ));

        foreach ($acts as [$code, $description, $quantity]) {
            $this->commandBus->dispatch(new AddPlanAction(
                consultationId: $consultationId,
                clinicId: $clinicId,
                kind: 'PERFORMED_ACT',
                description: $description,
                catalogItemId: $this->actId($clinicId, $code),
                catalogCode: null,
                posology: null,
                durationDays: null,
                followUpDays: null,
                quantity: $quantity,
                createdByUserId: $vetUserId,
            ));
        }

        foreach ($prescribe as [$code, $dose, $frequency, $days, $route, $quantity]) {
            $this->commandBus->dispatch(new AddPrescriptionLine(
                consultationId: $consultationId,
                clinicId: $clinicId,
                articleId: $this->articleId($clinicId, $code),
                dose: $dose,
                frequency: $frequency,
                durationDays: $days,
                route: $route,
                quantity: $quantity,
                createdByUserId: $vetUserId,
            ));
        }

        $this->commandBus->dispatch(new CloseConsultation(
            consultationId: $consultationId,
            closedByUserId: $vetUserId,
            summary: $summary,
        ));

        $this->shortenToRealisticDuration($consultationId);
    }

    /**
     * A consultation closed by the fixtures lasts a few milliseconds: start it
     * 18 minutes before its closing, as the cockpit's timer would show.
     */
    private function shortenToRealisticDuration(string $consultationId): void
    {
        $entity = $this->entityManager->find(
            ConsultationEntity::class,
            Uuid::fromString($consultationId)->toBinary(),
        );

        if (null === $entity || null === $entity->getClosedAtUtc()) {
            return;
        }

        $startedAt = $entity->getClosedAtUtc()->modify('-18 minutes -24 seconds');
        $entity->setStartedAtUtc($startedAt);
        $entity->setCreatedAtUtc($startedAt);

        $this->entityManager->flush();
    }

    /**
     * The pre-operative consultation of a patient on the table: it carries the
     * anaesthetic protocol, which is where a controlled substance shows up.
     */
    private function buildSurgeryConsultation(string $clinicId, string $admissionId, string $vetUserId): void
    {
        $consultationId = $this->startConsultation($admissionId, $vetUserId);

        $this->commandBus->dispatch(new RecordChiefComplaint(
            consultationId: $consultationId,
            clinicId: $clinicId,
            chiefComplaint: 'Stérilisation programmée, examen pré-anesthésique.',
        ));

        $this->commandBus->dispatch(new UpdateConsultationMotifs(
            consultationId: $consultationId,
            clinicId: $clinicId,
            labels: ['Chirurgie', 'Pré-anesthésie'],
        ));

        $this->commandBus->dispatch(new RecordVitals(
            consultationId: $consultationId,
            clinicId: $clinicId,
            weightKg: 3.9,
            temperatureC: 38.3,
        ));

        $this->commandBus->dispatch(new AddPlanAction(
            consultationId: $consultationId,
            clinicId: $clinicId,
            kind: 'PERFORMED_ACT',
            description: 'Stérilisation femelle',
            catalogItemId: $this->actId($clinicId, 'STERIL_FEMELLE'),
            catalogCode: null,
            posology: null,
            durationDays: null,
            followUpDays: null,
            quantity: 1.0,
            createdByUserId: $vetUserId,
        ));

        $this->commandBus->dispatch(new AddPrescriptionLine(
            consultationId: $consultationId,
            clinicId: $clinicId,
            articleId: $this->articleId($clinicId, 'MEDETO-1'),
            dose: '40 µg/kg',
            frequency: 'dose unique',
            durationDays: 1,
            route: 'IM',
            quantity: 1.0,
            createdByUserId: $vetUserId,
        ));

        $this->commandBus->dispatch(new AddPrescriptionLine(
            consultationId: $consultationId,
            clinicId: $clinicId,
            articleId: $this->articleId($clinicId, 'MELOXIDYL-1'),
            dose: '0,05 mg/kg',
            frequency: '1×/j',
            durationDays: 3,
            route: 'per os',
            quantity: 1.0,
            createdByUserId: $vetUserId,
        ));
    }

    private function openAdmission(
        string $clinicId,
        string $animalId,
        string $animalName,
        string $triageLevel,
        string $triageNotes,
    ): string {
        $admissionId = $this->commandBus->dispatch(new OpenAdmissionForWalkIn(
            clinicId: $clinicId,
            triageLevel: $triageLevel,
            knownAnimalId: $animalId,
            animalName: $animalName,
            provisionalLabel: null,
            triageNotes: $triageNotes,
        ));

        \assert(\is_string($admissionId));

        return $admissionId;
    }

    private function moveTo(string $clinicId, string $admissionId, string $locationStatus): void
    {
        $this->commandBus->dispatch(new UpdateAdmissionLocationStatus(
            clinicId: $clinicId,
            admissionId: $admissionId,
            newLocationStatus: $locationStatus,
        ));
    }

    private function closeAdmission(string $clinicId, string $admissionId, string $reason): void
    {
        $this->commandBus->dispatch(new CloseAdmission(
            clinicId: $clinicId,
            admissionId: $admissionId,
            closureReason: $reason,
        ));
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

    private function userId(string $email): string
    {
        $user = ClinicUserFactory::repository()->findOneBy(['email' => $email]);

        if (null === $user) {
            throw new \RuntimeException(\sprintf('%s user not found. The staff stories must be loaded first.', $email));
        }

        return $user->getId()->toRfc4122();
    }

    private function actId(string $clinicId, string $code): string
    {
        $act = $this->entityManager->getRepository(ActEntity::class)->findOneBy([
            'code'     => $code,
            'tenantId' => Uuid::fromString($clinicId),
        ]);

        if (null === $act) {
            throw new \RuntimeException(\sprintf('Catalog act "%s" not found.', $code));
        }

        return $act->getId()->toRfc4122();
    }

    private function articleId(string $clinicId, string $code): string
    {
        $article = $this->entityManager->getRepository(ArticleEntity::class)->findOneBy([
            'code'     => $code,
            'tenantId' => Uuid::fromString($clinicId),
        ]);

        if (null === $article) {
            throw new \RuntimeException(\sprintf('Catalog article "%s" not found.', $code));
        }

        return $article->getId()->toRfc4122();
    }
}
