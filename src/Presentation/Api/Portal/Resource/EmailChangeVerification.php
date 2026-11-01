<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\ConfirmEmailChangeProcessor;
use App\Presentation\Api\Portal\View\AccountView;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'EmailChangeVerification',
    operations: [
        new Post(
            uriTemplate: '/me/email-change/verify',
            status: 200,
            openapi: new Operation(
                tags: ['Compte'],
                summary: 'Vérifie le code reçu à la nouvelle adresse : elle devient l\'e-mail vérifié du compte.',
                description: 'Un autre compte qui détenait l\'adresse sans l\'avoir vérifiée la perd ; s\'il l\'a vérifiée → `email_taken` (seulement ici). L\'ancienne adresse vérifiée reçoit un avis de changement. Une fois vérifiée, l\'adresse sert de second facteur de connexion (`resend` par e-mail).',
                responses: [
                    '200' => new Response(description: 'Le compte, e-mail vérifié.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '422' => new Response(description: '`otp_invalid`, `otp_expired`, `otp_attempts_exceeded`, `email_taken` ou `validation_failed`.'),
                ],
            ),
            output: AccountView::class,
            processor: ConfirmEmailChangeProcessor::class,
        ),
    ],
)]
final class EmailChangeVerification
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $challengeId;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{6}$/', message: 'Le code comporte 6 chiffres.')]
    public string $code;
}
