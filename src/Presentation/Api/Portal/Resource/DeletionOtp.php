<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\RequestDeletionOtpProcessor;
use App\Presentation\Api\Portal\View\OtpChallengeView;

#[ApiResource(
    shortName: 'DeletionOtp',
    operations: [
        new Post(
            uriTemplate: '/me/deletion-request/otp',
            status: 202,
            openapi: new Operation(
                tags: ['Compte'],
                summary: 'Envoie par SMS le code qui confirme la suppression du compte.',
                description: 'Code de finalité `account_deletion`, lié au compte, envoyé au mobile du compte (« Code de confirmation de suppression de votre compte Kiveto »). Mêmes quotas que les codes de connexion (par numéro, par jour, par IP). Renvoi : `POST /auth/otp/{challengeId}/resend` avec `sms` (jamais par e-mail).',
                responses: [
                    '202' => new Response(description: 'Code envoyé.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '429' => new Response(description: '`otp_rate_limited`, avec `Retry-After`.'),
                ],
            ),
            input: false,
            output: OtpChallengeView::class,
            read: false,
            processor: RequestDeletionOtpProcessor::class,
        ),
    ],
)]
final class DeletionOtp
{
}
