<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Consultation;

use App\Context\Consultation\Domain\ValueObject\BodySystem;
use App\Context\Consultation\Domain\ValueObject\DiagnosisCertainty;
use App\Context\Consultation\Domain\ValueObject\DiagnosisSource;
use App\Context\Consultation\Domain\ValueObject\ExamStatus;
use App\Context\Consultation\Domain\ValueObject\PlanActionKind;
use App\Context\Consultation\Domain\ValueObject\VitalType;

/**
 * Static reference lists the cockpit needs but that no bounded context owns:
 * the exam templates per species, the diagnosis nomenclature, the preset motifs
 * and the plan templates and suggestions.
 *
 * Enum-backed lists (vital types, body systems, statuses, kinds) are derived
 * from the domain enums so the server stays their single source of truth.
 */
final class CockpitReferenceData
{
    /**
     * Which body systems the clinical exam grid shows per species.
     *
     * @var list<array{id: string, name: string, emoji: string, enabled: bool, systems: list<string>}>
     */
    private const array SPECIES_TEMPLATES = [
        [
            'id'      => 'chien',
            'name'    => 'Chien standard',
            'emoji'   => '🐕',
            'enabled' => true,
            'systems' => [
                'CARDIOVASCULAR', 'RESPIRATORY', 'DIGESTIVE', 'URINARY',
                'LOCOMOTOR', 'NEUROLOGICAL', 'SKIN', 'OPHTHALMIC',
            ],
        ],
        [
            'id'      => 'chat',
            'name'    => 'Chat standard',
            'emoji'   => '🐈',
            'enabled' => true,
            'systems' => [
                'CARDIOVASCULAR', 'RESPIRATORY', 'DIGESTIVE', 'URINARY',
                'LOCOMOTOR', 'NEUROLOGICAL', 'SKIN', 'OPHTHALMIC',
            ],
        ],
        [
            'id'      => 'lapin',
            'name'    => 'Lapin',
            'emoji'   => '🐰',
            'enabled' => true,
            'systems' => [
                'DENTAL', 'DIGESTIVE', 'RESPIRATORY', 'CARDIOVASCULAR',
                'URINARY', 'LOCOMOTOR', 'SKIN',
            ],
        ],
        [
            'id'      => 'reptile',
            'name'    => 'NAC reptile',
            'emoji'   => '🦎',
            'enabled' => true,
            'systems' => [
                'INTEGUMENT', 'RESPIRATORY', 'CARDIOVASCULAR', 'DIGESTIVE',
                'URINARY', 'OPHTHALMIC',
            ],
        ],
        [
            'id'      => 'oiseau',
            'name'    => 'NAC oiseau',
            'emoji'   => '🦜',
            'enabled' => false,
            'systems' => [
                'RESPIRATORY', 'CARDIOVASCULAR', 'SKIN', 'DIGESTIVE',
                'LOCOMOTOR', 'OPHTHALMIC',
            ],
        ],
        [
            'id'      => 'equin',
            'name'    => 'Équin',
            'emoji'   => '🐴',
            'enabled' => false,
            'systems' => [
                'LOCOMOTOR', 'CARDIOVASCULAR', 'RESPIRATORY', 'DIGESTIVE',
                'DENTAL', 'SKIN', 'OPHTHALMIC', 'NEUROLOGICAL',
            ],
        ],
    ];

    /** @var list<array{code: string, name: string, system: string}> */
    private const array NOMENCLATURE = [
        ['code' => 'M.LOC.21', 'name' => 'Arthrose grasset, stade précoce', 'system' => 'Locomoteur'],
        ['code' => 'M.LOC.18', 'name' => 'Rupture partielle ligament croisé', 'system' => 'Locomoteur'],
        ['code' => 'M.LOC.05', 'name' => 'Dysplasie de la hanche', 'system' => 'Locomoteur'],
        ['code' => 'M.LOC.30', 'name' => 'Tendinite calcanéenne', 'system' => 'Locomoteur'],
        ['code' => 'M.LOC.12', 'name' => 'Hernie discale lombaire', 'system' => 'Locomoteur'],
        ['code' => 'M.CARDIO.05', 'name' => 'Souffle cardiaque grade 2', 'system' => 'Cardiovasculaire'],
        ['code' => 'M.CARDIO.01', 'name' => 'Insuffisance cardiaque congestive', 'system' => 'Cardiovasculaire'],
        ['code' => 'M.CARDIO.14', 'name' => 'Fibrillation atriale', 'system' => 'Cardiovasculaire'],
        ['code' => 'M.DERMA.20', 'name' => 'Dermatite par allergie aux puces (DAPP)', 'system' => 'Cutané'],
        ['code' => 'M.DERMA.05', 'name' => 'Pyodermite superficielle', 'system' => 'Cutané'],
        ['code' => 'M.DERMA.11', 'name' => 'Atopie canine', 'system' => 'Cutané'],
        ['code' => 'M.GI.10', 'name' => 'Gastro-entérite alimentaire', 'system' => 'Digestif'],
        ['code' => 'M.GI.04', 'name' => 'Pancréatite aiguë', 'system' => 'Digestif'],
        ['code' => 'M.URO.04', 'name' => 'Cystite bactérienne', 'system' => 'Urinaire'],
        ['code' => 'M.URO.09', 'name' => 'Insuffisance rénale chronique', 'system' => 'Urinaire'],
    ];

