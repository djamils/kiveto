<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Consultation;

use App\Context\Consultation\Domain\ValueObject\BodySystem;
use App\Context\Consultation\Domain\ValueObject\DiagnosisCertainty;
use App\Context\Consultation\Domain\ValueObject\VitalType;

/**
 * Static reference lists the cockpit needs but that no bounded context owns:
 * the exam base per species and the systems suggested by a motif or an alert,
 * the normal ranges of the vitals, the diagnosis nomenclature, the preset
 * motifs, the plan templates and the plan entries that have no catalog behind
 * them (follow-up appointments, advice, other actions).
 *
 * Enum-backed lists (vital types, body systems, certainties) are derived from
 * the domain enums so the server stays their single source of truth.
 */
final class CockpitReferenceData
{
    /**
     * Exam of each species: `base` is shown on every consultation, `sugg` is
     * suggested by the species itself, `dispo` is what can be added on demand.
     *
     * @var list<array{v: string, l: string, base: list<string>, sugg: list<string>, dispo: list<string>}>
     */
    private const array EXAM_TEMPLATES = [
        [
            'v'     => 'chien', 'l' => '🐕 Chien', 'base' => ['GENERAL'], 'sugg' => [],
            'dispo' => ['CARDIOVASCULAR', 'RESPIRATORY', 'DIGESTIVE', 'SKIN', 'EARS', 'OPHTHALMIC', 'DENTAL', 'LOCOMOTOR', 'NEUROLOGICAL', 'URINARY'],
        ],
        [
            'v'     => 'chat', 'l' => '🐈 Chat', 'base' => ['GENERAL'], 'sugg' => [],
            'dispo' => ['CARDIOVASCULAR', 'RESPIRATORY', 'DIGESTIVE', 'DENTAL', 'URINARY', 'SKIN', 'OPHTHALMIC', 'EARS', 'LOCOMOTOR', 'NEUROLOGICAL'],
        ],
        [
            'v'     => 'lapin', 'l' => '🐇 Lapin', 'base' => ['GENERAL'], 'sugg' => ['DENTAL', 'DIGESTIVE'],
            'dispo' => ['DENTAL', 'DIGESTIVE', 'RESPIRATORY', 'CARDIOVASCULAR', 'SKIN', 'OPHTHALMIC', 'EARS', 'URINARY', 'LOCOMOTOR', 'NEUROLOGICAL'],
        ],
        [
            'v'     => 'reptile', 'l' => '🦎 NAC reptile', 'base' => ['GENERAL'], 'sugg' => ['INTEGUMENT'],
            'dispo' => ['INTEGUMENT', 'RESPIRATORY', 'DIGESTIVE', 'OPHTHALMIC', 'DENTAL', 'CARDIOVASCULAR', 'URINARY', 'LOCOMOTOR', 'NEUROLOGICAL'],
        ],
    ];

    /** What each system checks, shown under « Examen général » while it is not an anomaly. */
    private const array SYSTEM_HINTS = [
        'GENERAL'        => 'vigilance · muqueuses/TRC · nœuds lymphatiques · auscultation cardio-respi · palpation abdo',
        'CARDIOVASCULAR' => 'auscultation, pouls',
        'RESPIRATORY'    => 'auscultation, FR',
        'DIGESTIVE'      => 'palpation abdominale',
        'URINARY'        => 'palpation vessie, reins',
        'LOCOMOTOR'      => 'démarche, palpation',
        'NEUROLOGICAL'   => 'réflexes, proprioception',
    ];

    /** Short labels of the exam rows, as the mock-up names them. */
    private const array SYSTEM_LABELS = [
        'GENERAL'        => 'Examen général',
        'CARDIOVASCULAR' => 'Cardiovasculaire',
        'RESPIRATORY'    => 'Respiratoire',
        'DIGESTIVE'      => 'Digestif',
        'URINARY'        => 'Urogénital',
        'LOCOMOTOR'      => 'Locomoteur',
        'NEUROLOGICAL'   => 'Neurologique',
        'SKIN'           => 'Peau & pelage',
        'OPHTHALMIC'     => 'Yeux',
        'EARS'           => 'Oreilles',
        'DENTAL'         => 'Cavité buccale',
        'INTEGUMENT'     => 'Tégument / carapace',
    ];

