<?php

declare(strict_types=1);

namespace App\Fixtures\System\PharmaceuticalRegistry\Story;

use App\Fixtures\System\PharmaceuticalRegistry\Factory\ActiveSubstanceEntityFactory;
use App\Fixtures\System\PharmaceuticalRegistry\Factory\AuthorizationEntityFactory;
use App\Fixtures\System\PharmaceuticalRegistry\Factory\CompositionEntityFactory;
use App\Fixtures\System\PharmaceuticalRegistry\Factory\PresentationEntityFactory;
use Zenstruck\Foundry\Story;

final class CoreMedicationsStory extends Story
{
    public function build(): void
    {
        $drugs = [
            ['name' => 'Apoquel 3,6 mg', 'atc' => 'QD11AH91', 'status' => 'ACTIVE', 'substances' => ['Oclacitinib'], 'gtin' => '03661103046394'],
            ['name' => 'Cerenia 10 mg/mL', 'atc' => 'QA03AA55', 'status' => 'ACTIVE', 'substances' => ['Maropitant'], 'gtin' => '03661103046400'],
            ['name' => 'Frontline Combo Chien', 'atc' => 'QP53AX64', 'status' => 'ACTIVE', 'substances' => ['Fipronil', 'S-Méthoprène'], 'gtin' => '03661103046417'],
            ['name' => 'Convenia 80 mg/mL', 'atc' => 'QJ01DB10', 'status' => 'ACTIVE', 'substances' => ['Céfovécine'], 'gtin' => '03661103046424'],
            ['name' => 'Vetmedin 1,25 mg', 'atc' => 'QC01CE90', 'status' => 'ACTIVE', 'substances' => ['Pimobendane'], 'gtin' => '03661103046431'],
            ['name' => 'Stronghold 45 mg', 'atc' => 'QP54AB05', 'status' => 'ACTIVE', 'substances' => ['Sélamectine'], 'gtin' => '03661103046448'],
            ['name' => 'Synulox 500 mg', 'atc' => 'QJ01CR02', 'status' => 'ACTIVE', 'substances' => ['Amoxicilline', 'Acide clavulanique'], 'gtin' => '03661103046455'],
            ['name' => 'Cystaid Plus', 'atc' => null, 'status' => 'ACTIVE', 'substances' => ['N-acétylglucosamine'], 'gtin' => '03661103046462'],
            ['name' => 'Meloxidyl 1 mg/mL', 'atc' => 'QM01AE16', 'status' => 'ACTIVE', 'substances' => ['Méloxicam'], 'gtin' => '03661103046479'],
            ['name' => 'Rilexine 300 mg', 'atc' => 'QJ01DB01', 'status' => 'WITHDRAWN', 'substances' => ['Céfalexine'], 'gtin' => '03661103046486'],
        ];

        foreach ($drugs as $drug) {
            $authorization = AuthorizationEntityFactory::createOne([
                'commercialName'   => $drug['name'],
                'atcVetCode'       => $drug['atc'],
                'status'           => $drug['status'],
                'holderLaboratory' => 'Laboratoire Vétérinaire SA',
            ]);

            PresentationEntityFactory::createOne([
                'authorization' => $authorization,
                'description'   => $drug['name'] . ' — 1 flacon',
                'gtin'          => $drug['gtin'],
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