    /** @var list<array{category: string, items: list<string>}> */
    private const array PRESET_MOTIFS = [
        [
            'category' => 'Médecine préventive',
            'items'    => ['Vaccination', 'Vermifugation', 'Bilan annuel', 'Identification (puce)', 'Stérilisation'],
        ],
        [
            'category' => 'Pathologie',
            'items'    => ['Boiterie', 'Vomissements', 'Diarrhée', 'Toux', 'Prurit', 'Otite'],
        ],
        [
            'category' => 'Soins',
            'items'    => ['Toilettage', 'Détartrage', 'Pansement', 'Retrait de fil'],
        ],
        [
            'category' => 'Suivi',
            'items'    => ['Suivi post-op', 'Recontrôle', 'Suivi chronique'],
        ],
        [
            'category' => 'Urgence',
            'items'    => ['Urgence vitale', 'Traumatisme', 'Intoxication'],
        ],
    ];

    /**
     * @var list<array{
     *     id: string,
     *     name: string,
     *     emoji: string,
     *     description: string,
     *     items: list<array{kind: string, description: string, posology: ?string, durationDays: ?int, followUpDays: ?int}>
     * }>
     */
    private const array PLAN_TEMPLATES = [
        [
            'id'          => 'vacc-annuelle',
            'name'        => 'Vaccination annuelle (chien)',
            'emoji'       => '💉',
            'description' => 'CHPLR + examen + rappel',
            'items'       => [
                [
                    'kind'         => 'PERFORMED_ACT',
                    'description'  => 'Vaccination CHPLR sous-cutanée',
                    'posology'     => null,
                    'durationDays' => null,
                    'followUpDays' => null,
                ],
                [
                    'kind'         => 'PERFORMED_ACT',
                    'description'  => 'Examen général',
                    'posology'     => null,
                    'durationDays' => null,
                    'followUpDays' => null,
                ],
                [
                    'kind'         => 'FOLLOW_UP_APPOINTMENT',
                    'description'  => 'Rappel vaccin',
                    'posology'     => null,
                    'durationDays' => null,
                    'followUpDays' => 365,
                ],
            ],
        ],
        [
            'id'          => 'suivi-arthrose',
            'name'        => 'Suivi arthrose',
            'emoji'       => '🦴',
            'description' => 'AINS + chondroprotecteur + recontrôle',
            'items'       => [
                [
                    'kind'         => 'MEDICATION_PRESCRIPTION',
                    'description'  => 'AINS 7 j + repos forcé 10 j',
                    'posology'     => 'Méloxicam 0,1 mg/kg · 1×/j · 7 j · per os',
                    'durationDays' => 7,
                    'followUpDays' => null,
                ],
                [
                    'kind'         => 'MEDICATION_PRESCRIPTION',
                    'description'  => 'Chondroprotecteur cure 4 sem.',
                    'posology'     => 'Cartrophen 3 mg/kg · SC · 1×/sem · 4 sem',
                    'durationDays' => 28,
                    'followUpDays' => null,
                ],
                [
                    'kind'         => 'FOLLOW_UP_APPOINTMENT',
                    'description'  => 'Recontrôle clinique',
                    'posology'     => null,
                    'durationDays' => null,
                    'followUpDays' => 14,
                ],
            ],
        ],
        [
            'id'          => 'plaie-infectee',
            'name'        => 'Plaie infectée',
            'emoji'       => '🩹',
            'description' => 'Parage + ATB + antalgique',
            'items'       => [
                [
                    'kind'         => 'PERFORMED_ACT',
                    'description'  => 'Parage de plaie + antiseptique',
                    'posology'     => null,
                    'durationDays' => null,
                    'followUpDays' => null,
                ],
                [
                    'kind'         => 'MEDICATION_PRESCRIPTION',
                    'description'  => 'Antibiotique 7 j',
                    'posology'     => 'Clavaseptin · 12,5 mg/kg · 2×/j · 7 j',
                    'durationDays' => 7,
                    'followUpDays' => null,
                ],
                [
                    'kind'         => 'MEDICATION_PRESCRIPTION',
                    'description'  => 'Antalgique 5 j',
                    'posology'     => 'Tramadol · 2 mg/kg · 2×/j · 5 j',
                    'durationDays' => 5,
                    'followUpDays' => null,
                ],
                [
                    'kind'         => 'FOLLOW_UP_APPOINTMENT',
                    'description'  => 'Retrait des points',
                    'posology'     => null,
                    'durationDays' => null,
                    'followUpDays' => 10,
                ],
            ],
        ],
    ];

