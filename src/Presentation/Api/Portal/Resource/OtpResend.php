<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\ResendOtpProcessor;
use App\Presentation\Api\Portal\View\OtpChallengeView;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'OtpResend',
    operations: [
        new Post(
            uriTemplate: '/auth/otp/{challengeId}/resend',
            uriVariables: ['challengeId' => new Link(fromClass: self::class, identifiers: [])],
            status: 202,
            openapi: new Operation(
                tags: ['Authentification'],
                summary: 'Renvoie un nouveau code pour le même challenge.',
                description: 'Le code précédent ne fonctionne plus. Seul le canal `sms` existe pour l\'instant.',
                responses: [
                    '202' => new Response(description: 'Nouveau code envoyé.'),
                    '404' => new Response(description: '`otp_challenge_not_found`.'),
                    '422' => new Response(description: '`validation_failed` (canal inconnu).'),
                    '429' => new Response(description: '`otp_rate_limited` (avant `resendAvailableAt`, ou quotas), avec `Retry-After`.'),
                ],
            ),
            output: OtpChallengeView::class,
            read: false,
            processor: ResendOtpProcessor::class,
        ),
    ],
)]
final class OtpResend
{
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['sms'])]
    public string $channel;
}
