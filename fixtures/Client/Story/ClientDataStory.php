<?php

declare(strict_types=1);

namespace App\Fixtures\Client\Story;

use App\Fixtures\Client\Factory\ClientEntityFactory;
use App\Fixtures\Client\Factory\ContactMethodEntityFactory;
use App\Fixtures\Clinic\Story\ClinicDataStory;
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
    }
}
