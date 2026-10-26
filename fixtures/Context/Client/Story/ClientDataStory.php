<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Client\Story;

use App\Context\Client\Domain\TaxProfile\CompanyCheckOutcome;
use App\Context\Client\Domain\TaxProfile\VatCheckOutcome;
use App\Context\Client\Domain\ValueObject\ClientTaxStatus;
use App\Context\Client\Domain\ValueObject\CommunicationDirection;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Fixtures\Context\Client\Factory\ClientCommunicationEntityFactory;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Fixtures\Context\Client\Factory\ClientNoteEntityFactory;
use App\Fixtures\Context\Client\Factory\ContactMethodEntityFactory;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use Zenstruck\Foundry\Story;

final class ClientDataStory extends Story
{
    // IDs constants for Animals to reference
    public const CLIENT_SOPHIE_ID   = '01936e19-1111-7000-8000-000000000001';
    public const CLIENT_MARC_ID     = '01936e19-2222-7000-8000-000000000002';
    public const CLIENT_CLAIRE_ID   = '01936e19-3333-7000-8000-000000000003';
    public const CLIENT_PIERRE_ID   = '01936e19-4444-7000-8000-000000000004';
    public const CLIENT_JULIEN_ID   = '01936e19-5555-7000-8000-000000000005';
    public const CLIENT_EMILIE_ID   = '01936e19-6666-7000-8000-000000000006';
    public const CLIENT_THOMAS_ID   = '01936e19-7777-7000-8000-000000000007';
    public const CLIENT_NATHALIE_ID = '01936e19-8888-7000-8000-000000000008';
    public const CLIENT_LAURENT_ID  = '01936e19-9999-7000-8000-000000000009';
    public const CLIENT_ISABELLE_ID = '01936e19-aaaa-7000-8000-00000000000a';

    // UI edge-case fixture: client with many animals (triggers "+N" badge in the clients list)
    public const CLIENT_AURELIE_ID = '01936e19-bbbb-7000-8000-00000000000b';

    // Structures (tax profile): VAT-liable businesses, a shared SIREN, an association, an archived one.
    public const CLIENT_CALANQUES_ID         = '01936e19-cccc-7000-8000-00000000000c';
    public const CLIENT_CENTRE_EQUESTRE_ID   = '01936e19-dddd-7000-8000-00000000000d';
    public const CLIENT_CALANQUES_AUBAGNE_ID = '01936e19-eeee-7000-8000-00000000000e';
    public const CLIENT_REFUGE_ID            = '01936e19-ffff-7000-8000-00000000000f';
    public const CLIENT_GAEC_ID              = '01936e19-1010-7000-8000-000000000010';
    public const CLIENT_HARAS_ID             = '01936e19-1111-7000-8000-000000000011';

    // Professionals whose SIREN plays a scenario of the local e-invoicing
    // platform simulator (status reached by their invoices).
    public const CLIENT_MISTRAL_ID      = '01936e19-1212-7000-8000-000000000012'; // 213 Rejetée
    public const CLIENT_CRAU_ID         = '01936e19-1313-7000-8000-000000000013'; // 210 Refusée
    public const CLIENT_ALPILLES_ID     = '01936e19-1414-7000-8000-000000000014'; // 207 En litige
    public const CLIENT_LUBERON_ID      = '01936e19-1515-7000-8000-000000000015'; // 501 Irrecevable
    public const CLIENT_SAINTE_BAUME_ID = '01936e19-1616-7000-8000-000000000016'; // 200 non transmise

    // Professionals of the Lyon and Cassis establishments, whose company has
    // no e-invoicing platform connection yet.
    public const CLIENT_RHONE_ID   = '01936e19-1717-7000-8000-000000000017';
    public const CLIENT_CALANCO_ID = '01936e19-1818-7000-8000-000000000018';

