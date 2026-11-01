<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Context\ClientPortal\Application\Command\UpdateOwnerProfile\UpdateOwnerProfileHandler;
use App\Presentation\Api\Portal\State\RegisterProcessor;
use App\Presentation\Api\Portal\View\AcceptedInvitationView;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'Registration',
    operations: [
        new Post(
            uriTemplate: '/auth/register',
            status: 201,
            openapi: new Operation(
                tags: ['Authentification'],
                summary: 'Crée le compte d\'un numéro sans invitation, avec le jeton d\'inscription.',
                description: 'Le `registrationToken` vient de la réponse `registration_required` de `/auth/otp/verify` (15 min, usage unique) et n\'est accepté que par cette route. Rejouer la requête avec le même jeton pendant ses 15 minutes rend le même compte avec une nouvelle paire de jetons (réponse perdue). `email` est ignoré sans erreur quand un autre compte l\'utilise déjà ; il n\'est pas vérifié. 10 inscriptions par heure et par IP.',
                responses: [
                    '201' => new Response(description: 'Paire de jetons et compte.'),
                    '401' => new Response(description: '`onboarding_invalid`.'),
                    '403' => new Response(description: '`account_disabled`.'),
                    '409' => new Response(description: '`account_exists` : un compte existe déjà pour ce numéro, se connecter (le jeton reste utilisable).'),
                    '422' => new Response(description: '`terms_outdated` ou `validation_failed`.'),
                    '429' => new Response(description: '`rate_limited`, avec `Retry-After`.'),
                ],
            ),
            output: AcceptedInvitationView::class,
            processor: RegisterProcessor::class,
        ),
    ],
)]
final class Registration
{
    #[Assert\NotBlank]
    public string $registrationToken;

    #[Assert\NotBlank(message: 'Le prénom ne peut pas être vide.', normalizer: 'trim')]
    #[Assert\Length(max: UpdateOwnerProfileHandler::NAME_MAX_LENGTH, maxMessage: 'Le prénom comporte {{ limit }} caractères au plus.', normalizer: 'trim')]
    public string $firstName;

    #[Assert\NotBlank(message: 'Le nom ne peut pas être vide.', normalizer: 'trim')]
    #[Assert\Length(max: UpdateOwnerProfileHandler::NAME_MAX_LENGTH, maxMessage: 'Le nom comporte {{ limit }} caractères au plus.', normalizer: 'trim')]
    public string $lastName;

    /** Optional, never verified; ignored when another account already uses it. */
    #[Assert\Email(message: 'Adresse e-mail invalide.', normalizer: 'trim')]
    #[Assert\Length(max: 180, maxMessage: 'L\'adresse e-mail comporte {{ limit }} caractères au plus.', normalizer: 'trim')]
    public ?string $email = null;

    /** The `version` of /legal/terms/current. */
    #[Assert\NotBlank]
    public string $termsVersion;
}
