<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Client\Story;

use App\Context\Admission\Application\Command\CloseAdmission\CloseAdmission;
use App\Context\Admission\Application\Command\OpenAdmissionForWalkIn\OpenAdmissionForWalkIn;
use App\Context\Animal\Application\Command\AddMedicalAlert\AddMedicalAlert;
use App\Context\Animal\Application\Command\CreateAnimal\CreateAnimal;
use App\Context\Animal\Application\Command\RecordPreventiveCare\RecordPreventiveCare;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\Service\InvoiceNumberGeneratorInterface;
use App\Context\Billing\Domain\ValueObject\AdmissionId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\PayerId;
use App\Context\Billing\Domain\ValueObject\Payment;
use App\Context\Billing\Domain\ValueObject\PaymentId;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\ArticleEntity;
use App\Context\Client\Domain\ValueObject\CommunicationDirection;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Context\Consultation\Application\Command\AddDiagnosis\AddDiagnosis;
use App\Context\Consultation\Application\Command\AddPrescriptionLine\AddPrescriptionLine;
use App\Context\Consultation\Application\Command\CloseConsultation\CloseConsultation;
use App\Context\Consultation\Application\Command\RecordChiefComplaint\RecordChiefComplaint;
use App\Context\Consultation\Application\Command\RecordVitals\RecordVitals;
use App\Context\Consultation\Application\Command\StartConsultationFromAdmission\StartConsultationFromAdmission;
use App\Context\Consultation\Application\Command\UpdateConsultationMotifs\UpdateConsultationMotifs;
use App\Context\Consultation\Application\Command\UpdateObjectiveObservations\UpdateObjectiveObservations;
use App\Context\Prescription\Application\Command\AllowPrescriptionRenewals\AllowPrescriptionRenewals;
use App\Context\Prescription\Application\Command\IssuePrescription\IssuePrescription;
use App\Context\Prescription\Application\Command\PreparePrescriptionForConsultation\PreparePrescriptionForConsultation;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity\AppointmentEntity;
use App\Fixtures\Context\Client\Factory\ClientCommunicationEntityFactory;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Fixtures\Context\Client\Factory\ClientNoteEntityFactory;
use App\Fixtures\Context\Client\Factory\ContactMethodEntityFactory;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * Three Paris households, one per state of the client sheet
 * (docs/frontend-theme/kiveto-theme/client-v-topbar*.html):
 *
 * - « normal » — M. Thierry Marchand: two animals and a departed one, a cat
 *   in consultation today, two years of visits (one missed), an ordonnance,
 *   a counter sale, two appointments to come;
 * - « busy » — Mme Sophie Garnier: five animals (the cards scroll), a dog in
 *   consultation today, a long history with two unpaid invoices, missed
 *   appointments, logged exchanges, four appointments to come;
 * - « vierge » — Mme Inès Barbier: created today with her puppy, nothing on
 *   file yet but a first appointment tomorrow.
 *
 * Loaded last: the visits go through the command bus (admission, consultation,
 * ordonnance) and are then moved back in time; their invoices are written
 * with the date of the visit so that their numbers carry the right year.
 */
final class ClientSheetStatesStory extends Story
{
    public const string CLIENT_NORMAL_ID = '01936e19-c5c5-7000-8000-000000000001';
    public const string CLIENT_BUSY_ID   = '01936e19-c5c5-7000-8000-000000000002';
    public const string CLIENT_VIERGE_ID = '01936e19-c5c5-7000-8000-000000000003';

    private const string PARIS = ClinicDataStory::INDEPENDENT_CLINIC_ID;
    private const string TZ    = 'Europe/Paris';

    /** @var array<string, string> e-mail => user id */
    private array $users = [];

