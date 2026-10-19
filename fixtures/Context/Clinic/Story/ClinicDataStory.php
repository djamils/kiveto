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
    // A quiet clinic of the group: the « journée calme » of the dashboard.
    public const string CALM_CLINIC_ID = '01960000-0000-7000-8000-000000000102';

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
                siret: '81245690300028',
                vatNumber: 'FR20812456903',
                rcsCity: 'Paris',
                rcsNumber: '812 456 903',
                apeCode: '7500Z',
                ordinalNumber: '21284',
                ordinalCouncil: 'idf',
                liabilityInsurer: 'MACSF',
                liabilityPolicyNumber: '4401220788',
                streetLine1: '18 rue des Abbesses',
                postalCode: '75018',
                city: 'Paris',
                countryCode: 'FR',
                phone: '+33142551890',
                email: 'contact@vet-paris.fr',
                website: 'www.vet-paris.fr',
            ))
            // Open six days a week, shut at lunch, closed on Sunday — and shut
            // over Christmas (three days, shown as one range) and on New Year's Day.
            ->withOpeningHours([
                'week' => [
                    1 => [['opensAt' => '09:00', 'closesAt' => '12:30'], ['opensAt' => '14:00', 'closesAt' => '19:00']],
                    2 => [['opensAt' => '09:00', 'closesAt' => '12:30'], ['opensAt' => '14:00', 'closesAt' => '19:00']],
                    3 => [['opensAt' => '09:00', 'closesAt' => '12:30'], ['opensAt' => '14:00', 'closesAt' => '19:00']],
                    4 => [['opensAt' => '09:00', 'closesAt' => '12:30'], ['opensAt' => '14:00', 'closesAt' => '19:00']],
                    5 => [['opensAt' => '09:00', 'closesAt' => '19:00']],
                    6 => [['opensAt' => '09:00', 'closesAt' => '13:00']],
                ],
                'closures' => [
                    ['date' => '2026-12-24', 'reason' => 'Noël'],
                    ['date' => '2026-12-25', 'reason' => 'Noël'],
                    ['date' => '2026-12-26', 'reason' => 'Noël'],
                    ['date' => '2027-01-01', 'reason' => 'Jour de l\'an'],
                ],
            ])
            ->withBillingSettings([
                'billingAddress'      => null,
                'invoiceNumberPrefix' => 'PAR',
                'paymentTermsDays'    => 30,
                'iban'                => 'FR7630006000011234567890189',
                'bic'                 => 'AGRIFRPP',
                'invoiceFooterNote'   => 'Règlement à réception. Aucun escompte pour paiement anticipé.',
                'creditNotePrefix'    => 'AVPAR',
                // Breeders and shelters are given 45 days; the clinic files monthly.
                'professionalPaymentTermsDays' => 45,
                'vatRegime'                    => 'MONTHLY',
                'vatOnDebits'                  => false,
                'acceptedPaymentMethods'       => ['CARD', 'CASH', 'CHECK', 'TRANSFER'],
            ])
            ->withBrandColor('#4f86c6')
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
                siret: '90112233300015',
                vatNumber: 'FR00901122333',
                rcsCity: 'Lyon',
                rcsNumber: '901 122 333',
                apeCode: '7500Z',
                ordinalNumber: '10442',
                ordinalCouncil: 'ara',
                streetLine1: '7 quai Saint-Antoine',
                streetLine2: 'Bâtiment B',
                postalCode: '69002',
                city: 'Lyon',
                countryCode: 'FR',
                phone: '+33478920145',
                email: 'contact@vetofrance-lyon.fr',
                website: 'www.vetofrance.fr',
            ))
            // Lyon and Cassis are two establishments of one company (SIREN
            // 901 122 333): each numbers its documents under prefixes of its own,
            // so that a number identifies one document of the company.
            ->withBillingSettings([
                'invoiceNumberPrefix' => 'LYO',
                'creditNotePrefix'    => 'AVLYO',
            ])
            // Continuous hours on weekdays, so the dashboard can count the
            // day's slots.
            ->withOpeningHours([
                'week' => [
                    1 => [['opensAt' => '08:30', 'closesAt' => '19:00']],
                    2 => [['opensAt' => '08:30', 'closesAt' => '19:00']],
                    3 => [['opensAt' => '08:30', 'closesAt' => '19:00']],
                    4 => [['opensAt' => '08:30', 'closesAt' => '19:00']],
                    5 => [['opensAt' => '08:30', 'closesAt' => '19:00']],
                    6 => [['opensAt' => '09:00', 'closesAt' => '12:00']],
                ],
                'closures' => [],
            ])
            ->create(['timeZone' => 'Europe/Paris', 'locale' => 'fr-FR'])
        ;

        ClinicEntityFactory::new()
            ->withId(self::CALM_CLINIC_ID)
            ->withSlug('clinic-calanques')
            ->withName('Clinique des Calanques')
            ->withGroupId(self::GROUP_ID)
            ->withLetterhead(new LetterheadEmbeddable(
                legalName: 'Réseau VetoFrance — Établissement de Cassis',
                legalForm: 'SAS',
                shareCapital: '150 000 €',
                siret: '90112233300023',
                vatNumber: 'FR00901122333',
                rcsCity: 'Marseille',
                rcsNumber: '901 122 333',
                apeCode: '7500Z',
                ordinalNumber: '13871',
                ordinalCouncil: 'paca',
                liabilityInsurer: 'Groupama',
                liabilityPolicyNumber: 'GPRC77120',
                streetLine1: '5 avenue de la Marne',
                postalCode: '13260',
                city: 'Cassis',
                countryCode: 'FR',
                phone: '+33442010203',
                email: 'cassis@vetofrance.fr',
                website: 'www.vetofrance.fr',
            ))
            // Cheques are no longer taken at Cassis: the desk shows card, cash and transfer only.
            ->withBillingSettings([
                'invoiceNumberPrefix'    => 'CAS',
                'creditNotePrefix'       => 'AVCAS',
                'paymentTermsDays'       => 0,
                'vatRegime'              => 'SIMPLIFIED',
                'acceptedPaymentMethods' => ['CARD', 'CASH', 'TRANSFER'],
            ])
            ->withBrandColor('#e07a5f')
            // Every day of the week, so its calm day is today whatever today is.
            ->withOpeningHours([
                'week' => array_fill(1, 7, [
                    ['opensAt' => '09:00', 'closesAt' => '12:00'],
                    ['opensAt' => '14:00', 'closesAt' => '18:00'],
                ]),
                'closures' => [],
            ])
            ->create(['timeZone' => 'Europe/Paris', 'locale' => 'fr-FR'])
        ;

        // Quelques cliniques supplémentaires pour les tests — sans en-tête, pour
        // couvrir le cas d'un document imprimé par une clinique qui n'a rien saisi.
        ClinicEntityFactory::createMany(3);
    }
}