    public function build(): void
    {
        $parisClinicId = ClinicDataStory::INDEPENDENT_CLINIC_ID;
        $lyonClinicId  = ClinicDataStory::GROUP_CLINIC_ID;

        // ===========================
        // PARIS CLIENTS (5 clients)
        // ===========================

        // 1. Active client with complete address and 2 phones + 1 email
        $client1 = ClientEntityFactory::new()
            ->withId(self::CLIENT_SOPHIE_ID)
            ->withClinicId($parisClinicId)
            ->withName('Sophie', 'Dupont')
            ->active()
            ->withPostalAddress(
                streetLine1: '12 Avenue des Champs-Élysées',
                city: 'Paris',
                countryCode: 'FR',
                postalCode: '75008',
                region: 'Île-de-France',
            )
            // Complete file: authorized person designated and every consent collected.
            ->withAuthorizedPerson('mister', 'Antoine', 'Dupont', 'spouse', '+33688214509', '2025-01-14 10:00:00')
            ->withCommunicationPreferences(
                'sms',
                [
                    'appointment_reminders' => ['sms', 'email'],
                    'vaccination_reminders' => ['sms'],
                    'clinical_documents'    => ['email'],
                    'billing'               => ['email'],
                    'marketing'             => ['email'],
                ],
                '2024-09-09 09:30:00',
            )
            ->create()
        ;

        ContactMethodEntityFactory::new()
            ->forClient($client1->getId()->toRfc4122())
            ->mobile()
            ->phone('+33 6 12 34 56 78')
            ->primary()
            ->create()
        ;

        ContactMethodEntityFactory::new()
            ->forClient($client1->getId()->toRfc4122())
            ->home()
            ->phone('+33 1 42 56 78 90')
            ->create()
        ;

        ContactMethodEntityFactory::new()
            ->forClient($client1->getId()->toRfc4122())
            ->email('sophie.dupont@example.com')
            ->work()
            ->primary()
            ->create()
        ;

        // 2. Active client without address, email only
        $client2 = ClientEntityFactory::new()
            ->withId(self::CLIENT_MARC_ID)
            ->withClinicId($parisClinicId)
            ->withName('Marc', 'Lefebvre')
            ->active()
            ->create()
        ;

        ContactMethodEntityFactory::new()
            ->forClient($client2->getId()->toRfc4122())
            ->email('marc.lefebvre@gmail.com')
            ->home()
            ->primary()
            ->create()
        ;

        // 3. Active client with phone only
        $client3 = ClientEntityFactory::new()
            ->withId(self::CLIENT_CLAIRE_ID)
            ->withClinicId($parisClinicId)
            ->withName('Claire', 'Martin')
            ->active()
            ->withPostalAddress(
                streetLine1: '8 Rue de Rivoli',
                city: 'Paris',
                countryCode: 'FR',
                postalCode: '75004',
            )
            ->create()
        ;

        ContactMethodEntityFactory::new()
            ->forClient($client3->getId()->toRfc4122())
            ->mobile()
            ->phone('+33 7 88 99 11 22')
            ->primary()
            ->create()
        ;

        // 4. ARCHIVED client with complete info
        $client4 = ClientEntityFactory::new()
            ->withId(self::CLIENT_PIERRE_ID)
            ->withClinicId($parisClinicId)
            ->withName('Pierre', 'Moreau')
            ->archived()
            ->withPostalAddress(
                streetLine1: '45 Boulevard Haussmann',
                city: 'Paris',
                countryCode: 'FR',
                postalCode: '75009',
                region: 'Île-de-France',
            )
            ->create()
        ;

        ContactMethodEntityFactory::new()
            ->forClient($client4->getId()->toRfc4122())
            ->mobile()
            ->phone('+33 6 55 44 33 22')
            ->primary()
            ->create()
        ;

        ContactMethodEntityFactory::new()
            ->forClient($client4->getId()->toRfc4122())
            ->email('pierre.moreau@yahoo.fr')
            ->home()
            ->primary()
            ->create()
        ;

        // 5. Active client with international address (Switzerland)
        $client5 = ClientEntityFactory::new()
            ->withId(self::CLIENT_JULIEN_ID)
            ->withClinicId($parisClinicId)
            ->withName('Julien', 'Bernard')
            ->active()
            ->withPostalAddress(
                streetLine1: 'Rue du Rhône 10',
                city: 'Genève',
                countryCode: 'CH',
                postalCode: '1204',
                region: 'Genève',
            )
            ->create()
        ;

        ContactMethodEntityFactory::new()
            ->forClient($client5->getId()->toRfc4122())
            ->mobile()
            ->phone('+41 22 123 45 67')
            ->primary()
            ->create()
        ;

        ContactMethodEntityFactory::new()
            ->forClient($client5->getId()->toRfc4122())
            ->email('julien.bernard@protonmail.com')
            ->work()
            ->primary()
            ->create()
        ;

        // 6 (Paris). UI edge-case: client with many animals — triggers the "+N" overflow badge in the clients list.
        //    Covers the "more than 4 animals" threshold for the animal-badge column.
        $client6Paris = ClientEntityFactory::new()
            ->withId(self::CLIENT_AURELIE_ID)
            ->withClinicId($parisClinicId)
            ->withName('Aurélie', 'Chevalier')
            ->active()
            ->withPostalAddress(
                streetLine1: '3 Impasse du Moulin',
                city: 'Versailles',
                countryCode: 'FR',
                postalCode: '78000',
                region: 'Île-de-France',
            )
            // Partial file: reminders agreed to, marketing never asked, nobody authorized.
            ->withCommunicationPreferences(
                'phone',
                ['appointment_reminders' => ['sms'], 'vaccination_reminders' => ['sms', 'email']],
                '2026-07-20 11:00:00',
            )
            ->create()
        ;

        ContactMethodEntityFactory::new()
            ->forClient($client6Paris->getId()->toRfc4122())
            ->mobile()
            ->phone('+33 6 01 02 03 04')
            ->primary()
            ->create()
        ;

        ContactMethodEntityFactory::new()
            ->forClient($client6Paris->getId()->toRfc4122())
            ->email('aurelie.chevalier@example.com')
            ->home()
            ->primary()
            ->create()
        ;

        // ===========================
        // LYON CLIENTS (5 clients)
        // ===========================

        // 6. Active client with 3 different emails
        $client6 = ClientEntityFactory::new()
            ->withId(self::CLIENT_EMILIE_ID)
            ->withClinicId($lyonClinicId)
            ->withName('Émilie', 'Rousseau')
            ->active()
            ->withPostalAddress(
                streetLine1: '22 Rue de la République',
                city: 'Lyon',
                countryCode: 'FR',
                postalCode: '69002',
                region: 'Auvergne-Rhône-Alpes',
            )
            ->create()
        ;

        ContactMethodEntityFactory::new()
            ->forClient($client6->getId()->toRfc4122())
            ->email('emilie.rousseau@example.com')
            ->work()
            ->primary()
            ->create()
        ;

        ContactMethodEntityFactory::new()
            ->forClient($client6->getId()->toRfc4122())
            ->email('emilie.r@gmail.com')
            ->home()
            ->create()
        ;

        ContactMethodEntityFactory::new()
            ->forClient($client6->getId()->toRfc4122())
            ->mobile()
            ->phone('+33 6 98 76 54 32')
            ->primary()
            ->create()
        ;

        // 7. Simple ARCHIVED client
        $client7 = ClientEntityFactory::new()
            ->withId(self::CLIENT_THOMAS_ID)
            ->withClinicId($lyonClinicId)
            ->withName('Thomas', 'Petit')
            ->archived()
            ->create()
        ;

        ContactMethodEntityFactory::new()
            ->forClient($client7->getId()->toRfc4122())
            ->mobile()
            ->phone('+33 7 11 22 33 44')
            ->primary()
            ->create()
        ;

        // 8. Active client with complete address including line 2
        $client8 = ClientEntityFactory::new()
            ->withId(self::CLIENT_NATHALIE_ID)
            ->withClinicId($lyonClinicId)
            ->withName('Nathalie', 'Leroy')
            ->active()
            ->withPostalAddress(
                streetLine1: '18 Cours Vitton',
                city       : 'Lyon',
                countryCode: 'FR',
                streetLine2: 'Bâtiment B, 3ème étage',
                postalCode : '69006',
                region     : 'Auvergne-Rhône-Alpes',
            )
            ->create()
        ;

        ContactMethodEntityFactory::new()
            ->forClient($client8->getId()->toRfc4122())
            ->home()
            ->phone('+33 4 78 90 12 34')
            ->primary()
            ->create()
        ;

        ContactMethodEntityFactory::new()
            ->forClient($client8->getId()->toRfc4122())
            ->email('nathalie.leroy@outlook.fr')
            ->work()
            ->primary()
            ->create()
        ;

        // 9. Active client with Belgium address
        $client9 = ClientEntityFactory::new()
            ->withId(self::CLIENT_LAURENT_ID)
            ->withClinicId($lyonClinicId)
            ->withName('Laurent', 'Girard')
            ->active()
            ->withPostalAddress(
                streetLine1: 'Avenue Louise 54',
                city: 'Bruxelles',
                countryCode: 'BE',
                postalCode: '1050',
                region: 'Bruxelles-Capitale',
            )
            ->create()
        ;

        ContactMethodEntityFactory::new()
            ->forClient($client9->getId()->toRfc4122())
            ->mobile()
            ->phone('+32 2 123 45 67')
            ->primary()
            ->create()
        ;

        ContactMethodEntityFactory::new()
            ->forClient($client9->getId()->toRfc4122())
            ->email('laurent.girard@skynet.be')
            ->home()
            ->primary()
            ->create()
        ;

        // 10. ARCHIVED client with multiple contacts
        $client10 = ClientEntityFactory::new()
            ->withId(self::CLIENT_ISABELLE_ID)
            ->withClinicId($lyonClinicId)
            ->withName('Isabelle', 'Fontaine')
            ->archived()
            ->withPostalAddress(
                streetLine1: '5 Place Bellecour',
                city: 'Lyon',
                countryCode: 'FR',
                postalCode: '69002',
                region: 'Auvergne-Rhône-Alpes',
            )
            ->create()
        ;

        ContactMethodEntityFactory::new()
            ->forClient($client10->getId()->toRfc4122())
            ->mobile()
            ->phone('+33 6 44 55 66 77')
            ->primary()
            ->create()
        ;

        ContactMethodEntityFactory::new()
            ->forClient($client10->getId()->toRfc4122())
            ->work()
            ->phone('+33 4 72 10 20 30')
            ->create()
        ;

        ContactMethodEntityFactory::new()
            ->forClient($client10->getId()->toRfc4122())
            ->email('isabelle.fontaine@free.fr')
            ->home()
            ->primary()
            ->create()
        ;

        $this->buildProfessionals($parisClinicId);
        $this->createProfessionals($lyonClinicId, [
            [self::CLIENT_RHONE_ID, 'madam', 'Agathe', 'Morel', ClientTaxStatus::FRENCH_BUSINESS, 'Élevage des Monts du Rhône', '694123456', null, true, '+33 4 78 41 23 45', 'contact@monts-du-rhone.fr', ['Chemin des Crêtes', 'Vaugneray', '69670'], null],
        ]);
        $this->createProfessionals(ClinicDataStory::CALM_CLINIC_ID, [
            [self::CLIENT_CALANCO_ID, 'mister', 'Marius', 'Ferrand', ClientTaxStatus::FRENCH_BUSINESS, 'Pension canine de Port-Miou', '831123450', null, true, '+33 4 42 01 12 34', null, ['Avenue de l\'Amiral Ganteaume', 'Cassis', '13260'], null],
        ]);
        $this->buildNotesAndCommunications($parisClinicId);
    }

