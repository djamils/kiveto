<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Client\Sheet;

use App\Context\Client\Application\Command\UpdateClientTaxProfile\UpdateClientTaxProfile;
use App\Presentation\Clinic\ViewModel\Client\TaxProfileInput;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The « Profil fiscal » card of the client sheet: the shared tax-profile
 * block (`fisc`, `rs`, `siren`, `tva`). An archived client cannot change it.
 */
#[Route('/clients/{id}/tax-profile', name: 'clinic_clients_tax_profile_update', methods: ['POST'])]
final class UpdateClientTaxProfileController extends AbstractClientSheetAction
{
    public function __construct(private readonly CommandBusInterface $commandBus)
    {
    }

    public function __invoke(string $id, Request $request): Response
    {
        $this->guard($request);

        $input = TaxProfileInput::fromArray($request->request->all());
        $error = null === $input ? 'choisissez le statut fiscal.' : $input->firstError();

        if (null !== $error || null === $input) {
            $this->addFlash('error', 'Le profil fiscal n’a pas été enregistré : ' . mb_lcfirst((string) $error));

            return $this->backToSheet($id);
        }

        $clinicId = $this->clinicId();
        $saved    = $this->attempt(fn () => $this->commandBus->dispatch(
            new UpdateClientTaxProfile($clinicId, $id, $input->toClientDto()),
        ));

        if (!$saved) {
            $this->addFlash('error', 'Le profil fiscal n’a pas été enregistré : la fiche est archivée ou introuvable.');

            return $this->backToSheet($id);
        }

        $this->addFlash('success', 'Profil fiscal enregistré.');

        return $this->backToSheet($id, 'cFisc');
    }
}
