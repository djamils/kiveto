<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Client\Sheet;

use App\Context\Client\Application\Command\UpdateClientCommunicationPreferences\UpdateClientCommunicationPreferences;
use App\Context\Client\Domain\Exception\StaleConsentsException;
use App\Presentation\Clinic\ViewModel\Client\StaffConsentAuthor;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Saves the « Préférences » card. The changes are signed by the staff member
 * signed in (`clinic` source); the form posts the version of the record it
 * was rendered at, and a record changed since — a withdrawal on the Portal,
 * another user — is not overwritten.
 */
#[Route(
    '/clients/{id}/communication-preferences',
    name: 'clinic_clients_communication_preferences_update',
    methods: ['POST'],
)]
final class UpdateClientCommunicationPreferencesController extends AbstractClientSheetAction
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly StaffConsentAuthor $staffConsentAuthor,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        $this->guard($request);

        $channelsByKind = [];

        foreach ($request->request->all('consent') as $kind => $channels) {
            if (!\is_string($kind) || !\is_array($channels)) {
                continue;
            }

            $channelsByKind[$kind] = array_values(array_filter($channels, \is_string(...)));
        }

        $clinicId = $this->clinicId();
        $author   = $this->staffConsentAuthor->of($clinicId);
        $version  = $request->request->getString('version');

        $failure = null;

        try {
            $this->commandBus->dispatch(new UpdateClientCommunicationPreferences(
                clinicId: $clinicId,
                clientId: $id,
                preferredChannel: $this->trimmed($request, 'preferred_channel'),
                channelsByConsentKind: $channelsByKind,
                source: 'clinic',
                actorType: 'staff',
                actorId: $author['id'],
                authorLabel: $author['label'],
                expectedVersion: ctype_digit($version) ? (int) $version : null,
            ));
        } catch (StaleConsentsException) {
            $this->addFlash('error', 'Les consentements ont changé depuis l\'ouverture de la fiche');

            return $this->backToSheet($id);
        } catch (\Throwable $e) {
            $failure = $e;
        }

        // Any other failure is told the way every sheet form tells it.
        $saved = null === $failure || $this->attempt(static fn () => throw $failure);

        if (!$saved) {
            $this->addFlash('error', 'Les préférences de communication n’ont pas pu être enregistrées.');

            return $this->backToSheet($id);
        }

        $this->addFlash('success', 'Préférences enregistrées.');

        return $this->backToSheet($id, 'cPref');
    }
}
