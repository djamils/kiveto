<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Client\Sheet;

use App\Context\Client\Application\Command\ChangeClientAuthorizedPerson\AuthorizedPersonDto;
use App\Context\Client\Application\Command\ChangeClientAuthorizedPerson\ChangeClientAuthorizedPerson;
use App\Context\Client\Domain\ValueObject\AuthorizedPersonRelationship;
use App\Context\Client\Domain\ValueObject\Civility;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The « Personnes autorisées » card of the client sheet: designates the
 * person who may drop off and collect the animals, or withdraws it when the
 * names come empty.
 */
#[Route('/clients/{id}/authorized-person', name: 'clinic_clients_authorized_person_update', methods: ['POST'])]
final class UpdateClientAuthorizedPersonController extends AbstractClientSheetAction
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        $this->guard($request);

        $firstName    = $this->trimmed($request, 'ap_first_name');
        $lastName     = $this->trimmed($request, 'ap_last_name');
        $civility     = $this->trimmed($request, 'ap_civility') ?? Civility::UNSPECIFIED->value;
        $relationship = $this->trimmed($request, 'ap_relationship') ?? AuthorizedPersonRelationship::OTHER->value;
        $phone        = $this->phone($this->trimmed($request, 'ap_phone'));

        $person = null;

        if (null !== $firstName || null !== $lastName) {
            $valid = null !== $firstName && null !== $lastName
                && null !== Civility::tryFrom($civility)
                && null !== AuthorizedPersonRelationship::tryFrom($relationship)
                && (null === $phone || 1 === preg_match('/^\+[1-9]\d{6,14}$/', $phone));

            if (!$valid) {
                $this->addFlash('error', 'La personne autorisée n’a pas été enregistrée : vérifiez son nom, son lien et son téléphone.');

                return $this->backToSheet($id);
            }

            $person = new AuthorizedPersonDto(
                civility: $civility,
                firstName: $firstName,
                lastName: $lastName,
                relationship: $relationship,
                phoneNumber: $phone,
            );
        }

        $saved = $this->attempt(fn () => $this->commandBus->dispatch(
            new ChangeClientAuthorizedPerson($this->clinicId(), $id, $person),
        ));

        if (!$saved) {
            $this->addFlash('error', 'La personne autorisée n’a pas pu être enregistrée.');

            return $this->backToSheet($id);
        }

        $this->addFlash('success', null === $person ? 'Personnes autorisées · autorisation retirée' : 'Personnes autorisées · liste mise à jour');

        return $this->backToSheet($id, 'cPers');
    }
}
