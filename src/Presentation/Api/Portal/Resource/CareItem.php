<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Context\ClientPortal\Application\Query\ListOwnerCareItems\OwnerCareItem;
use App\Presentation\Api\Portal\State\PetCareItemsProvider;
use App\Presentation\Api\Portal\Support\ApiDate;

/**
 * The latest act of a preventive protocol and where it stands against its
 * due date. The care-items route answers a plain JSON array of it.
 */
#[ApiResource(
    shortName: 'CareItem',
    operations: [
        new GetCollection(
            uriTemplate: '/clinics/{clinicId}/pets/{petId}/care-items',
            uriVariables: [
                'clinicId' => new Link(fromClass: self::class, identifiers: []),
                'petId'    => new Link(fromClass: self::class, identifiers: []),
            ],
            openapi: new Operation(
                tags: ['Animaux'],
                summary: 'Les soins préventifs à suivre pour un animal.',
                description: 'Dernier acte de chaque protocole ayant une prochaine échéance, par échéance croissante. « Aujourd\'hui » = date locale de la clinique. Vide pour un animal décédé ou disparu.',
                responses: [
                    '200' => new Response(description: 'Les soins.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '404' => new Response(description: '`not_found` (pas de lien confirmé, ou animal non visible).'),
                ],
            ),
            provider: PetCareItemsProvider::class,
        ),
    ],
)]
final readonly class CareItem
{
    public function __construct(
        public string $kind,
        public string $label,
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date'])]
        public string $lastDoneOn,
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date'])]
        public string $dueOn,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['overdue', 'due_soon', 'up_to_date']])]
        public string $status,
    ) {
    }

    public static function of(OwnerCareItem $item): self
    {
        return new self($item->kind, $item->label, ApiDate::date($item->lastDoneOn), ApiDate::date($item->dueOn), $item->status->value);
    }
}
