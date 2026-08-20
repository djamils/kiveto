<?php

declare(strict_types=1);

namespace App\Fixtures\System\PharmaceuticalRegistry\Story;

use App\Fixtures\System\PharmaceuticalRegistry\Factory\ActiveSubstanceEntityFactory;
use App\Fixtures\System\PharmaceuticalRegistry\Factory\AuthorizationEntityFactory;
use App\Fixtures\System\PharmaceuticalRegistry\Factory\CompositionEntityFactory;
use App\Fixtures\System\PharmaceuticalRegistry\Factory\PresentationEntityFactory;
use Zenstruck\Foundry\Story;

final class ControlledSubstancesStory extends Story
{
    public function build(): void
    {
        $narcotics = [
            ['name' => 'Narketan 10 mg/mL', 'class' => 'NARCOTIC_I', 'substances' => ['Kétamine']],
            ['name' => 'Medetomidine 1 mg/mL', 'class' => 'NARCOTIC_II', 'substances' => ['Médétomidine']],
            ['name' => 'Ketamine 100 mg/mL', 'class' => 'NARCOTIC_I', 'substances' => ['Kétamine']],
        ];

        foreach ($narcotics as $narcotic) {
            $authorization = AuthorizationEntityFactory::createOne([
                'commercialName'                  => $narcotic['name'],
                'status'                          => 'ACTIVE',
                'controlledSubstanceClass'        => $narcotic['class'],
                'controlledSubstanceJurisdiction' => 'FR',
                'controlledSubstanceCode'         => 'NARCO-FR',
                'holderLaboratory'                => 'Pharma Vét SARL',
            ]);

            PresentationEntityFactory::createOne([
                'authorization'                 => $authorization,
                'description'                   => $narcotic['name'] . ' — 10 mL',
                'prescriptionRequired'          => true,
                'prescriptionClass'             => 'RX_NARCOTIC',
                'prescriptionJurisJurisdiction' => 'FR',
                'prescriptionJurisCode'         => 'NARCO',
            ]);

            foreach ($narcotic['substances'] as $substanceLabel) {
                CompositionEntityFactory::createOne([
                    'authorization'   => $authorization,
                    'activeSubstance' => ActiveSubstanceEntityFactory::named($substanceLabel),
                ]);
            }
        }
    }
}
