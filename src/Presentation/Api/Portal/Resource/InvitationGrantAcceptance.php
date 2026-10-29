<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\AcceptInvitationWithGrantProcessor;
use App\Presentation\Api\Portal\View\AcceptedInvitationView;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'InvitationGrantAcceptance',
    operations: [
        new Post(
            uriTemplate: '/invitations/{invitationId}/accept',
            uriVariables: ['invitationId' => new Link(fromClass: self::class, identifiers: [])],
            status: 200,
            openapi: new Operation(
                tags: ['Invitation'],
                summary: 'Accepte une invitation après connexion par numéro, avec le jeton d\'onboarding.',
                description: 'Le jeton d\'onboarding vient de `/auth/otp/verify` et n\'est accepté que par cette route. Après l\'acceptation de la dernière invitation du numéro, il est révoqué : en cas de `401 onboarding_invalid` après une réponse perdue, refaire la connexion par numéro.',
                responses: [
                    '200' => new Response(description: 'Paire de jetons et compte.'),
                    '401' => new Response(description: '`onboarding_invalid`.'),
                    '403' => new Response(description: '`account_disabled`.'),
                    '404' => new Response(description: '`invitation_invalid`.'),
                    '422' => new Response(description: '`terms_outdated` ou `validation_failed`.'),
                ],
            ),
            output: AcceptedInvitationView::class,
            read: false,
            processor: AcceptInvitationWithGrantProcessor::class,
        ),
    ],
)]
final class InvitationGrantAcceptance
{
    #[Assert\NotBlank]
    public string $onboardingToken;

    /** The `version` of /legal/terms/current. */
    #[Assert\NotBlank]
    public string $termsVersion;
}
