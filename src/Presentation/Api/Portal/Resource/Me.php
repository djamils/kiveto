<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\MeProvider;
use App\Presentation\Api\Portal\View\AccountView;
use App\Presentation\Api\Portal\View\ClinicLinkView;

#[ApiResource(
    shortName: 'Me',
    operations: [
        new Get(
            uriTemplate: '/me',
            openapi: new Operation(
                tags: ['Compte'],
                summary: 'Le compte et ses cliniques rattachées.',
                description: 'Ne liste pas les invitations en attente : elles apparaissent à la prochaine connexion par code.',
                responses: [
                    '200' => new Response(description: 'Compte et cliniques.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                ],
            ),
            provider: MeProvider::class,
        ),
    ],
)]
final readonly class Me
{
    /**
     * @param list<ClinicLinkView> $clinics
     */
    public function __construct(
        public AccountView $account,
        public array $clinics,
    ) {
    }
}