    /**
     * Structures of the Paris clinic, each billed under its legal name to its
     * contact: a business with and one without VAT number (franchise en
     * base), a second establishment sharing a SIREN (duplicate warning), an
     * association that is not VAT-liable, and an archived business.
     *
     * Verification states, consistent with the fixture registry and VIES:
     * Calanques verified (active, VAT valid), Centre équestre pending, the
     * Aubagne site verified (its name contains the official one: no
     * mismatch), the Haras typed under another name than the registry's and
     * with a VAT number VIES rejects, the GAEC ceased (archived).
     */
    private function buildProfessionals(string $clinicId): void
    {
        $structures = [
            [self::CLIENT_CALANQUES_ID, 'madam', 'Claire', 'Rostand', ClientTaxStatus::FRENCH_BUSINESS, 'Élevage des Calanques', '523456788', 'FR31523456788', true, '+33 4 91 22 33 44', 'contact@elevage-calanques.fr', ['Chemin des Goudes', 'Marseille', '13008'],
                [CompanyCheckOutcome::ACTIVE, 'Élevage des Calanques', 'yesterday 08:12', VatCheckOutcome::VALID, null]],
            [self::CLIENT_CENTRE_EQUESTRE_ID, 'mister', 'Hugo', 'Marchal', ClientTaxStatus::FRENCH_BUSINESS, 'Centre équestre du Lac', '812345676', null, true, '+33 6 45 12 78 90', null, ['Route du Lac', 'Enghien-les-Bains', '95880'],
                [CompanyCheckOutcome::UNCHECKED, null, null, VatCheckOutcome::UNCHECKED, '-5 minutes']],
            // No street: its draft shows the readiness banner at the counter.
            [self::CLIENT_CALANQUES_AUBAGNE_ID, 'mister', 'Paul', 'Rostand', ClientTaxStatus::FRENCH_BUSINESS, 'Élevage des Calanques — site d\'Aubagne', '523456788', null, true, '+33 4 42 03 11 22', null, null,
                [CompanyCheckOutcome::ACTIVE, 'Élevage des Calanques', 'yesterday 08:13', VatCheckOutcome::NOT_APPLICABLE, null]],
            [self::CLIENT_REFUGE_ID, 'madam', 'Inès', 'Perrin', ClientTaxStatus::INDIVIDUAL, 'Refuge des Collines', null, null, true, '+33 6 77 88 99 00', 'refuge.collines@example.org', ['12 rue des Tilleuls', 'Montreuil', '93100'], null],
            [self::CLIENT_GAEC_ID, 'mister', 'Bernard', 'Lacoste', ClientTaxStatus::FRENCH_BUSINESS, 'GAEC du Mont', '441569738', 'FR14441569738', false, '+33 5 55 12 34 56', null, ['Lieu-dit Le Mont', 'Saint-Yrieix-la-Perche', '87500'],
                [CompanyCheckOutcome::CEASED, 'GAEC du Mont', '-3 days', VatCheckOutcome::VALID, null]],
            [self::CLIENT_HARAS_ID, 'madam', 'Lucie', 'Fabre', ClientTaxStatus::FRENCH_BUSINESS, 'Écuries du Mont Ventoux', '392107454', 'FR76392107454', true, '+33 4 90 65 12 34', 'accueil@haras-ventoux.fr', ['Chemin du Ventoux', 'Bédoin', '84410'],
                [CompanyCheckOutcome::ACTIVE, 'Haras du Mont Ventoux', 'yesterday 08:14', VatCheckOutcome::INVALID, null]],
            [self::CLIENT_MISTRAL_ID, 'mister', 'Julien', 'Arnaud', ClientTaxStatus::FRENCH_BUSINESS, 'Élevage du Mistral', '000002139', 'FR27000002139', true, '+33 4 90 12 21 39', 'contact@elevage-mistral.fr', ['Mas du Mistral', 'Saint-Rémy-de-Provence', '13210'], null],
            [self::CLIENT_CRAU_ID, 'madam', 'Mathilde', 'Garnier', ClientTaxStatus::FRENCH_BUSINESS, 'Haras de la Crau', '000002105', 'FR22000002105', true, '+33 4 90 47 21 05', 'accueil@haras-crau.fr', ['Route de Saint-Martin-de-Crau', 'Arles', '13200'], null],
            [self::CLIENT_ALPILLES_ID, 'mister', 'Romain', 'Blanc', ClientTaxStatus::FRENCH_BUSINESS, 'Chenil des Alpilles', '000002071', 'FR17000002071', true, '+33 4 90 54 20 71', null, ['Chemin des Baux', 'Maussane-les-Alpilles', '13520'], null],
            [self::CLIENT_LUBERON_ID, 'madam', 'Élise', 'Martin', ClientTaxStatus::FRENCH_BUSINESS, 'Pension du Luberon', '000005017', 'FR28000005017', true, '+33 4 90 72 50 17', 'pension.luberon@example.fr', ['Hameau des Bories', 'Gordes', '84220'], null],
            // The simulator's buyer without a platform; the registry knows no
            // such SIREN either: « Entreprise introuvable » on its sheet.
            [self::CLIENT_SAINTE_BAUME_ID, 'mister', 'Denis', 'Roux', ClientTaxStatus::FRENCH_BUSINESS, 'Ferme de la Sainte-Baume', '000000000', 'FR12000000000', true, '+33 4 42 04 00 00', null, ['Route du Plan d\'Aups', 'Nans-les-Pins', '83860'],
                [CompanyCheckOutcome::NOT_FOUND, null, 'yesterday 08:15', VatCheckOutcome::UNCHECKED, null]],
        ];

        $this->createProfessionals($clinicId, $structures);
    }

