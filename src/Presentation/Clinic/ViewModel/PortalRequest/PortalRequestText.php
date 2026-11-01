<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\PortalRequest;

/**
 * The French words of the « Demandes Portal » screens, the dashboard tasks,
 * the agenda popover and the cockpit: what a request asks, in which state it
 * is, and how the owner answered a pre-visit questionnaire.
 */
final class PortalRequestText
{
    public const array KIND = [
        'pet_change'           => 'Modification de fiche',
        'prescription_renewal' => 'Renouvellement d’ordonnance',
    ];

    public const array FIELD = [
        'name'          => 'Nom',
        'weight'        => 'Poids',
        'sterilization' => 'Stérilisation',
        'chip_number'   => 'N° de puce',
        'other'         => 'Autre',
    ];

    public const array CONDITION = [
        'improved'        => 'Va mieux',
        'no_improvement'  => 'Pas d’amélioration',
        'adverse_effects' => 'Effets indésirables',
    ];

    public const array STATUS = [
        'submitted' => 'À traiter',
        'done'      => 'Traitée',
        'rejected'  => 'Refusée',
        'closed'    => 'Close',
    ];

    public const array CLOSED_REASON = [
        'link_revoked'           => 'accès Portal retiré',
        'client_archived'        => 'client archivé',
        'account_deleted'        => 'compte Portal supprimé',
        'prescription_cancelled' => 'ordonnance annulée',
        'pet_archived'           => 'animal archivé',
        'pet_deceased'           => 'animal décédé',
    ];

    public const array SYMPTOM = [
        'none'          => 'Aucun symptôme',
        'cough'         => 'Toux',
        'vomiting'      => 'Vomissements',
        'diarrhea'      => 'Diarrhée',
        'appetite_loss' => 'Perte d’appétit',
        'itching'       => 'Démangeaisons',
        'lameness'      => 'Boiterie',
    ];

    public const array TEMPERAMENT = [
        'calm'     => 'Calme',
        'stressed' => 'Stressé',
        'may_bite' => 'Peut mordre',
    ];

    private const array STERILIZATION = [
        'neutered' => 'Stérilisé',
        'intact'   => 'Non stérilisé',
        'unknown'  => 'Non renseigné',
    ];

    /**
     * @param array<string, string> $labels one of the constants above
     */
    public static function label(array $labels, ?string $key): string
    {
        return null === $key ? '—' : ($labels[$key] ?? $key);
    }

    /** « 12,4 kg » from grams. */
    public static function kilograms(int $grams): string
    {
        return rtrim(rtrim(number_format($grams / 1000, 3, ',', ' '), '0'), ',') . ' kg';
    }

    /**
     * A field value as the screen shows it: weights in kilograms,
     * sterilization in words, anything else as stored.
     */
    public static function value(?string $field, ?string $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return match ($field) {
            'weight'        => is_numeric($value) ? self::kilograms((int) $value) : $value,
            'sterilization' => self::STERILIZATION[$value] ?? $value,
            default         => $value,
        };
    }
}
