<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Client\Profile;

use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Application\Query\GetClientById\GetClientById;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Presentation\Clinic\ViewModel\Client\ClientSheetBuilder;
use App\Presentation\Clinic\ViewModel\Shared\ReturnLink;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/clients/{id}', name: 'clinic_clients_view', methods: ['GET'])]
final class ViewClientController extends AbstractController
{
    /** The cards a link may open: the contact details window and the tax profile. */
    private const array CARDS = ['cCoord', 'cFisc'];

    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ClientSheetBuilder $sheetBuilder,
        private readonly ClockInterface $clock,
        private readonly ReturnLink $returnLink,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $client = $this->queryBus->ask(new GetClientById(
            clinicId: $currentClinicId->toString(),
            clientId: $id,
        ));

        if (!$client instanceof ClientView) {
            throw $this->createNotFoundException('Client introuvable.');
        }

        $clinic = $this->queryBus->ask(new GetClinic($currentClinicId->toString()));
        \assert($clinic instanceof ClinicDto);

        $clinicTz = TimeZone::fromString($clinic->timeZone)->toNative();
        $now      = $this->clock->now();

        return $this->render('clinic/clients/view/index.html.twig', [
            'client' => $client,
            'sheet'  => $this->sheetBuilder->build($client, $clinicTz, $now),
            // The card a form was just saved from, flashed green by the page.
            'savedCard' => $request->query->getString('saved'),
            // Opened from elsewhere (a refused checkout): the card to open, and the way back.
            'openCard'          => \in_array($request->query->getString('card'), self::CARDS, true) ? $request->query->getString('card') : '',
            'returnLink'        => $this->returnLink->from($request->query->getString('_returnTo')),
            'today'             => $now->setTimezone($clinicTz)->format('Y-m-d'),
            'currentClinicId'   => $currentClinicId->toString(),
            'currentClinicName' => $clinic->name,
        ]);
    }
}
