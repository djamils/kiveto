<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\StartEmailChangeProcessor;
use App\Presentation\Api\Portal\View\EmailVerificationChallengeView;
use App\System\IdentityAccess\Application\Command\StartPortalEmailChange\StartPortalEmailChangeHandler;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'EmailChange',
    operations: [
        new Post(
            uriTemplate: '/me/email-change',
            status: 202,
            openapi: new Operation(
                tags: ['Compte'],
                summary: 'Vérifie le code SMS et envoie un code à la nouvelle adresse.',
                description: 'Le code SMS (`POST /me/email-change/otp`) est vérifié ; un code est envoyé à `email` (challenge `email_verification`, 10 min, 5 essais), à saisir sur `POST /me/email-change/verify`. Sert aussi à revérifier l\'adresse actuelle non vérifiée. Aucune collision n\'est signalée ici.',
                responses: [
                    '202' => new Response(description: 'Code envoyé à la nouvelle adresse.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '422' => new Response(description: '`otp_invalid` (code faux, d\'une autre finalité ou d\'un autre utilisateur), `otp_expired`, `otp_attempts_exceeded` ou `validation_failed`.'),
                ],
            ),
            output: EmailVerificationChallengeView::class,
            processor: StartEmailChangeProcessor::class,
        ),
    ],
)]
final class EmailChange
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $challengeId;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{6}$/', message: 'Le code comporte 6 chiffres.')]
    public string $code;

    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Email(message: 'Cette adresse e-mail n\'est pas valide.', normalizer: 'trim')]
    #[Assert\Length(max: StartPortalEmailChangeHandler::EMAIL_MAX_LENGTH, maxMessage: 'L\'adresse e-mail comporte {{ limit }} caractères au plus.', normalizer: 'trim')]
    public string $email;
}