    /**
     * Systems a motif suggests (p = strong, s = secondary): the exam stays
     * targeted, the motif only proposes.
     *
     * @var array<string, array{p: list<string>, s: list<string>}>
     */
    private const array EXAM_BY_MOTIF = [
        'Boiterie'     => ['p' => ['LOCOMOTOR'], 's' => ['NEUROLOGICAL']],
        'Traumatisme'  => ['p' => ['LOCOMOTOR'], 's' => ['NEUROLOGICAL', 'RESPIRATORY']],
        'Vomissements' => ['p' => ['DIGESTIVE'], 's' => ['URINARY']],
        'Diarrhée'     => ['p' => ['DIGESTIVE'], 's' => []],
        'Toux'         => ['p' => ['RESPIRATORY'], 's' => ['CARDIOVASCULAR']],
        'Prurit'       => ['p' => ['SKIN'], 's' => ['EARS']],
        'Otite'        => ['p' => ['EARS'], 's' => ['SKIN']],
        'Détartrage'   => ['p' => ['DENTAL'], 's' => []],
        'Bilan annuel' => ['p' => ['DENTAL'], 's' => ['SKIN', 'OPHTHALMIC', 'EARS']],
        'Intoxication' => ['p' => [], 's' => ['NEUROLOGICAL', 'DIGESTIVE']],
    ];

    /**
     * Systems a medical alert of the animal suggests, matched on a word of the
     * alert label.
     *
     * @var array<string, list<string>>
     */
    private const array EXAM_BY_ALERT = [
        'rénale'    => ['URINARY'],
        'cardio'    => ['CARDIOVASCULAR'],
        'épilep'    => ['NEUROLOGICAL'],
        'arthrose'  => ['LOCOMOTOR'],
        'dysplasie' => ['LOCOMOTOR'],
        'dermatite' => ['SKIN'],
        'diabète'   => ['URINARY'],
    ];

    /**
     * Normal physiological ranges per species, used to flag a vital in amber.
     *
     * @var array<string, array<string, array{0: float, 1: float}>>
     */
    private const array NORMAL_RANGES = [
        'chien' => [
            'TEMP'                  => [38.0, 39.2],
            'HEART_RATE'            => [70.0, 120.0],
            'RESPIRATORY_RATE'      => [15.0, 30.0],
            'CAPILLARY_REFILL_TIME' => [0.0, 2.0],
            'BODY_CONDITION_SCORE'  => [4.0, 5.0],
            'PAIN_SCORE'            => [0.0, 1.0],
            'GLYCEMIA'              => [4.0, 7.0],
        ],
        'chat' => [
            'TEMP'                  => [38.0, 39.2],
            'HEART_RATE'            => [140.0, 220.0],
            'RESPIRATORY_RATE'      => [20.0, 30.0],
            'CAPILLARY_REFILL_TIME' => [0.0, 2.0],
            'BODY_CONDITION_SCORE'  => [4.0, 5.0],
            'PAIN_SCORE'            => [0.0, 1.0],
            'GLYCEMIA'              => [4.0, 7.0],
        ],
    ];

    /** @var list<array{0: string, 1: string, 2: string}> */
    private const array NOMENCLATURE = [
        ['M.TRAU.03', 'Fracture antébrachiale (radius-ulna)', 'Traumatologie'],
        ['M.TRAU.11', 'Contusion pulmonaire', 'Traumatologie'],
        ['M.TRAU.01', 'Choc hypovolémique', 'Traumatologie'],
        ['M.LOC.21', 'Arthrose grasset, stade précoce', 'Locomoteur'],
        ['M.LOC.18', 'Rupture partielle ligament croisé', 'Locomoteur'],
        ['M.LOC.05', 'Dysplasie de la hanche', 'Locomoteur'],
        ['M.LOC.30', 'Tendinite calcanéenne', 'Locomoteur'],
        ['M.LOC.12', 'Hernie discale lombaire', 'Locomoteur'],
        ['M.CARDIO.05', 'Souffle cardiaque grade 2', 'Cardiovasculaire'],
        ['M.CARDIO.01', 'Insuffisance cardiaque congestive', 'Cardiovasculaire'],
        ['M.CARDIO.14', 'Fibrillation atriale', 'Cardiovasculaire'],
        ['M.CARDIO.22', 'Cardiomyopathie hypertrophique (CMH)', 'Cardiovasculaire'],
        ['M.DERMA.20', 'Dermatite par allergie aux puces (DAPP)', 'Cutané'],
        ['M.DERMA.05', 'Pyodermite superficielle', 'Cutané'],
        ['M.DERMA.11', 'Atopie canine', 'Cutané'],
        ['M.GI.10', 'Gastro-entérite alimentaire', 'Digestif'],
        ['M.GI.04', 'Pancréatite aiguë', 'Digestif'],
        ['M.URO.04', 'Cystite bactérienne', 'Urinaire'],
        ['M.URO.09', 'Insuffisance rénale chronique', 'Urinaire'],
        ['M.NEURO.02', 'Épilepsie idiopathique', 'Neurologique'],
        ['M.OTO.01', 'Otite externe', 'Oreilles'],
    ];

