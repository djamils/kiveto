<?php

declare(strict_types=1);

namespace App\Fixtures\System\PharmaceuticalRegistry\Story;

use App\Fixtures\System\PharmaceuticalRegistry\Factory\ActiveSubstanceEntityFactory;
use App\Fixtures\System\PharmaceuticalRegistry\Factory\AuthorizationEntityFactory;
use App\Fixtures\System\PharmaceuticalRegistry\Factory\CompositionEntityFactory;
use App\Fixtures\System\PharmaceuticalRegistry\Factory\PresentationEntityFactory;
use Zenstruck\Foundry\Story;

final class LivestockMedicationsStory extends Story
{
    public function build(): void
    {
        $livestockDrugs = [
            ['name' => 'Baytril 10% Solution', 'atc' => 'QJ01MA90', 'substances' => ['Enrofloxacine']],
            ['name' => 'Oxytetracycline 200 mg/mL', 'atc' => 'QJ01AA06', 'substances' => ['Oxytétracycline']],
            ['name' => 'Terramycin LA', 'atc' => 'QJ01AA06', 'substances' => ['Oxytétracycline']],
        ];

        foreach ($livestockDrugs as $drug) {
            $authorization = AuthorizationEntityFactory::createOne([
                'commercialName'   => $drug['name'],
                'atcVetCode'       => $drug['atc'],
                'status'           => 'ACTIVE',
                'holderLaboratory' => 'Vétérinaire Bovin SAS',
            ]);

            PresentationEntityFactory::createOne([
                'authorization' => $authorization,
                'description'   => $drug['name'] . ' — 250 mL',
            ]);

            foreach ($drug['substances'] as $substanceLabel) {
                CompositionEntityFactory::createOne([
                    'authorization'   => $authorization,
                    'activeSubstance' => ActiveSubstanceEntityFactory::named($substanceLabel),
                ]);
            }
        }
    }
}
