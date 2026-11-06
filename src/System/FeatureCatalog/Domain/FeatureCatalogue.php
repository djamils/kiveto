<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Domain;

/**
 * The features a clinic can have switched on, in the console's order.
 */
final class FeatureCatalogue
{
    public const string CONTROLLED_SUBSTANCES = 'compliance.controlled_substances';
    public const string ONLINE_BOOKING        = 'portal.online_booking';
    public const string SMS_REMINDERS         = 'notification.sms_reminders';
    public const string HOSPITALISATION       = 'clinic.hospitalisation';
    public const string ELECTRONIC_INVOICING  = 'billing.electronic_invoicing';
    public const string SUPPORT_WRITE_MODE    = 'support.write_mode';

    /**
     * @return list<FeatureDefinition>
     */
    public static function all(): array
    {
        return [
            new FeatureDefinition(
                self::CONTROLLED_SUBSTANCES,
                'Registre des stupéfiants',
                'Registre append-only chaîné SHA-256.',
                'VetApp : registre des stupéfiants et ses mouvements. Module à venir.',
                FeatureStatus::UPCOMING,
                false,
                false,
            ),
            new FeatureDefinition(
                self::ONLINE_BOOKING,
                'Prise de RDV en ligne',
                'La clinique apparaît dans l’app Portal.',
                'Portal : la clinique prend des rendez-vous en ligne (si elle l’a ouvert dans ses paramètres). API : routes de réservation.',
                FeatureStatus::AVAILABLE,
                true,
                true,
            ),
            new FeatureDefinition(
                self::SMS_REMINDERS,
                'Rappels SMS',
                'Rappels de RDV et de vaccins par SMS.',
                'Notifications : les rappels de rendez-vous partent aussi par SMS. Sans elle, l’e-mail seul.',
                FeatureStatus::AVAILABLE,
                true,
                true,
            ),
            new FeatureDefinition(
                self::HOSPITALISATION,
                'Hospitalisation',
                'Module et bandeau du tableau de bord.',
                'VetApp : entrée « Hospitalisation » et bandeau du tableau de bord. Module à venir.',
                FeatureStatus::UPCOMING,
                false,
                false,
            ),
            new FeatureDefinition(
                self::ELECTRONIC_INVOICING,
                'Facturation électronique',
                'Émission via la Plateforme Agréée.',
                'Facturation : les factures aux professionnels partent par la Plateforme Agréée. Sans elle, PDF seul.',
                FeatureStatus::BETA,
                false,
                true,
            ),
            new FeatureDefinition(
                self::SUPPORT_WRITE_MODE,
                'Écriture en session support',
                'Autorise le mode « lecture et écriture » en session support.',
                'Console : une session support peut être ouverte en lecture et écriture pour cette clinique.',
                FeatureStatus::INTERNAL,
                false,
                false,
            ),
        ];
    }

    /**
     * @throws UnknownFeatureException
     */
    public static function get(string $code): FeatureDefinition
    {
        foreach (self::all() as $definition) {
            if ($definition->code === $code) {
                return $definition;
            }
        }

        throw new UnknownFeatureException($code);
    }
}