    /**
     * Diagnoses the exam points to, per system in anomaly: the « Suggérer »
     * box lists them, the practitioner decides.
     *
     * @var array<string, list<array{0: string, 1: string}>>
     */
    private const array SUGGESTIONS_BY_SYSTEM = [
        'LOCOMOTOR'      => [['M.LOC.21', 'Anomalie locomotrice · boiterie'], ['M.LOC.18', 'Anomalie locomotrice · instabilité du grasset']],
        'CARDIOVASCULAR' => [['M.CARDIO.05', 'Anomalie à l\'auscultation cardiaque'], ['M.CARDIO.22', 'Souffle chez un chat']],
        'RESPIRATORY'    => [['M.TRAU.11', 'Anomalie respiratoire']],
        'DIGESTIVE'      => [['M.GI.10', 'Anomalie digestive'], ['M.GI.04', 'Douleur abdominale']],
        'URINARY'        => [['M.URO.04', 'Anomalie urinaire'], ['M.URO.09', 'Anomalie urinaire · animal âgé']],
        'SKIN'           => [['M.DERMA.20', 'Anomalie cutanée'], ['M.DERMA.05', 'Lésions cutanées']],
        'EARS'           => [['M.OTO.01', 'Anomalie auriculaire']],
        'NEUROLOGICAL'   => [['M.NEURO.02', 'Anomalie neurologique']],
    ];

    /** @var list<array{cat: string, items: list<string>}> */
    private const array PRESET_MOTIFS = [
        ['cat' => 'Médecine préventive', 'items' => ['Vaccination', 'Vermifugation', 'Bilan annuel', 'Identification (puce)', 'Stérilisation']],
        ['cat' => 'Pathologie', 'items' => ['Boiterie', 'Vomissements', 'Diarrhée', 'Toux', 'Prurit', 'Otite']],
        ['cat' => 'Soins', 'items' => ['Toilettage', 'Détartrage', 'Pansement', 'Retrait de fil']],
        ['cat' => 'Suivi', 'items' => ['Suivi post-op', 'Recontrôle', 'Suivi chronique']],
        ['cat' => 'Urgence', 'items' => ['Urgence vitale', 'Traumatisme', 'Intoxication']],
    ];

    /**
     * Plan templates. Catalog-backed lines carry the search term the cockpit
     * resolves against the clinic's own catalog when the template is applied.
     *
     * @var list<array{v: string, l: string, items: list<array<string, int|string>>}>
     */
    private const array PLAN_TEMPLATES = [
        ['v' => 'arthrose', 'l' => '🦴 Suivi arthrose', 'items' => [
            ['k' => 'MED', 'q' => 'Meloxidyl', 'dose' => '0,1 mg/kg', 'freq' => '1×/j', 'duree' => 7, 'voie' => 'Per os'],
            ['k' => 'ADV', 'd' => 'Repos forcé / activité réduite'],
            ['k' => 'RDV', 'd' => 'Recontrôle clinique', 'fu' => 14],
        ]],
        ['v' => 'vacc', 'l' => '💉 Vaccination annuelle', 'items' => [
            ['k' => 'ACT', 'q' => 'Vaccination CHPPiL'],
            ['k' => 'RDV', 'd' => 'Rappel vaccin annuel', 'fu' => 365],
        ]],
        ['v' => 'plaie', 'l' => '🩹 Plaie infectée', 'items' => [
            ['k' => 'ACT', 'q' => 'Suture de plaie'],
            ['k' => 'MED', 'q' => 'Synulox', 'dose' => '12,5 mg/kg', 'freq' => '2×/j', 'duree' => 7, 'voie' => 'Per os'],
            ['k' => 'PRD', 'q' => 'Collerette'],
            ['k' => 'RDV', 'd' => 'Retrait des points', 'fu' => 10],
        ]],
        ['v' => 'polytrauma', 'l' => '🚑 Polytraumatisé', 'items' => [
            ['k' => 'ACT', 'q' => 'perfusion'],
            ['k' => 'ACT', 'q' => 'Radiographie thorax'],
            ['k' => 'ACT', 'q' => 'Échographie abdominale'],
        ]],
        ['v' => 'irc', 'l' => '🫘 Suivi IRC', 'items' => [
            ['k' => 'ACT', 'q' => 'Bilan rénal'],
            ['k' => 'PRD', 'q' => 'k/d'],
            ['k' => 'RDV', 'd' => 'Bilan rénal de contrôle', 'fu' => 90],
        ]],
    ];

