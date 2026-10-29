<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\RequestInvitationOtpProcessor;
use App\Presentation\Api\Portal\View\OtpChallengeView;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'InvitationOtpRequest',
    operations: [
        new Post(
            uriTemplate: '/invitations/otp',
            status: 202,
            openapi: new Operation(
                tags: ['Invitation'],
                summary: 'Envoie un code au mobile de l\'invitation.',
                description: 'Quotas : 3/h par invitation, 3/h et 10/24 h par numéro, 30/h par IP.',
                responses: [
                    '202' => new Response(description: 'Code envoyé.'),
                    '404' => new Response(description: '`invitation_invalid`.'),
                    '410' => new Response(description: '`invitation_expired` ou `invitation_already_used`.'),
                    '429' => new Response(description: '`otp_rate_limited`, avec `Retry-After`.'),
                ],
            ),
            output: OtpChallengeView::class,
            processor: RequestInvitationOtpProcessor::class,
        ),
    ],
)]
final class InvitationOtpRequest
{
    #[Assert\NotBlank]
    public string $token;
}
