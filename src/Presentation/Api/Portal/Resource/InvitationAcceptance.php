<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\AcceptInvitationProcessor;
use App\Presentation\Api\Portal\View\AcceptedInvitationView;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'InvitationAcceptance',
    operations: [
        new Post(
            uriTemplate: '/invitations/accept',
            status: 200,
            openapi: new Operation(
                tags: ['Invitation'],
                summary: 'Accepte une invitation par le lien : jeton + code reçu par SMS.',
                description: 'Rejouable : la même requête (même code, avant son expiration) après une réponse perdue renvoie une paire neuve.',
                responses: [
                    '200' => new Response(description: 'Paire de jetons et compte.'),
                    '403' => new Response(description: '`account_disabled`.'),
                    '404' => new Response(description: '`invitation_invalid`.'),
                    '410' => new Response(description: '`invitation_expired` ou `invitation_already_used`.'),
                    '422' => new Response(description: '`otp_invalid`, `otp_expired`, `otp_attempts_exceeded`, `terms_outdated` ou `validation_failed`.'),
                ],
            ),
            output: AcceptedInvitationView::class,
            processor: AcceptInvitationProcessor::class,
        ),
    ],
)]
final class InvitationAcceptance
{
    #[Assert\NotBlank]
    public string $token;

    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $challengeId;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{6}$/', message: 'Le code comporte 6 chiffres.')]
    public string $code;

    /** The `version` of /legal/terms/current. */
    #[Assert\NotBlank]
    public string $termsVersion;
}