    /**
     * Plan entries with no catalog behind them.
     *
     * @var array<string, list<array{d: string, fu?: int}>>
     */
    private const array PLAN_ENTRIES = [
        'RDV' => [
            ['d' => 'Recontrôle clinique', 'fu' => 14], ['d' => 'Recontrôle clinique', 'fu' => 7],
            ['d' => 'Contrôle post-op', 'fu' => 10], ['d' => 'Retrait des points', 'fu' => 10],
            ['d' => 'Bilan rénal de contrôle', 'fu' => 90], ['d' => 'Rappel vaccin annuel', 'fu' => 365],
        ],
        'ADV' => [
            ['d' => 'Repos forcé / activité réduite'], ['d' => 'Promenades calmes, pas de sauts'],
            ['d' => 'Surveiller température 2×/j'], ['d' => 'Transition alimentaire sur 7 j'],
            ['d' => 'Hydratation : eau à volonté, fontaine conseillée'], ['d' => 'Collerette obligatoire 10 j'],
        ],
        'OTH' => [
            ['d' => 'Référer chez un spécialiste'], ['d' => 'Demander avis confrère'],
            ['d' => 'Devis chirurgie à établir'],
        ],
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'systems'       => $this->systems(),
            'examTemplates' => self::EXAM_TEMPLATES,
            'examByMotif'   => self::EXAM_BY_MOTIF,
            'examByAlert'   => self::EXAM_BY_ALERT,
            'vitals'        => $this->vitals(),
            'normalRanges'  => self::NORMAL_RANGES,
            'certainties'   => array_map(
                static fn (DiagnosisCertainty $c): array => ['id' => $c->value, 'label' => $c->label(), 'short' => $c->shortLabel()],
                DiagnosisCertainty::cases(),
            ),
            'nomenclature'  => self::NOMENCLATURE,
            'suggestions'   => self::SUGGESTIONS_BY_SYSTEM,
            'motifs'        => self::PRESET_MOTIFS,
            'planTemplates' => self::PLAN_TEMPLATES,
            'planEntries'   => self::PLAN_ENTRIES,
        ];
    }

    /**
     * @return list<array{id: string, l: string, i: string, h: string}>
     */
    private function systems(): array
    {
        return array_map(
            static fn (BodySystem $system): array => [
                'id' => $system->value,
                'l'  => self::SYSTEM_LABELS[$system->value],
                'i'  => $system->icon(),
                'h'  => self::SYSTEM_HINTS[$system->value] ?? '',
            ],
            BodySystem::cases(),
        );
    }

    /**
     * Weight and temperature (held by the consultation's vitals pair) first,
     * then the typed vitals, with the short label the strip shows.
     *
     * @return list<array{id: string, l: string, c: string, u: string}>
     */
    private function vitals(): array
    {
        $short = [
            'HEART_RATE'            => 'FC',
            'RESPIRATORY_RATE'      => 'FR',
            'CAPILLARY_REFILL_TIME' => 'TRC',
            'MUCOUS_MEMBRANES'      => 'Muq.',
            'BODY_CONDITION_SCORE'  => 'BCS',
            'PAIN_SCORE'            => 'Douleur',
            'BLOOD_PRESSURE'        => 'TA',
            'GLYCEMIA'              => 'Gly.',
        ];

        $vitals = [
            ['id' => 'POIDS', 'l' => 'Poids', 'c' => 'Poids', 'u' => 'kg'],
            ['id' => 'TEMP', 'l' => 'Température', 'c' => 'T°', 'u' => '°C'],
        ];

        foreach ($short as $id => $label) {
            $type     = VitalType::from($id);
            $vitals[] = ['id' => $type->value, 'l' => $type->label(), 'c' => $label, 'u' => $type->unit()];
        }

        return $vitals;
    }
}