    /**
     * Ready-made plan entries for the kinds that have no catalog behind them.
     * Acts and medications are searched in the Catalog instead.
     *
     * @var array<string, list<array{code: string, name: string, followUpDays: ?int, posology: ?string, durationDays: ?int}>>
     */
    private const array PLAN_SUGGESTIONS = [
        'FOLLOW_UP_APPOINTMENT' => [
            ['code' => 'APT001', 'name' => 'Recontrôle clinique', 'followUpDays' => 7, 'posology' => null, 'durationDays' => null],
            ['code' => 'APT002', 'name' => 'Recontrôle clinique', 'followUpDays' => 14, 'posology' => null, 'durationDays' => null],
            ['code' => 'APT003', 'name' => 'Recontrôle clinique', 'followUpDays' => 30, 'posology' => null, 'durationDays' => null],
            ['code' => 'APT004', 'name' => 'Contrôle post-op', 'followUpDays' => 10, 'posology' => null, 'durationDays' => null],
            ['code' => 'APT005', 'name' => 'Retrait des points', 'followUpDays' => 10, 'posology' => null, 'durationDays' => null],
            ['code' => 'APT006', 'name' => 'Recontrôle à 3 mois', 'followUpDays' => 90, 'posology' => null, 'durationDays' => null],
            ['code' => 'APT007', 'name' => 'Rappel vaccin annuel', 'followUpDays' => 365, 'posology' => null, 'durationDays' => null],
        ],
        'ADVICE' => [
            ['code' => 'ADV001', 'name' => 'Repos forcé / activité réduite', 'followUpDays' => null, 'posology' => null, 'durationDays' => null],
            ['code' => 'ADV002', 'name' => 'Promenades calmes en laisse uniquement', 'followUpDays' => null, 'posology' => null, 'durationDays' => null],
            ['code' => 'ADV003', 'name' => 'Pansement à changer tous les 2 jours', 'followUpDays' => null, 'posology' => null, 'durationDays' => null],
            ['code' => 'ADV004', 'name' => 'Surveiller température 2×/j', 'followUpDays' => null, 'posology' => null, 'durationDays' => null],
            ['code' => 'ADV005', 'name' => 'Transition alimentaire sur 7 j', 'followUpDays' => null, 'posology' => null, 'durationDays' => null],
            ['code' => 'ADV006', 'name' => 'Médication à jeun ou avec repas', 'followUpDays' => null, 'posology' => null, 'durationDays' => null],
            ['code' => 'ADV007', 'name' => 'Hydratation contrôlée, eau à volonté', 'followUpDays' => null, 'posology' => null, 'durationDays' => null],
            ['code' => 'ADV008', 'name' => 'Collerette obligatoire 10 j', 'followUpDays' => null, 'posology' => null, 'durationDays' => null],
        ],
        'OTHER' => [
            ['code' => 'OTH001', 'name' => 'Référer chez un spécialiste', 'followUpDays' => null, 'posology' => null, 'durationDays' => null],
            ['code' => 'OTH002', 'name' => 'Demander avis confrère', 'followUpDays' => null, 'posology' => null, 'durationDays' => null],
            ['code' => 'OTH003', 'name' => 'Programmer hospitalisation', 'followUpDays' => null, 'posology' => null, 'durationDays' => null],
            ['code' => 'OTH004', 'name' => 'Devis chirurgie à établir', 'followUpDays' => null, 'posology' => null, 'durationDays' => null],
        ],
    ];

