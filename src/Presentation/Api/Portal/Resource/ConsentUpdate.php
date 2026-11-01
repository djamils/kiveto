<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\UpdateConsentProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'ConsentUpdate',
    operations: [
        new Put(
            uriTemplate: '/clinics/{clinicId}/consents/{kind}',
            uriVariables: [
                'clinicId' => new Link(fromClass: self::class, identifiers: []),
                'kind'     => new Link(fromClass: self::class, identifiers: []),
            ],
            openapi: new Operation(
                tags: ['Consentements'],
                summary: 'Choisit les canaux d\'un consentement.',
                description: '`kind` : `appointment_reminders`, `vaccination_reminders`, `clinical_documents`, `billing` ou `marketing`. `channels` vide = retrait (types `send_control` seulement). S\'applique à chaque fiche confirmée non archivée de la clinique, en une transaction ; chaque changement ajoute des lignes d\'historique (source `portal`, auteur = nom du profil). Ajouter un canal = consentement daté du jour. Renvoie la même forme que `GET /clinics/{clinicId}/consents`.',
                responses: [
                    '200' => new Response(description: 'Les consentements après modification.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '404' => new Response(description: '`not_found` (pas de fiche liée active, ou `kind` inconnu).'),
                    '409' => new Response(description: '`conflict` : modification concurrente, relire puis réessayer.'),
                    '422' => new Response(description: '`validation_failed` (canal hors `sms`|`email`, ou liste vide sur `billing` / `clinical_documents`).'),
                ],
            ),
            output: Consents::class,
            read: false,
            processor: UpdateConsentProcessor::class,
        ),
    ],
)]
final class ConsentUpdate
{
    /** @var list<string> */
    #[ApiProperty(required: true, schema: ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['sms', 'email']]])]
    #[Assert\NotNull(message: 'Liste des canaux obligatoire.')]
    #[Assert\All([new Assert\Choice(choices: ['sms', 'email'], message: 'Canal non pris en charge : sms ou email.')])]
    public array $channels;
}
