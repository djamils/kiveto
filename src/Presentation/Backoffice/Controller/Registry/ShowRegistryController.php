<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Registry;

use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\PharmaceuticalRegistry\Application\Query\Console\ListRegistryImports;
use App\System\PharmaceuticalRegistry\Application\Query\Console\RegistryStatusView;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * G1 / G2 / G3 — « Registre ANMV »: the latest import (done, running or
 * failed), the history, the source and « Lancer l'import ».
 */
final class ShowRegistryController extends AbstractController
{
    public const string CSRF_ID = 'backoffice_registry';

    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route(path: '/registry', name: 'backoffice_registry', methods: ['GET'])]
    public function __invoke(): Response
    {
        $status = $this->queryBus->ask(new ListRegistryImports());
        \assert($status instanceof RegistryStatusView);

        return $this->render('backoffice/console/registry/index.html.twig', [
            'status'    => $status,
            'latest'    => $status->latest(),
            'inService' => $status->inService(),
            'nextFile'  => $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->modify('next tuesday'),
        ]);
    }
}
