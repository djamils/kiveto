<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Clinic\Story;

use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Embeddable\LetterheadEmbeddable;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicGroupEntityFactory;
use Zenstruck\Foundry\Story;

final class ClinicDataStory extends Story
{
    public const string INDEPENDENT_CLINIC_ID = '01960000-0000-7000-8000-000000000001';
    public const string GROUP_ID              = '01960000-0000-7000-8000-000000000100';
    public const string GROUP_CLINIC_ID       = '01960000-0000-7000-8000-000000000101';

    public function build(): void
    {
        // 1 clinic indépendante
        ClinicEntityFactory::new()
            ->withId(self::INDEPENDENT_CLINIC_ID)
            ->withSlug('clinic-paris')
            ->withName('Clinique Vétérinaire de Paris')
            ->withLetterhead(new LetterheadEmbeddable(
                legalName: 'Clinique Vétérinaire de Paris',
                legalForm: 'SELARL',
                shareCapital: '30 000 €',
                siret: '81245690300027',
                vatNumber: 'FR41812456903',
                rcsCity: 'Paris',
                rcsNumber: '812 456 903',
                apeCode: '7500Z',
                ordinalNumber: '75-1284',
                streetLine1: '18 rue des Abbesses',
                postalCode: '75018',
                city: 'Paris',
                countryCode: 'FR',
                phone: '+33142551890',
                email: 'contact@vet-paris.fr',
                website: 'www.vet-paris.fr',
            ))
            ->create(['timeZone' => 'Europe/Paris', 'locale' => 'fr-FR'])
        ;

        // 1 clinicGroup + 1 clinic rattachée
        ClinicGroupEntityFactory::new()
            ->withId(self::GROUP_ID)
            ->withName('Réseau VetoFrance')
            ->create()
        ;

        ClinicEntityFactory::new()
            ->withId(self::GROUP_CLINIC_ID)
            ->withSlug('clinic-lyon')
            ->withName('Clinique Vétérinaire de Lyon')
            ->withGroupId(self::GROUP_ID)
            ->withLetterhead(new LetterheadEmbeddable(
                legalName: 'Réseau VetoFrance — Établissement de Lyon',
                legalForm: 'SAS',
                shareCapital: '150 000 €',
                siret: '90112233400018',
                vatNumber: 'FR62901122334',
                rcsCity: 'Lyon',
                rcsNumber: '901 122 334',
                apeCode: '7500Z',
                ordinalNumber: '69-0442',
                streetLine1: '7 quai Saint-Antoine',
                streetLine2: 'Bâtiment B',
                postalCode: '69002',
                city: 'Lyon',
                countryCode: 'FR',
                phone: '+33478920145',
                email: 'contact@vetofrance-lyon.fr',
                website: 'www.vetofrance.fr',
            ))
            ->create(['timeZone' => 'Europe/Paris', 'locale' => 'fr-FR'])
        ;

        // Quelques cliniques supplémentaires pour les tests — sans en-tête, pour
        // couvrir le cas d'un document imprimé par une clinique qui n'a rien saisi.
        ClinicEntityFactory::createMany(3);
    }
}
