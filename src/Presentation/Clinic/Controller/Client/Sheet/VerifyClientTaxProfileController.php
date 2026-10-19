<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Client\Sheet;

use App\Context\Client\Application\Command\RequestTaxProfileVerification\RequestTaxProfileVerification;
use App\Context\Client\Domain\Exception\ClientNotFoundException;
use App\Context\Client\Domain\TaxProfile\VerificationRequestOutcome;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Revérifier » on the « Profil fiscal » card: queues a new check of the
 * company and its VAT number, at most once a day.
 */
#[Route('/clients/{id}/tax-profile/verify', name: 'clinic_clients_tax_profile_verify', methods: ['POST'])]
final class VerifyClientTaxProfileController extends AbstractClientSheetAction
{
    public function __construct(private readonly CommandBusInterface $commandBus)
    {
    }

    public function __invoke(string $id, Request $request): Response
    {
        $this->guard($request);

        // Not through attempt(): an unknown client (or one of another clinic) is a 404.
        try {
            $outcome = $this->commandBus->dispatch(new RequestTaxProfileVerification($this->clinicId(), $id));
        } catch (ClientNotFoundException) {
            throw $this->createNotFoundException();
        }
        \assert($outcome instanceof VerificationRequestOutcome);

        match ($outcome) {
            VerificationRequestOutcome::QUEUED          => $this->addFlash('success', 'Vérification lancée : le résultat s’affichera sur la fiche d’ici quelques minutes.'),
            VerificationRequestOutcome::ALREADY_PENDING => $this->addFlash('info', 'Vérification déjà en cours.'),
            VerificationRequestOutcome::ARCHIVED        => $this->addFlash('error', 'Client archivé : la vérification n’est pas relancée.'),
            VerificationRequestOutcome::NOT_APPLICABLE  => $this->addFlash('error', 'Seul un professionnel assujetti avec un SIREN peut être vérifié.'),
        };

        return $this->backToSheet($id, 'cFisc');
    }
}
