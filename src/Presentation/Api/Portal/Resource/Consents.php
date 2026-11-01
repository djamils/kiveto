<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Context\ClientPortal\Application\Query\GetPortalConsents\PortalConsents;
use App\Presentation\Api\Portal\State\ConsentsProvider;
use App\Presentation\Api\Portal\View\ConsentView;

#[ApiResource(
    shortName: 'Consents',
    operations: [
        new Get(
            uriTemplate: '/clinics/{clinicId}/consents',
            uriVariables: [
                'clinicId' => new Link(fromClass: self::class, identifiers: []),
            ],
            openapi: new Operation(
                tags: ['Consentements'],
                summary: 'Les consentements de l\'appelant dans la clinique.',
                description: 'Les cinq `kind` sont toujours renvoyés ; `channels: []` = non consenti. Relié à plusieurs fiches de la clinique : union des canaux par `kind` sur les fiches confirmées non archivées ; `grantedAt` = la date la plus récente parmi ces fiches. `effect` : `send_control` (sans consentement, le message n\'est pas envoyé) ou `channel_preference` (le message part de toute façon, le consentement choisit le canal).',
                responses: [
                    '200' => new Response(description: 'Les consentements.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '404' => new Response(description: '`not_found` (pas de fiche liée active dans la clinique).'),
                ],
            ),
            provider: ConsentsProvider::class,
        ),
    ],
)]
final readonly class Consents
{
    /**
     * @param list<ConsentView> $consents
     */
    public function __construct(
        #[ApiProperty(schema: ['type' => ['string', 'null'], 'enum' => ['sms', 'email', 'phone', 'mail', null]])]
        public ?string $preferredChannel,
        public array $consents,
    ) {
    }

    public static function of(PortalConsents $consents): self
    {
        return new self($consents->preferredChannel, array_map(ConsentView::of(...), $consents->consents));
    }
}