    /**
     * @param list<array{string, string, string, string, ClientTaxStatus, string, ?string, ?string, bool, string, ?string, ?array{string, string, string}, ?array{CompanyCheckOutcome, ?string, ?string, VatCheckOutcome, ?string}}> $structures
     */
    private function createProfessionals(string $clinicId, array $structures): void
    {
        foreach ($structures as [$id, $civility, $first, $last, $status, $legalName, $siren, $vat, $active, $phone, $email, $address, $verification]) {
            $factory = ClientEntityFactory::new()
                ->withId($id)
                ->withClinicId($clinicId)
                ->withName($first, $last)
                ->withCivility($civility)
                ->withTaxProfile($status, $legalName, $siren, $vat)
            ;

            if (null !== $verification) {
                [$company, $registryName, $checkedAt, $vatOutcome, $requestedAt] = $verification;
                $factory                                                         = $factory->withTaxVerification($company, $registryName, $checkedAt, $vatOutcome, $checkedAt, $requestedAt);
            }

            $factory = $active ? $factory->active() : $factory->archived();

            if (null !== $address) {
                $factory = $factory->withPostalAddress(streetLine1: $address[0], city: $address[1], countryCode: 'FR', postalCode: $address[2]);
            }

            $factory->create();

            // mobile() draws a random number: the label goes first, the number after.
            $contact = ContactMethodEntityFactory::new()->forClient($id);
            $contact = str_starts_with($phone, '+33 6') ? $contact->mobile() : $contact->work();
            $contact->phone($phone)->primary()->create();

            if (null !== $email) {
                ContactMethodEntityFactory::new()->forClient($id)->email($email)->work()->primary()->create();
            }
        }
    }