    private \DateTimeImmutable $today;

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly InvoiceNumberGeneratorInterface $numbers,
        private readonly ClockInterface $clock,
    ) {
    }

    public function build(): void
    {
        // Relative to the application clock, as the commands below are: a test
        // that freezes it gets the same three households around its own today.
        $this->today = $this->clock->now()->setTimezone(new \DateTimeZone(self::TZ))->setTime(0, 0);

        $this->buildNormal();
        $this->buildBusy();
        $this->buildVierge();
    }

    // ═════════ « normal » ═════════

    private function buildNormal(): void
    {
        $client = self::CLIENT_NORMAL_ID;
        $this->client(
            $client,
            'mister',
            'Thierry',
            'Marchand',
            '-6 years -4 months',
            '+33794839282',
            'thierry.marchand@gmail.com',
            ['18 rue des Martyrs', null, '75009'],
            ['madam', 'Claire', 'Marchand', 'spouse', '+33612457890', '-7 months'],
            ['appointment_reminders' => ['sms'], 'clinical_documents' => ['email'], 'billing' => ['sms', 'email']]
        );

        $minette = $this->animal($client, 'Minette', 'cat', 'female', 'neutered', 'Maine coon', '-9 years -2 months');
        $gaston  = $this->animal($client, 'Gaston', 'dog', 'male', 'intact', 'Beagle', '-3 years -5 months');
        $this->animal($client, 'Tigrou', 'cat', 'male', 'neutered', 'Européen', '-14 years', deceasedAt: '-18 months');

        $this->alert($minette, 'CHRONIC_CONDITION', 'IRC · IRIS 2', 'Contrôle trimestriel, aliment rénal.');
        $this->alert($gaston, 'ALLERGY', 'Pénicillines', 'Urticaire après une prise d’amoxicilline.');

        $this->care($minette, 'vaccination', 'Typhus-Coryza', '-155 days', '+210 days');
        $this->care($minette, 'internal_parasite', 'Vermifugation', '-100 days', '-10 days');
        $this->care($gaston, 'vaccination', 'CHPPiL', '-378 days', '+12 days');
        $this->care($gaston, 'internal_parasite', 'Vermifugation', '-60 days', '+30 days');

        $rousseau = $this->user('vet@kiveto.local');
        $bernard  = $this->user('vet2@kiveto.local');
        $dupont   = $this->user('contractor@kiveto.local');

        $this->visit($client, 'Thierry Marchand', $gaston, 'Gaston', $bernard, '-690 days 11:00', ['Urticaire'], 'Plaques et prurit deux heures après une prise d’amoxicilline.', null, 'Arrêt de l’amoxicilline, corticoïde injectable. Allergie notée au dossier.', [11.2, null], [['Consultation', 1, 5000], ['Dexaméthasone injectable', 1, 1200]], 'CASH', diagnosis: 'Réaction allergique aux pénicillines');
        $this->visit($client, 'Thierry Marchand', $minette, 'Minette', $bernard, '-500 days 09:30', ['Bilan IRC', 'Diagnostic'], 'Polyuro-polydipsie, amaigrissement de 300 g en 6 mois.', 'Déshydratation 5 %, reins petits et irréguliers à la palpation.', 'Aliment rénal, contrôle à 3 mois.', [5.9, 38.5], [['Consultation', 1, 5000], ['Biochimie et SDMA', 1, 8800], ['Analyse d’urine', 1, 4200], ['Échographie abdominale', 1, 8800]], 'CARD', diagnosis: 'Insuffisance rénale chronique, stade IRIS 2', prescribe: [['FOOD-HILLS-KD', '70 g par jour', 'en 2 repas', 90, 'per os', 3.0]]);
        $this->visit($client, 'Thierry Marchand', $gaston, 'Gaston', $dupont, '-378 days 18:00', ['Vaccination', 'Rappel annuel'], 'Rappel annuel.', 'RAS, bon état général.', 'CHPPiL et rage faits. Prochain rappel dans un an.', [13.4, 38.6], [['Vaccination CHPPiL', 1, 6200], ['Vaccination antirabique', 1, 3400]], 'CARD');
        $this->missed($client, $gaston, $bernard, '-398 days 17:30', 'Consultation', 'Non venu, sans prévenir · SMS de relance envoyé le lendemain');
        $this->visit($client, 'Thierry Marchand', $minette, 'Minette', $bernard, '-320 days 18:15', ['Contrôle IRC'], 'Contrôle à 6 mois du diagnostic.', 'Déshydratation légère (5 %), muqueuses roses.', 'Poursuite de l’aliment rénal. Contrôle dans 3 mois.', [5.9, 38.5], [['Consultation de suivi', 1, 3500], ['Bilan rénal (urée, créatinine, SDMA)', 1, 8890]], 'CHECK', diagnosis: 'Insuffisance rénale chronique, IRIS 2, stable', prescribe: [['FOOD-HILLS-KD', '70 g par jour', 'en 2 repas', 180, 'per os', 6.0]]);
        $this->visit($client, 'Thierry Marchand', $minette, 'Minette', $rousseau, '-155 days 17:45', ['Vaccination', 'Bilan rénal'], 'Rappel annuel et contrôle rénal.', 'Bon état général, muqueuses roses, pas de déshydratation.', 'Vaccin fait. IRC stable (créatinine 2,0). Poursuite du traitement.', [5.8, 38.4], [['Vaccination typhus-coryza', 1, 5800], ['Bilan rénal (urée, créatinine, SDMA)', 1, 8800], ['Milbemax chat — comprimé', 1, 900]], 'CARD');
        $this->counterSale($client, 'Thierry Marchand', '-75 days 12:10', [['Bravecto chat — pipette', 1, 2490]], 'CARD');

        // Today: Minette is in the consultation room, her invoice still a draft.
        $this->visit($client, 'Thierry Marchand', $minette, 'Minette', $rousseau, 'now', ['Contrôle IRC'], 'Contrôle trimestriel de l’insuffisance rénale.', null, null, [5.8, 38.6], [['Consultation de suivi', 1, 3500], ['Bilan rénal (urée, créatinine, SDMA)', 1, 8800], ['Semintra 4 mg/mL — flacon 30 mL', 1, 3990], ['Hill’s k/d — sac 3 kg', 1, 4900]], null, open: true);

        $this->upcoming($client, $gaston, $dupont, '+12 days 18:00', 'Rappel vaccin annuel', 'Rappel SMS prévu la veille à 18:00');
        $this->upcoming($client, $minette, $bernard, '+40 days 17:30', 'Bilan rénal de contrôle', 'Venir à jeun · apporter un échantillon d’urine');

        $this->note($client, "Préfère les rendez-vous en fin de journée.\nMinette stressée en salle d’attente : la faire entrer directement si possible.", 'Julie Martin', '-7 months');
    }

    // ═════════ « busy » ═════════

    private function buildBusy(): void
    {
        $client = self::CLIENT_BUSY_ID;
        $this->client(
            $client,
            'madam',
            'Sophie',
            'Garnier',
            '-11 years -8 months',
            '+33648217730',
            'sophie.garnier@orange.fr',
            ['12 rue Lepic', 'Bâtiment B, 3e étage', '75018'],
            ['mister', 'Julien', 'Garnier', 'spouse', '+33631904412', '-2 months'],
            ['appointment_reminders' => ['sms'], 'vaccination_reminders' => ['sms'], 'billing' => ['sms', 'email']],
            landline: '+33142523318'
        );

        $oscar  = $this->animal($client, 'Oscar', 'dog', 'male', 'neutered', 'Labrador', '-11 years -3 months');
        $nala   = $this->animal($client, 'Nala', 'cat', 'female', 'neutered', 'Européen', '-4 years -1 month');
        $pepito = $this->animal($client, 'Pepito', 'dog', 'male', 'intact', 'Jack Russell', '-2 years -2 months');
        $coco   = $this->animal($client, 'Coco', 'nac', 'male', 'unknown', 'Lapin bélier', '-5 years');
        $this->animal($client, 'Filou', 'cat', 'male', 'neutered', 'Européen', '-15 years', deceasedAt: '-23 months');

        $this->alert($oscar, 'CHRONIC_CONDITION', 'Diabète', 'Insulinothérapie, courbe de glycémie tous les 3 mois.');
        $this->alert($nala, 'ALLERGY', 'Poulet', 'Allergie alimentaire suspectée, régime d’éviction.');

        $this->care($oscar, 'vaccination', 'CHPPiL', '-200 days', '+165 days');
        $this->care($oscar, 'internal_parasite', 'Vermifugation', '-110 days', '-20 days');
        $this->care($nala, 'vaccination', 'Typhus-Coryza', '-358 days', '+7 days');
        $this->care($pepito, 'internal_parasite', 'Vermifugation', '-95 days', '-5 days');
        $this->care($coco, 'vaccination', 'VHD et myxomatose', '-359 days', '+6 days');

        $rousseau = $this->user('vet@kiveto.local');
        $bernard  = $this->user('vet2@kiveto.local');
        $dupont   = $this->user('contractor@kiveto.local');
        $who      = 'Sophie Garnier';

        $this->visit($client, $who, $oscar, 'Oscar', $dupont, '-490 days 08:30', ['Chirurgie', 'Exérèse'], 'Masse de 6 cm au flanc droit.', null, 'Lipome bénin confirmé à l’histologie.', [33.8, 38.4], [['Chirurgie cutanée', 1, 18000], ['Anesthésie générale', 1, 9500], ['Histologie', 1, 6800]], 'CHECK', diagnosis: 'Lipome sous-cutané');
        $this->visit($client, $who, $pepito, 'Pepito', $dupont, '-400 days 17:30', ['Vaccination', 'Primo-vaccination'], 'Primo-vaccination et identification.', null, 'CHPPiL fait, puce posée.', [5.9, 38.7], [['Vaccination CHPPiL', 1, 6200], ['Identification par puce', 1, 6500]], 'CARD');
        $this->visit($client, $who, $coco, 'Coco', $dupont, '-360 days 16:00', ['Vaccination'], 'VHD et myxomatose.', null, 'Vaccins faits.', [2.4, null], [['Vaccination lapin', 1, 5200]], 'CARD');
        $this->visit($client, $who, $oscar, 'Oscar', $rousseau, '-315 days 11:00', ['Boiterie', 'Imagerie'], 'Raideur des antérieurs au lever.', 'Douleur à la flexion des coudes.', 'Anti-inflammatoire au long cours, contrôle dans 2 mois.', [34.2, 38.5], [['Consultation', 1, 5000], ['Radiographie des coudes', 1, 7000]], 'CARD', diagnosis: 'Arthrose des coudes', prescribe: [['MELOXIDYL-1', '0,1 mg/kg', '1×/j', 60, 'per os', 2.0]]);
        $this->visit($client, $who, $nala, 'Nala', $dupont, '-270 days 18:00', ['Vaccination'], 'Rappel annuel.', null, 'Typhus-coryza fait.', [4.4, 38.6], [['Vaccination typhus-coryza', 1, 5800]], 'CARD');
        $this->visit($client, $who, $oscar, 'Oscar', $bernard, '-245 days 10:00', ['Diabète', 'Bilan sanguin'], 'Boit et urine beaucoup, amaigrissement de 3 kg.', 'Cataracte débutante, état général conservé.', 'Insuline matin et soir, aliment adapté, courbe à 2 mois.', [34.0, 38.6], [['Consultation', 1, 5000], ['Biochimie complète', 1, 8800], ['Fructosamine', 1, 4200], ['Analyse d’urine', 1, 4200]], 'CARD', diagnosis: 'Diabète sucré insulinodépendant', prescribe: [['FOOD-RC', '360 g par jour', 'en 2 repas', 60, 'per os', 2.0]]);
        $this->missed($client, $pepito, $bernard, '-226 days 17:00', 'Consultation', 'Annulé moins de 2 h avant');
        $this->visit($client, $who, $coco, 'Coco', $dupont, '-209 days 14:00', ['Dentaire'], 'Joue gonflée, mange moins.', null, 'Drainage, antibiotique 10 jours.', [2.3, null], [['Consultation NAC', 1, 5500], ['Drainage d’abcès', 1, 4800]], 'CARD', diagnosis: 'Abcès de la 2e prémolaire');
        $this->visit($client, $who, $nala, 'Nala', $rousseau, '-194 days 18:15', ['Toux'], 'Toux sèche depuis 5 jours.', null, 'Anti-inflammatoire 5 jours.', [4.3, 38.9], [['Consultation', 1, 5000]], 'CARD', diagnosis: 'Trachéite');
        $this->visit($client, $who, $oscar, 'Oscar', $bernard, '-175 days 09:30', ['Hospitalisation', 'Courbe de glycémie'], 'Ajustement de la dose d’insuline.', '9 glycémies de 8:00 à 18:00, nadir 1,6 g/L à 14:00.', 'Insuline 10 → 12 UI.', [34.5, 38.4], [['Hospitalisation à la journée', 1, 4500], ['Glycémie capillaire', 9, 600]], 'CARD');
        $this->visit($client, $who, $pepito, 'Pepito', $dupont, '-153 days 16:30', ['Vaccination', 'Rappel CHPPiL'], 'Rappel annuel.', null, 'CHPPiL fait. Vermifuge donné.', [7.1, 38.5], [['Vaccination CHPPiL', 1, 6200], ['Milbemax chien — comprimé', 1, 850]], 'CASH');
        $this->visit($client, $who, $oscar, 'Oscar', $bernard, '-130 days 10:00', ['Contrôle diabète'], 'Contrôle à 4 mois du diagnostic.', null, 'Insuline passée à 12 UI matin et soir.', [34.9, 38.6], [['Consultation de suivi', 1, 3500], ['Fructosamine', 1, 4200]], 'CARD', diagnosis: 'Diabète sucré, équilibre partiel');
        $this->counterSale($client, $who, '-115 days 12:00', [['Caninsulin 40 UI/mL — flacon 10 mL', 2, 5490], ['Seringues U40 — boîte de 30', 2, 1450]], 'CARD');
        $this->missed($client, $coco, $rousseau, '-104 days 18:00', 'Consultation', 'Non venu, sans prévenir · SMS de relance envoyé le lendemain');
        $this->visit($client, $who, $nala, 'Nala', $dupont, '-88 days 09:00', ['Vomissements'], 'Vomissements 2 à 3 fois par semaine depuis 2 mois.', 'Bon état général, palpation abdominale souple.', 'Régime d’éviction 8 semaines (protéine hydrolysée).', [4.3, 38.5], [['Consultation', 1, 5000], ['Hill’s z/d chat — sac 2 kg', 1, 2450]], null, diagnosis: 'Allergie alimentaire suspectée', prescribe: [['CERENIA-10', '1 mg/kg', '1×/j', 5, 'SC', 1.0]]);
        $this->visit($client, $who, $oscar, 'Oscar', $bernard, '-76 days 17:45', ['Bilan thyroïdien'], 'Prise de poids, poil terne, abattement.', null, 'Contrôle T4 dans 6 semaines.', [35.1, 38.3], [['Consultation de suivi', 1, 3500], ['T4 totale et TSH', 1, 6400]], 'CARD', diagnosis: 'Hypothyroïdie');
        $this->visit($client, $who, $pepito, 'Pepito', $rousseau, '-63 days 11:30', ['Boiterie'], 'Boiterie depuis une chute au parc.', 'Douleur à la flexion du carpe, pas de crépitation.', 'Repos strict 10 jours, anti-inflammatoire 5 jours.', [7.2, 38.6], [['Consultation', 1, 5000], ['Radiographie du carpe', 1, 4600]], null, diagnosis: 'Entorse du carpe gauche', prescribe: [['MELOXIDYL-1', '0,1 mg/kg', '1×/j', 5, 'per os', 1.0]]);

        // Today: Oscar is in the consultation room.
        $this->visit($client, $who, $oscar, 'Oscar', $rousseau, 'now', ['Contrôle diabète'], 'Contrôle à 2 mois de l’ajustement d’insuline.', null, null, [34.6, 38.7], [['Consultation de suivi', 1, 3500], ['Fructosamine', 1, 4200], ['Caninsulin 40 UI/mL — flacon 10 mL', 2, 5490], ['Seringues U40 — boîte de 30', 1, 1450]], null, open: true);

        $this->upcoming($client, $oscar, $bernard, '+3 days 09:00', 'Courbe de glycémie', 'Hospitalisation de jour · venir à jeun avec l’insuline', 480);
        $this->upcoming($client, $nala, $dupont, '+7 days 18:30', 'Rappel typhus-coryza', 'Rappel SMS prévu la veille à 18:00');
        $this->upcoming($client, $pepito, $dupont, '+14 days 08:00', 'Castration', 'À jeun depuis la veille 20:00 · dépôt à 8:00, sortie vers 17:00', 60);
        $this->upcoming($client, $coco, $rousseau, '+25 days 17:00', 'Coupe des dents', 'Incisives trop longues · apporter du foin dans la caisse');

        $this->note($client, "Famille très investie, appelle souvent pour Oscar : orienter vers le Dr Bernard qui suit le diabète.\nPréfère être rappelée après 18:00.\nDeux factures en attente : accord pour régler en 2 fois à partir du mois prochain.\nPepito tire fort en laisse : le garder en caisse en salle d’attente.", 'Rousseau', '-12 days');

        ClientCommunicationEntityFactory::new()->forClient(self::PARIS, $client)
            ->via(ContactChannel::PHONE, CommunicationDirection::OUTBOUND)
            ->about('Factures en attente de Pepito et Nala', 'Accord pour un règlement en deux fois à partir du mois prochain.')
            ->loggedBy('Julie Martin', $this->utc('-10 days 17:20'))
            ->create()
        ;
        ClientCommunicationEntityFactory::new()->forClient(self::PARIS, $client)
            ->via(ContactChannel::SMS, CommunicationDirection::INBOUND)
            ->about('Nala a encore vomi ce matin', 'Conseil donné : poursuivre le régime d’éviction, revoir si cela se répète.')
            ->loggedBy('Rousseau', $this->utc('-80 days 09:40'))
            ->create()
        ;
    }

    // ═════════ « vierge » ═════════

    private function buildVierge(): void
    {
        $client = self::CLIENT_VIERGE_ID;
        $this->client(
            $client,
            'madam',
            'Inès',
            'Barbier',
            'now',
            '+33671421895',
            'ines.barbier@outlook.fr',
            ['27 rue des Trois Frères', null, '75018'],
            null,
            ['appointment_reminders' => ['sms'], 'clinical_documents' => ['email'], 'billing' => ['sms', 'email']]
        );

        $moka = $this->animal($client, 'Moka', 'dog', 'female', 'intact', 'Golden retriever', '-10 weeks');

        $this->upcoming($client, $moka, $this->user('contractor@kiveto.local'), '+1 day 09:30', 'Première consultation', 'Bilan de santé et première injection · apporter le carnet de l’éleveur');
    }

    // ═════════ Builders ═════════

    /**
     * @param array{0: string, 1: ?string, 2: string}                                      $address
     * @param array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}|null $person
     * @param array<string, list<string>>                                                  $consents
     */
    private function client(
        string $id,
        string $civility,
        string $firstName,
        string $lastName,
        string $since,
        string $mobile,
        string $email,
        array $address,
        ?array $person,
        array $consents,
        ?string $landline = null,
    ): void {
        $factory = ClientEntityFactory::new()
            ->withId($id)
            ->withClinicId(self::PARIS)
            ->withName($firstName, $lastName)
            ->withCivility($civility)
            ->since($this->utc($since))
            ->active()
            ->withPostalAddress(streetLine1: $address[0], city: 'Paris', countryCode: 'FR', streetLine2: $address[1], postalCode: $address[2], region: 'Île-de-France')
            ->withCommunicationPreferences('sms', $consents, $this->utc($since))
        ;

        if (null !== $person) {
            $factory = $factory->withAuthorizedPerson($person[0], $person[1], $person[2], $person[3], $person[4], $this->utc($person[5]));
        }

        $factory->create();

        ContactMethodEntityFactory::new()->forClient($id)->mobile()->phone($mobile)->primary()->create();

        if (null !== $landline) {
            ContactMethodEntityFactory::new()->forClient($id)->home()->phone($landline)->create();
        }

        ContactMethodEntityFactory::new()->forClient($id)->email($email)->home()->primary()->create();
    }

    private function animal(
        string $clientId,
        string $name,
        string $species,
        string $sex,
        string $reproductiveStatus,
        string $breed,
        string $born,
        ?string $deceasedAt = null,
    ): string {
        $animalId = $this->commandBus->dispatch(new CreateAnimal(
            clinicId: self::PARIS,
            name: $name,
            species: $species,
            sex: $sex,
            reproductiveStatus: $reproductiveStatus,
            isMixedBreed: false,
            breedName: $breed,
            birthDate: $this->at($born)->format('Y-m-d'),
            color: null,
            photoUrl: null,
            microchipNumber: null,
            tattooNumber: null,
            passportNumber: null,
            registryType: 'none',
            registryNumber: null,
            registryReference: null,
            lifeStatus: null === $deceasedAt ? 'alive' : 'deceased',
            deceasedAt: null === $deceasedAt ? null : $this->at($deceasedAt)->format('Y-m-d'),
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

    private function alert(string $animalId, string $kind, string $label, string $note): void
    {
        $this->commandBus->dispatch(new AddMedicalAlert(clinicId: self::PARIS, animalId: $animalId, kind: $kind, label: $label, note: $note));
    }

    private function care(string $animalId, string $kind, string $label, string $administered, string $due): void
    {
        $this->commandBus->dispatch(new RecordPreventiveCare(
            clinicId: self::PARIS,
            animalId: $animalId,
            kind: $kind,
            label: $label,
            administeredOn: $this->at($administered)->format('Y-m-d'),
            batchNumber: null,
            nextDueOn: $this->at($due)->format('Y-m-d'),
            authorName: 'Rousseau',
        ));
    }

    /**
     * A visit: admission, consultation (motifs, vitals, diagnosis, synthesis,
     * medicines) and its invoice, moved back to $when. An open visit stays on
     * the board with a draft invoice; a null payment method leaves the invoice
     * issued and unpaid.
     *
     * @param list<string>                                                              $motifs
     * @param array{0: ?float, 1: ?float}                                               $vitals    weight, temperature
     * @param list<array{0: string, 1: int, 2: int}>                                    $lines     label, quantity, unit gross (cents)
     * @param list<array{0: string, 1: string, 2: string, 3: int, 4: string, 5: float}> $prescribe catalog code, dose, frequency, days, route, quantity
     */
    private function visit(
        string $clientId,
        string $payerName,
        string $animalId,
        string $animalName,
        string $vetUserId,
        string $when,
        array $motifs,
        string $complaint,
        ?string $observations,
        ?string $summary,
        array $vitals,
        array $lines,
        ?string $paymentMethod,
        bool $open = false,
        ?string $diagnosis = null,
        array $prescribe = [],
    ): void {
        $at = $this->at($when);

        $admissionId = $this->commandBus->dispatch(new OpenAdmissionForWalkIn(
            clinicId: self::PARIS,
            triageLevel: 'standard',
            knownAnimalId: $animalId,
            animalName: $animalName,
            provisionalLabel: null,
            triageNotes: $complaint,
        ));
        \assert(\is_string($admissionId));

        $consultationId = $this->commandBus->dispatch(new StartConsultationFromAdmission(admissionId: $admissionId, startedByUserId: $vetUserId));
        \assert(\is_string($consultationId));

        $this->commandBus->dispatch(new RecordChiefComplaint(consultationId: $consultationId, clinicId: self::PARIS, chiefComplaint: $complaint));
        $this->commandBus->dispatch(new UpdateConsultationMotifs(consultationId: $consultationId, clinicId: self::PARIS, labels: $motifs));
        $this->commandBus->dispatch(new RecordVitals(consultationId: $consultationId, clinicId: self::PARIS, weightKg: $vitals[0], temperatureC: $vitals[1]));

        if (null !== $observations) {
            $this->commandBus->dispatch(new UpdateObjectiveObservations(consultationId: $consultationId, clinicId: self::PARIS, text: $observations));
        }

        if (null !== $diagnosis) {
            $this->commandBus->dispatch(new AddDiagnosis(
                consultationId: $consultationId,
                clinicId: self::PARIS,
                code: null,
                label: $diagnosis,
                certainty: 'CERTAIN',
                note: null,
                isPrimary: true,
                source: 'MANUAL',
                createdByUserId: $vetUserId,
            ));
        }

        foreach ($prescribe as [$code, $dose, $frequency, $days, $route, $quantity]) {
            $this->commandBus->dispatch(new AddPrescriptionLine(
                consultationId: $consultationId,
                clinicId: self::PARIS,
                articleId: $this->articleId($code),
                dose: $dose,
                frequency: $frequency,
                durationDays: $days,
                route: $route,
                quantity: $quantity,
                createdByUserId: $vetUserId,
            ));
        }

        if (!$open) {
            $this->commandBus->dispatch(new CloseConsultation(consultationId: $consultationId, closedByUserId: $vetUserId, summary: $summary ?? ''));
            $this->commandBus->dispatch(new CloseAdmission(clinicId: self::PARIS, admissionId: $admissionId, closureReason: 'released_to_owner'));

            if ([] !== $prescribe) {
                $prescriptionId = $this->commandBus->dispatch(new PreparePrescriptionForConsultation(clinicId: self::PARIS, consultationId: $consultationId, prescriberUserId: $vetUserId));
                \assert(\is_string($prescriptionId));
                $this->commandBus->dispatch(new AllowPrescriptionRenewals(self::PARIS, $prescriptionId, 1));
                $this->commandBus->dispatch(new IssuePrescription(self::PARIS, $prescriptionId));
                $this->backdatePrescription($prescriptionId, $at->modify('+30 minutes'));
            }

            $this->backdateVisit($consultationId, $admissionId, $at);
        }

        $this->invoice($clientId, $payerName, $admissionId, $at->modify($open ? '+0 minutes' : '+40 minutes'), $lines, $open ? null : ($paymentMethod ?? 'UNPAID'));
    }

    /**
     * @param list<array{0: string, 1: int, 2: int}> $lines label, quantity, unit gross (cents)
     */
    private function counterSale(string $clientId, string $payerName, string $when, array $lines, string $paymentMethod): void
    {
        $this->invoice($clientId, $payerName, null, $this->at($when), $lines, $paymentMethod);
    }

    /**
     * @param list<array{0: string, 1: int, 2: int}> $lines
     * @param ?string                                $payment null: draft · 'UNPAID': issued · else paid with that method
     */
    private function invoice(string $clientId, string $payerName, ?string $admissionId, \DateTimeImmutable $at, array $lines, ?string $payment): void
    {
        $clinic  = ClinicId::fromString(self::PARIS);
        $eur     = CurrencyCode::fromString('EUR');
        $invoice = Invoice::draft(
            id: InvoiceId::fromString(Uuid::v7()->toRfc4122()),
            clinicId: $clinic,
            admissionId: null === $admissionId ? null : AdmissionId::fromString($admissionId),
            payer: Payer::create(PayerId::fromString($clientId), $payerName, ['Paris']),
            currency: $eur,
            now: $at,
        );

        foreach ($lines as [$label, $quantity, $unitGross]) {
            $gross = $quantity * $unitGross;
            $net   = (int) round($gross / 1.2);

            $invoice->addLine(InvoiceLine::create(
                id: InvoiceLineId::fromString(Uuid::v7()->toRfc4122()),
                label: $label,
                code: null,
                quantity: (string) $quantity,
                unitNetPrice: Money::fromMinorUnits((int) round($unitGross / 1.2), $eur),
                taxCategoryCode: 'veterinary.act.consultation',
                taxRatePercent: '20',
                netAmount: Money::fromMinorUnits($net, $eur),
                taxAmount: Money::fromMinorUnits($gross - $net, $eur),
                grossAmount: Money::fromMinorUnits($gross, $eur),
                source: InvoiceLineSource::MANUAL,
                sourceReference: null,
            ), $at);
        }

        if (null !== $payment) {
            $invoice->issue($this->numbers->next($clinic, (int) $at->format('Y')), $at);

            if ('UNPAID' !== $payment) {
                $invoice->recordPayment(Payment::create(
                    id: PaymentId::fromString(Uuid::v7()->toRfc4122()),
                    method: PaymentMethod::from($payment),
                    amount: Money::fromMinorUnits(array_sum(array_map(static fn (array $l): int => $l[1] * $l[2], $lines)), $eur),
                    receivedAt: $at,
                    reference: null,
                ), $at);
            }
        }

        $this->invoices->save($invoice);
    }

    private function missed(string $clientId, string $animalId, string $vetUserId, string $when, string $reason, string $notes): void
    {
        $this->appointment($clientId, $animalId, $vetUserId, $when, $reason, $notes, AppointmentStatus::NO_SHOW, 30);
    }

    private function upcoming(string $clientId, string $animalId, string $vetUserId, string $when, string $reason, string $notes, int $minutes = 30): void
    {
        $this->appointment($clientId, $animalId, $vetUserId, $when, $reason, $notes, AppointmentStatus::PLANNED, $minutes);
    }

    private function appointment(string $clientId, string $animalId, string $vetUserId, string $when, string $reason, string $notes, AppointmentStatus $status, int $minutes): void
    {
        AppointmentEntityFactory::new()
            ->withClinicId(self::PARIS)
            ->withPractitionerUserId($vetUserId)
            ->startingAt($this->at($when)->setTimezone(new \DateTimeZone('UTC')), $minutes)
            ->withStatus($status)
            ->afterInstantiate(static function (AppointmentEntity $entity) use ($clientId, $animalId, $reason, $notes): void {
                $entity->setOwnerId(Uuid::fromString($clientId));
                $entity->setAnimalId(Uuid::fromString($animalId));
                $entity->setReason($reason);
                $entity->setNotes($notes);
            })
            ->create()
        ;
    }

    private function note(string $clientId, string $content, string $author, string $when): void
    {
        ClientNoteEntityFactory::new()->forClient(self::PARIS, $clientId)
            ->writtenBy($author, $this->utc($when))
            ->create(['content' => $content])
        ;
    }

    // ═════════ Moving a visit back in time ═════════

    private function backdateVisit(string $consultationId, string $admissionId, \DateTimeImmutable $at): void
    {
        $start = $at->setTimezone(new \DateTimeZone('UTC'));
        $end   = $start->modify('+35 minutes');

        $this->connection->executeStatement(
            'UPDATE consultation__consultations SET started_at_utc = :start, created_at_utc = :start, closed_at_utc = :end, updated_at_utc = :end WHERE id = :id',
            ['start' => $start->format('Y-m-d H:i:s'), 'end' => $end->format('Y-m-d H:i:s'), 'id' => Uuid::fromString($consultationId)->toBinary()],
        );
        $this->connection->executeStatement(
            'UPDATE admission__admissions SET opened_at = :start, created_at = :start, location_status_entered_at = :end, closed_at = :end, updated_at = :end WHERE id = :id',
            ['start' => $start->modify('-10 minutes')->format('Y-m-d H:i:s'), 'end' => $end->format('Y-m-d H:i:s'), 'id' => Uuid::fromString($admissionId)->toBinary()],
        );
        $this->connection->executeStatement(
            'UPDATE consultation__diagnoses SET created_at_utc = :start WHERE consultation_id = :id',
            ['start' => $start->format('Y-m-d H:i:s'), 'id' => Uuid::fromString($consultationId)->toBinary()],
        );
    }

    private function backdatePrescription(string $prescriptionId, \DateTimeImmutable $at): void
    {
        $this->connection->executeStatement(
            'UPDATE prescription__prescriptions SET issued_at = :at, created_at = :at, updated_at = :at,
                    valid_until = DATE_ADD(:at, INTERVAL validity_span_days DAY)
             WHERE id = :id',
            ['at' => $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), 'id' => Uuid::fromString($prescriptionId)->toBinary()],
        );
    }

    // ═════════ Lookups ═════════

    /** A moment relative to today in clinic time: '-3 days 17:30', '+1 day 09:30', 'now'. */
    private function at(string $relative): \DateTimeImmutable
    {
        if ('now' === $relative) {
            return $this->clock->now()->setTimezone(new \DateTimeZone(self::TZ));
        }

        return $this->today->modify($relative);
    }

    /** The same moment as a UTC « Y-m-d H:i:s », as the datetime columns store it. */
    private function utc(string $relative): string
    {
        return $this->at($relative)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function user(string $email): string
    {
        if (!isset($this->users[$email])) {
            $user = ClinicUserFactory::repository()->findOneBy(['email' => $email]);

            if (null === $user) {
                throw new \RuntimeException(\sprintf('%s user not found. The staff stories must be loaded first.', $email));
            }

            $this->users[$email] = $user->getId()->toRfc4122();
        }

        return $this->users[$email];
    }

    private function articleId(string $code): string
    {
        $article = $this->entityManager->getRepository(ArticleEntity::class)->findOneBy([
            'code'     => $code,
            'tenantId' => Uuid::fromString(self::PARIS),
        ]);

        if (null === $article) {
            throw new \RuntimeException(\sprintf('Catalog article "%s" not found.', $code));
        }

        return $article->getId()->toRfc4122();
    }
}
