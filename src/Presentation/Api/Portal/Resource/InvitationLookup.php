<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\LookupInvitationProcessor;
use App\Presentation\Api\Portal\View\InvitationLookupView;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'InvitationLookup',
    operations: [
        new Post(
            uriTemplate: '/invitations/lookup',
            status: 200,
            openapi: new Operation(
                tags: ['Invitation'],
                summary: 'Affiche une invitation à partir du jeton de son lien (`#t=`).',
                description: 'Le jeton voyage dans le corps, jamais dans l\'URL.',
                responses: [
                    '200' => new Response(description: 'Clinique, e-mail, téléphone masqué, animaux.'),
                    '404' => new Response(description: '`invitation_invalid` (inconnue, ou jeton remplacé par un renvoi).'),
                    '410' => new Response(description: '`invitation_expired` ou `invitation_already_used`.'),
                ],
            ),
            output: InvitationLookupView::class,
            processor: LookupInvitationProcessor::class,
        ),
    ],
)]
final class InvitationLookup
{
    #[Assert\NotBlank]
    public string $token;
}
