<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Client\Sheet;

use App\Context\Client\Application\Command\ReplaceClientContactMethods\ReplaceClientContactMethods;
use App\Context\Client\Application\Command\UpdateClientIdentity\UpdateClientIdentity;
use App\Context\Client\Application\Command\UpdateClientPostalAddress\PostalAddressDto;
use App\Context\Client\Application\Command\UpdateClientPostalAddress\UpdateClientPostalAddress;
use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Application\Query\GetClientById\GetClientById;
use App\Context\Client\Domain\ValueObject\Civility;
use App\Presentation\Clinic\ViewModel\Client\ClientContactSlots;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The « Coordonnées » dialog of the client sheet: civility, identity,
 * postal address and contact details in one form. Only what actually changed
 * is sent to the domain.
 */
#[Route('/clients/{id}/profile', name: 'clinic_clients_profile_update', methods: ['POST'])]
final class UpdateClientProfileController extends AbstractClientSheetAction
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        $this->guard($request);

        $clinicId = $this->clinicId();
        $client   = $this->queryBus->ask(new GetClientById($clinicId, $id));

        if (!$client instanceof ClientView) {
            throw $this->createNotFoundException('Client introuvable.');
        }

        $error = $this->validate($request, $client);

        if (null !== $error) {
            $this->addFlash('error', 'Les coordonnées n’ont pas été enregistrées : ' . $error);

            return $this->backToSheet($id);
        }

        $saved = $this->attempt(function () use ($clinicId, $client, $request): void {
            $this->updateIdentity($clinicId, $client, $request);
            $this->updateContactMethods($clinicId, $client, $request);
            $this->updateAddress($clinicId, $client, $request);
        });

        if (!$saved) {
            $this->addFlash('error', 'Les coordonnées n’ont été enregistrées qu’en partie : vérifiez les valeurs saisies.');

            return $this->backToSheet($id);
        }

        $this->addFlash('success', 'Coordonnées enregistrées.');

        return $this->backToSheet($id, 'head');
    }

    /**
     * Each section is its own command, hence its own transaction: everything
     * the domain is known to refuse is checked here first, so that a mistake
     * in one section does not leave the others half saved.
     */
    private function validate(Request $request, ClientView $client): ?string
    {
        $mobile   = $this->phone($this->trimmed($request, 'mobile'));
        $landline = $this->phone($this->trimmed($request, 'landline'));
        $email    = $this->email($request);
        $street   = $this->trimmed($request, 'street');
        $city     = $this->trimmed($request, 'city');
        $civility = $this->trimmed($request, 'civility');

        if (null === $this->trimmed($request, 'first_name') || null === $this->trimmed($request, 'last_name')) {
            return 'le prénom et le nom sont obligatoires.';
        }

        if (null !== $civility && null === Civility::tryFrom($civility)) {
            return 'la civilité est inconnue.';
        }

        $slots = ClientContactSlots::fromContactMethods($client->contactMethods);

        if (null === $mobile && null === $landline && null === $email && !$slots->hasOthers()) {
            return 'au moins un moyen de contact est obligatoire.';
        }

        if (null !== $email && false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return 'l’adresse e-mail est invalide.';
        }

        if (null !== $mobile && $mobile === $landline) {
            return 'les deux numéros de téléphone sont identiques.';
        }

        foreach ([$mobile, $landline] as $phone) {
            if (null !== $phone && 1 !== preg_match('/^\+[1-9]\d{6,14}$/', $phone)) {
                return 'un numéro de téléphone est invalide.';
            }
        }

        $hasPostalCode = null !== $this->trimmed($request, 'postal_code');

        if ((null === $street) !== (null === $city) || ($hasPostalCode && null === $street)) {
            return 'une adresse nécessite au moins la rue et la ville.';
        }

        return null;
    }

    /**
     * The domain stores e-mail addresses in lower case; comparing in the same
     * form keeps "Bob@x.com" from being taken for a new address.
     */
    private function email(Request $request): ?string
    {
        $email = $this->trimmed($request, 'email');

        return null === $email ? null : mb_strtolower($email);
    }

    private function updateIdentity(string $clinicId, ClientView $client, Request $request): void
    {
        $firstName = $this->trimmed($request, 'first_name') ?? '';
        $lastName  = $this->trimmed($request, 'last_name') ?? '';
        $civility  = $this->trimmed($request, 'civility') ?? $client->civility;

        if ($firstName === $client->firstName && $lastName === $client->lastName && $civility === $client->civility) {
            return;
        }

        $this->commandBus->dispatch(new UpdateClientIdentity($clinicId, $client->id, $firstName, $lastName, $civility));
    }

    private function updateContactMethods(string $clinicId, ClientView $client, Request $request): void
    {
        $mobile   = $this->phone($this->trimmed($request, 'mobile'));
        $landline = $this->phone($this->trimmed($request, 'landline'));
        $email    = $this->email($request);
        $slots    = ClientContactSlots::fromContactMethods($client->contactMethods);

        $unchanged = $mobile === $this->phone($slots->mobile?->value)
            && $landline === $this->phone($slots->landline?->value)
            && $email === $slots->email?->value;

        if ($unchanged) {
            return;
        }

        $this->commandBus->dispatch(new ReplaceClientContactMethods(
            $clinicId,
            $client->id,
            $slots->withValues($mobile, $landline, $email),
        ));
    }

    private function updateAddress(string $clinicId, ClientView $client, Request $request): void
    {
        $street     = $this->trimmed($request, 'street');
        $street2    = $this->trimmed($request, 'street2');
        $city       = $this->trimmed($request, 'city');
        $postalCode = $this->trimmed($request, 'postal_code');
        $current    = $client->postalAddress;

        if (null === $street || null === $city) {
            if (null !== $current) {
                $this->commandBus->dispatch(new UpdateClientPostalAddress($clinicId, $client->id, null));
            }

            return;
        }

        $unchanged = null !== $current
            && $current->streetLine1 === $street
            && $current->streetLine2 === $street2
            && $current->city === $city
            && $current->postalCode === $postalCode;

        if ($unchanged) {
            return;
        }

        $this->commandBus->dispatch(new UpdateClientPostalAddress($clinicId, $client->id, new PostalAddressDto(
            streetLine1: $street,
            city: $city,
            // The sheet edits the usual lines; what it does not show is kept as it was.
            countryCode: $current->countryCode ?? 'FR',
            streetLine2: $street2,
            postalCode: $postalCode,
            region: $current?->region,
        )));
    }
}
