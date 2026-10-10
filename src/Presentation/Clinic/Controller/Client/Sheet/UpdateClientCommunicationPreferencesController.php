<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Client\Sheet;

use App\Context\Client\Application\Command\UpdateClientCommunicationPreferences\UpdateClientCommunicationPreferences;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/clients/{id}/communication-preferences',
    name: 'clinic_clients_communication_preferences_update',
    methods: ['POST'],
)]
final class UpdateClientCommunicationPreferencesController extends AbstractClientSheetAction
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
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

        $saved = $this->attempt(fn () => $this->commandBus->dispatch(new UpdateClientCommunicationPreferences(
            clinicId: $this->clinicId(),
            clientId: $id,
            preferredChannel: $this->trimmed($request, 'preferred_channel'),
            channelsByConsentKind: $channelsByKind,
        )));

        if ($saved) {
            $this->addFlash('success', 'Préférences de communication enregistrées.');
        } else {
            $this->addFlash('error', 'Les préférences de communication n’ont pas pu être enregistrées.');
        }

        return $this->backToSheet($id);
    }
}