    /**
     * @return array{
     *     vitalTypes: list<array{id: string, label: string, unit: string, range: string, default: string, numeric: bool, min: ?float, max: ?float}>,
     *     bodySystems: list<array{id: string, label: string, icon: string, drilldown: ?string}>,
     *     examStatuses: list<array{id: string, label: string}>,
     *     diagnosisCertainties: list<array{id: string, label: string, shortLabel: string}>,
     *     diagnosisSources: list<array{id: string, label: string}>,
     *     planActionKinds: list<array{id: string, label: string, singularLabel: string, billable: bool}>,
     *     speciesTemplates: list<array{id: string, name: string, emoji: string, enabled: bool, systems: list<string>}>,
     *     nomenclature: list<array{code: string, name: string, system: string}>,
     *     presetMotifs: list<array{category: string, items: list<string>}>,
     *     planTemplates: list<array{id: string, name: string, emoji: string, description: string, items: list<array{kind: string, description: string, posology: ?string, durationDays: ?int, followUpDays: ?int}>}>,
     *     planSuggestions: array<string, list<array{code: string, name: string, followUpDays: ?int, posology: ?string, durationDays: ?int}>>
     * }
     */
    public function toArray(): array
    {
        return [
            'vitalTypes'           => $this->vitalTypes(),
            'bodySystems'          => $this->bodySystems(),
            'examStatuses'         => $this->examStatuses(),
            'diagnosisCertainties' => $this->diagnosisCertainties(),
            'diagnosisSources'     => $this->diagnosisSources(),
            'planActionKinds'      => $this->planActionKinds(),
            'speciesTemplates'     => self::SPECIES_TEMPLATES,
            'nomenclature'         => self::NOMENCLATURE,
            'presetMotifs'         => self::PRESET_MOTIFS,
            'planTemplates'        => self::PLAN_TEMPLATES,
            'planSuggestions'      => self::PLAN_SUGGESTIONS,
        ];
    }

    /**
     * @return list<array{id: string, label: string, unit: string, range: string, default: string, numeric: bool, min: ?float, max: ?float}>
     */
    private function vitalTypes(): array
    {
        return array_map(
            static fn (VitalType $type): array => [
                'id'      => $type->value,
                'label'   => $type->label(),
                'unit'    => $type->unit(),
                'range'   => $type->referenceRange(),
                'default' => $type->defaultValue(),
                'numeric' => $type->isNumeric(),
                'min'     => $type->min(),
                'max'     => $type->max(),
            ],
            VitalType::cases(),
        );
    }

    /**
     * @return list<array{id: string, label: string, icon: string, drilldown: ?string}>
     */
    private function bodySystems(): array
    {
        return array_map(
            static fn (BodySystem $system): array => [
                'id'        => $system->value,
                'label'     => $system->label(),
                'icon'      => $system->icon(),
                'drilldown' => $system->drilldown(),
            ],
            BodySystem::cases(),
        );
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    private function examStatuses(): array
    {
        return array_map(
            static fn (ExamStatus $status): array => [
                'id'    => $status->value,
                'label' => $status->label(),
            ],
            ExamStatus::cases(),
        );
    }

    /**
     * @return list<array{id: string, label: string, shortLabel: string}>
     */
    private function diagnosisCertainties(): array
    {
        return array_map(
            static fn (DiagnosisCertainty $certainty): array => [
                'id'         => $certainty->value,
                'label'      => $certainty->label(),
                'shortLabel' => $certainty->shortLabel(),
            ],
            DiagnosisCertainty::cases(),
        );
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    private function diagnosisSources(): array
    {
        return array_map(
            static fn (DiagnosisSource $source): array => [
                'id'    => $source->value,
                'label' => $source->label(),
            ],
            DiagnosisSource::cases(),
        );
    }

    /**
     * @return list<array{id: string, label: string, singularLabel: string, billable: bool}>
     */
    private function planActionKinds(): array
    {
        return array_map(
            static fn (PlanActionKind $kind): array => [
                'id'            => $kind->value,
                'label'         => $kind->label(),
                'singularLabel' => $kind->singularLabel(),
                'billable'      => $kind->isBillable(),
            ],
            PlanActionKind::cases(),
        );
    }
}
