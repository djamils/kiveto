<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Clinic;

use App\Presentation\Backoffice\ViewModel\Clinic\ClinicLifecycle;
use App\Presentation\Backoffice\ViewModel\Clinic\ClinicListBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * B1 / B2 — « Cliniques ».
 */
final class ListClinicsController extends AbstractController
{
    public function __construct(
        private readonly ClinicListBuilder $builder,
    ) {
    }

    #[Route(path: '/clinics', name: 'backoffice_clinics', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $status = $request->query->getString('status', 'all');
        $status = \in_array($status, [ClinicLifecycle::ACTIVE, ClinicLifecycle::ONBOARDING, ClinicLifecycle::SUSPENDED], true) ? $status : 'all';
        $sort   = $request->query->getString('sort', 'activity');
        $sort   = \array_key_exists($sort, ClinicListBuilder::SORTS) ? $sort : 'activity';
        $search = trim($request->query->getString('q'));

        return $this->render('backoffice/console/clinics/index.html.twig', [
            'list'           => $this->builder->build($status, $search, $sort, max(1, $request->query->getInt('page', 1))),
            'status'         => $status,
            'sort'           => $sort,
            'search'         => $search,
            'sorts'          => ClinicListBuilder::SORTS,
            'features_total' => null,
        ]);
    }
}
