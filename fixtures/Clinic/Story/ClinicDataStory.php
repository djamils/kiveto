<?php

declare(strict_types=1);

namespace App\Fixtures\Clinic\Story;

use App\Fixtures\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Clinic\Factory\ClinicGroupEntityFactory;
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
            ->create()
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
            ->create()
        ;

        // Quelques cliniques supplémentaires pour les tests
        ClinicEntityFactory::createMany(3);
    }
}
