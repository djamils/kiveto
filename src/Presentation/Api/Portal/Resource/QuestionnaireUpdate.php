<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\Input\OngoingTreatment;
use App\Presentation\Api\Portal\State\QuestionnaireUpdateProcessor;
use App\Presentation\Api\Portal\View\QuestionnaireView;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'QuestionnaireUpdate',
    operations: [
        new Put(
            uriTemplate: '/clinics/{clinicId}/appointments/{appointmentId}/questionnaire',
            uriVariables: [
                'clinicId'      => new Link(fromClass: self::class, identifiers: []),
                'appointmentId' => new Link(fromClass: self::class, identifiers: []),
            ],
            openapi: new Operation(
                tags: ['Demandes'],
                summary: 'Remplit ou modifie le questionnaire pré-visite d\'un rendez-vous.',
                description: 'Naturellement idempotente : les réponses remplacent les précédentes. `symptoms` : `none` ne se combine avec aucun autre. Modifiable tant que le rendez-vous occupe son créneau, n\'a pas commencé et qu\'aucune consultation n\'a démarré ; ensuite `questionnaire_locked` (un report ne le rouvre pas). Renvoie la même forme que `GET`.',
                responses: [
                    '200' => new Response(description: 'Le questionnaire enregistré.'),
                    '401' => new Response(description: '`unauthorized` ou `token_expired`.'),
                    '404' => new Response(description: '`not_found` (pas de lien confirmé ; rendez-vous inconnu, d\'un autre client, annulé ou refusé).'),
                    '422' => new Response(description: '`questionnaire_locked` ou `validation_failed` (dont `version` différente de 1).'),
                ],
            ),
            output: QuestionnaireView::class,
            read: false,
            processor: QuestionnaireUpdateProcessor::class,
        ),
    ],
)]
final class QuestionnaireUpdate
{
    public const array SYMPTOMS     = ['none', 'cough', 'vomiting', 'diarrhea', 'appetite_loss', 'itching', 'lameness'];
    public const array TEMPERAMENTS = ['calm', 'stressed', 'may_bite'];

    #[ApiProperty(required: true, schema: ['type' => 'integer', 'enum' => [1]])]
    #[Assert\NotNull(message: 'Version du questionnaire obligatoire.')]
    #[Assert\EqualTo(value: 1, message: 'Version de questionnaire non prise en charge : 1 attendue.')]
    public ?int $version = null;

    /** @var list<string>|null */
    #[ApiProperty(required: true, schema: ['type' => 'array', 'minItems' => 1, 'uniqueItems' => true, 'items' => ['type' => 'string', 'enum' => self::SYMPTOMS]])]
    #[Assert\NotNull(message: 'Liste des symptômes obligatoire.')]
    #[Assert\Count(min: 1, minMessage: 'Indiquez au moins un symptôme, ou « aucun ».')]
    #[Assert\All([new Assert\Choice(choices: self::SYMPTOMS, message: 'Symptôme inconnu.')])]
    public ?array $symptoms = null;

    #[ApiProperty(required: true)]
    #[Assert\NotNull(message: 'Traitement en cours obligatoire.')]
    #[Assert\Valid]
    public ?OngoingTreatment $ongoingTreatment = null;

    #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => self::TEMPERAMENTS])]
    #[Assert\NotBlank(message: 'Tempérament obligatoire.')]
    #[Assert\Choice(choices: self::TEMPERAMENTS, message: 'Tempérament inconnu : calm, stressed ou may_bite.')]
    public ?string $temperament = null;

    #[ApiProperty(schema: ['type' => ['string', 'null'], 'maxLength' => 1000])]
    #[Assert\Length(max: 1000, maxMessage: 'Les remarques comportent {{ limit }} caractères au plus.')]
    public ?string $notes = null;
}