    /**
     * Internal notes and logged exchanges. Sophie shows both lists filled,
     * Aurélie a single attention note; every other client keeps the empty states.
     */
    private function buildNotesAndCommunications(string $clinicId): void
    {
        ClientNoteEntityFactory::new()
            ->forClient($clinicId, self::CLIENT_SOPHIE_ID)
            ->writtenBy('Dr Rousseau', '2026-03-27 10:20:00')
            ->create(['content' => 'Cliente très impliquée et observante. Peut être contactée par SMS pour les résultats.'])
        ;
        ClientNoteEntityFactory::new()
            ->forClient($clinicId, self::CLIENT_SOPHIE_ID)
            ->attention()
            ->writtenBy('Camille Leroy', '2026-08-27 18:05:00')
            ->create(['content' => 'Rex est stressé par la contention. Prévoir un examen au sol.'])
        ;
        ClientNoteEntityFactory::new()
            ->forClient($clinicId, self::CLIENT_AURELIE_ID)
            ->attention()
            ->writtenBy('Camille Leroy', '2026-09-15 09:40:00')
            ->create(['content' => 'Facture de vermifugation en attente. Ne pas aborder le sujet en salle d’attente.'])
        ;

        ClientCommunicationEntityFactory::new()
            ->forClient($clinicId, self::CLIENT_SOPHIE_ID)
            ->via(ContactChannel::PHONE, CommunicationDirection::INBOUND)
            ->about('Appel entrant — 4 min', 'Prise de rendez-vous pour le rappel vaccinal de Rex.')
            ->loggedBy('Camille Leroy', '2026-03-20 09:12:00')
            ->create()
        ;
        ClientCommunicationEntityFactory::new()
            ->forClient($clinicId, self::CLIENT_SOPHIE_ID)
            ->via(ContactChannel::EMAIL, CommunicationDirection::OUTBOUND)
            ->about('Compte rendu du bilan annuel envoyé', 'Compte rendu et ordonnance transmis après la consultation du 27 mars.')
            ->loggedBy('Dr Rousseau', '2026-03-27 17:30:00')
            ->create()
        ;
        ClientCommunicationEntityFactory::new()
            ->forClient($clinicId, self::CLIENT_SOPHIE_ID)
            ->via(ContactChannel::SMS, CommunicationDirection::INBOUND)
            ->about(
                'Message reçu',
                '« Bonjour, Rex boite depuis quelques jours, est-ce que je peux passer aujourd’hui ? » — rappelée, reçue le jour même.',
            )
            ->loggedBy('Camille Leroy', '2026-08-27 08:04:00')
            ->create()
        ;
        ClientCommunicationEntityFactory::new()
            ->forClient($clinicId, self::CLIENT_AURELIE_ID)
            ->via(ContactChannel::EMAIL, CommunicationDirection::OUTBOUND)
            ->about('Relance de paiement', 'Première relance pour la facture de vermifugation, sans réponse à ce jour.')
            ->loggedBy('Camille Leroy', '2026-09-15 09:35:00')
            ->create()
        ;
    }
}
