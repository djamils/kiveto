<?php

declare(strict_types=1);

namespace App\Fixtures\System\Translation\Story;

use App\Fixtures\System\Translation\Factory\TranslationEntryEntityFactory;
use Zenstruck\Foundry\Story;

final class RegulatoryTranslationStory extends Story
{
    public function build(): void
    {
        $entries = [
            'authority_notification.scheduled'         => 'Notification autorité planifiée',
            'authority_notification.sent'              => 'Notification autorité envoyée',
            'authority_notification.cancelled'         => 'Notification autorité annulée',
            'authority_notification.deadline_label'    => 'Délai légal de notification (48h)',
            'microchip_registry_lookup.pending'        => 'Recherche registre puce en cours',
            'microchip_registry_lookup.found'          => 'Animal trouvé dans le registre',
            'microchip_registry_lookup.not_found'      => 'Animal non trouvé dans le registre',
            'microchip_registry_lookup.failed'         => 'Échec de la recherche registre',
            'stray_custody.active'                     => 'Garde errant en cours',
            'stray_custody.deadline_label'             => 'Délai légal de garde (8 jours ouvrés)',
            'stray_custody.closed_handed_to_authority' => 'Remis à l\'autorité compétente',
        ];

        foreach ($entries as $key => $value) {
            TranslationEntryEntityFactory::createOne([
                'appScope'         => 'shared',
                'locale'           => 'fr-FR',
                'domain'           => 'regulatory',
                'translationKey'   => $key,
                'translationValue' => $value,
            ]);
        }
    }
}
