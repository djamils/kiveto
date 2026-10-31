<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\HomeProvider;
use App\Presentation\Api\Portal\View\DocumentView;
use App\Presentation\Api\Portal\View\HomeCareItemView;
use App\Presentation\Api\Portal\View\NextAppointmentView;

#[ApiResource(
    shortName: 'Home',
    operations: [
        new Get(
            uriTemplate: '/clinics/{clinicId}/home',
            uriVariables: ['clinicId' => new Link(fromClass: self::class, identifiers: [])],
            openapi: new Operation(
                tags: ['Animaux'],
                summary: 'L\'accueil du propriétaire dans cette clinique.',
                description: 'Animaux vivants seulement. Prochain rendez-vous, 3 soins aux échéances les plus proches, 2 derniers documents. Une section vide est un tableau vide (`nextAppointment: null`).',
                responses: [
                    '200' => new Response(description: 'L\'accueil.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '404' => new Response(description: '`not_found` (pas de lien confirmé avec la clinique).'),
                ],
            ),
            provider: HomeProvider::class,
        ),
    ],
)]
final readonly class Home
{
    /**
     * @param list<PetSummary>       $pets
     * @param list<HomeCareItemView> $careItems
     * @param list<DocumentView>     $latestDocuments
     */
    public function __construct(
        public ?NextAppointmentView $nextAppointment,
        /** Embedded, never an IRI: the list resource has no item route. */
        #[ApiProperty(readableLink: true)]
        public array $pets,
        public array $careItems,
        public array $latestDocuments,
    ) {
    }
}
