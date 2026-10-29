<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\RequestOtpProcessor;
use App\Presentation\Api\Portal\View\OtpChallengeView;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'OtpRequest',
    operations: [
        new Post(
            uriTemplate: '/auth/otp/request',
            status: 202,
            openapi: new Operation(
                tags: ['Authentification'],
                summary: 'Envoie un code de connexion par SMS.',
                description: 'Même réponse que le numéro ait un compte ou non. Quotas : 3/h et 10/24 h par numéro, 30/h par IP.',
                responses: [
                    '202' => new Response(description: 'Code envoyé (ou non, sans que la réponse le dise).'),
                    '422' => new Response(description: '`phone_not_supported` ou `validation_failed`.'),
                    '429' => new Response(description: '`otp_rate_limited`, avec `Retry-After`.'),
                ],
            ),
            output: OtpChallengeView::class,
            processor: RequestOtpProcessor::class,
        ),
    ],
)]
final class OtpRequest
{
    /** E.164, e.g. `+33612345678`. */
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\+[1-9]\d{6,14}$/', message: 'Numéro au format international attendu (+33612345678).')]
    public string $phone;
}
