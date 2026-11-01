<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\RequestEmailChangeOtpProcessor;
use App\Presentation\Api\Portal\View\OtpChallengeView;

#[ApiResource(
    shortName: 'EmailChangeOtp',
    operations: [
        new Post(
            uriTemplate: '/me/email-change/otp',
            status: 202,
            openapi: new Operation(
                tags: ['Compte'],
                summary: 'Envoie par SMS le code qui confirme un changement d\'e-mail.',
                description: 'Code de finalité `email_change`, lié au compte, envoyé au mobile du compte (« Code de confirmation de changement d\'e-mail »). Mêmes quotas que les codes de connexion (par numéro, par jour, par IP). Renvoi : `POST /auth/otp/{challengeId}/resend` avec `sms` (jamais par e-mail).',
                responses: [
                    '202' => new Response(description: 'Code envoyé.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '429' => new Response(description: '`otp_rate_limited`, avec `Retry-After`.'),
                ],
            ),
            input: false,
            output: OtpChallengeView::class,
            read: false,
            processor: RequestEmailChangeOtpProcessor::class,
        ),
    ],
)]
final class EmailChangeOtp
{
}
