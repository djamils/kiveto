<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Context\ClientPortal\Application\Command\UpdateOwnerProfile\UpdateOwnerProfileHandler;
use App\Presentation\Api\Portal\Input\ProfileAddress;
use App\Presentation\Api\Portal\State\UpdateMeProcessor;
use App\Presentation\Api\Portal\View\AccountView;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'MeUpdate',
    operations: [
        new Patch(
            uriTemplate: '/me',
            inputFormats: ['json' => ['application/json', 'application/merge-patch+json']],
            openapi: new Operation(
                tags: ['Compte'],
                summary: 'Modifie le nom ou l\'adresse du profil Portal.',
                description: 'Chaque champ absent (ou `null`) reste inchangé. Modifie le profil Portal uniquement : les fiches client des cliniques ne changent pas. Adresse enregistrée telle que saisie, sans géocodage. Le téléphone et l\'e-mail ne se modifient pas par cette route.',
                responses: [
                    '200' => new Response(description: 'Le compte modifié.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '404' => new Response(description: '`not_found` (aucun profil : aucune invitation acceptée).'),
                    '422' => new Response(description: '`validation_failed`.'),
                ],
            ),
            output: AccountView::class,
            read: false,
            processor: UpdateMeProcessor::class,
        ),
    ],
)]
final class MeUpdate
{
    #[ApiProperty(schema: ['type' => ['string', 'null'], 'minLength' => 1, 'maxLength' => UpdateOwnerProfileHandler::NAME_MAX_LENGTH])]
    #[Assert\NotBlank(message: 'Le prénom ne peut pas être vide.', allowNull: true, normalizer: 'trim')]
    #[Assert\Length(max: UpdateOwnerProfileHandler::NAME_MAX_LENGTH, maxMessage: 'Le prénom comporte {{ limit }} caractères au plus.', normalizer: 'trim')]
    public ?string $firstName = null;

    #[ApiProperty(schema: ['type' => ['string', 'null'], 'minLength' => 1, 'maxLength' => UpdateOwnerProfileHandler::NAME_MAX_LENGTH])]
    #[Assert\NotBlank(message: 'Le nom ne peut pas être vide.', allowNull: true, normalizer: 'trim')]
    #[Assert\Length(max: UpdateOwnerProfileHandler::NAME_MAX_LENGTH, maxMessage: 'Le nom comporte {{ limit }} caractères au plus.', normalizer: 'trim')]
    public ?string $lastName = null;

    #[Assert\Valid]
    public ?ProfileAddress $address = null;
}
