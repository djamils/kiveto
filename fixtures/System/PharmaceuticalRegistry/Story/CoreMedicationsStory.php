<?php

declare(strict_types=1);

namespace App\Fixtures\System\PharmaceuticalRegistry\Story;

use App\Fixtures\System\PharmaceuticalRegistry\Factory\ActiveSubstanceEntityFactory;
use App\Fixtures\System\PharmaceuticalRegistry\Factory\AuthorizationEntityFactory;
use App\Fixtures\System\PharmaceuticalRegistry\Factory\PresentationEntityFactory;
use Zenstruck\Foundry\Story;

final class CoreMedicationsStory extends Story
{
    public function build(): void
    {
        $drugs = [
            ['name' => 'Apoquel 3,6 mg', 'atc' => 'QD11AH91', 'status' => 'ACTIVE'],
            ['name' => 'Cerenia 10 mg/mL', 'atc' => 'QA03AA55', 'status' => 'ACTIVE'],
            ['name' => 'Frontline Combo Chien', 'atc' => 'QP53AX64', 'status' => 'ACTIVE'],
            ['name' => 'Convenia 80 mg/mL', 'atc' => 'QJ01DB10', 'status' => 'ACTIVE'],
            ['name' => 'Vetmedin 1,25 mg', 'atc' => 'QC01CE90', 'status' => 'ACTIVE'],
            ['name' => 'Stronghold 45 mg', 'atc' => 'QP54AB05', 'status' => 'ACTIVE'],
            ['name' => 'Synulox 500 mg', 'atc' => 'QJ01CR02', 'status' => 'ACTIVE'],
            ['name' => 'Cystaid Plus', 'atc' => null, 'status' => 'ACTIVE'],
            ['name' => 'Meloxidyl 1 mg/mL', 'atc' => 'QM01AE16', 'status' => 'ACTIVE'],
            ['name' => 'Rilexine 300 mg', 'atc' => 'QJ01DB01', 'status' => 'WITHDRAWN'],
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
            ]);

            ActiveSubstanceEntityFactory::createOne();
        }
    }
}
