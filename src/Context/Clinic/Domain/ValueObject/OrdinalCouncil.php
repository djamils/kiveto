<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\ValueObject;

/**
 * The regional council of the veterinary Order the practising company is
 * registered with: printed next to its Order number.
 */
enum OrdinalCouncil: string
{
    case AUVERGNE_RHONE_ALPES       = 'ara';
    case BOURGOGNE_FRANCHE_COMTE    = 'bfc';
    case BRETAGNE                   = 'bre';
    case CENTRE_VAL_DE_LOIRE        = 'cvl';
    case CORSE                      = 'cor';
    case GRAND_EST                  = 'ges';
    case HAUTS_DE_FRANCE            = 'hdf';
    case ILE_DE_FRANCE              = 'idf';
    case NORMANDIE                  = 'nor';
    case NOUVELLE_AQUITAINE         = 'naq';
    case OCCITANIE                  = 'occ';
    case PAYS_DE_LA_LOIRE           = 'pdl';
    case PROVENCE_ALPES_COTE_D_AZUR = 'paca';
    case OUTRE_MER                  = 'om';

    public function label(): string
    {
        return match ($this) {
            self::AUVERGNE_RHONE_ALPES       => 'Auvergne-Rhône-Alpes',
            self::BOURGOGNE_FRANCHE_COMTE    => 'Bourgogne-Franche-Comté',
            self::BRETAGNE                   => 'Bretagne',
            self::CENTRE_VAL_DE_LOIRE        => 'Centre-Val de Loire',
            self::CORSE                      => 'Corse',
            self::GRAND_EST                  => 'Grand Est',
            self::HAUTS_DE_FRANCE            => 'Hauts-de-France',
            self::ILE_DE_FRANCE              => 'Île-de-France',
            self::NORMANDIE                  => 'Normandie',
            self::NOUVELLE_AQUITAINE         => 'Nouvelle-Aquitaine',
            self::OCCITANIE                  => 'Occitanie',
            self::PAYS_DE_LA_LOIRE           => 'Pays de la Loire',
            self::PROVENCE_ALPES_COTE_D_AZUR => 'Provence-Alpes-Côte d’Azur',
            self::OUTRE_MER                  => 'Outre-mer',
        };
    }
}
