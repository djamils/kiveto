<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Feature;

use App\Presentation\Backoffice\Feature\ConsoleFeatures;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * F2 / F3 — a feature: its clinics (filters, switches, switched by the
 * dozen), its global settings, its kill switch, its history, and
 * « Activer pour toutes ».
 */
final class ShowFeatureController extends AbstractController
{
    public const string CSRF_ID = 'backoffice_feature';

    public function __construct(
        private readonly ConsoleFeatures $features,
    ) {
    }

    #[Route(path: '/features/{code}', name: 'backoffice_feature_show', requirements: ['code' => ListFeaturesController::CODE], methods: ['GET'])]
    public function __invoke(Request $request, string $code): Response
    {
        $filters = self::filters($request);
        $detail  = $this->features->detail($code, $filters['q'], $filters['state'], $filters['plan']);

        if (null === $detail) {
            throw $this->createNotFoundException('Fonctionnalité inconnue.');
        }

        return $this->render('backoffice/console/features/show.html.twig', [
            'detail'      => $detail,
            'filters'     => $filters,
            'badges'      => ConsoleFeatures::BADGES,
            'statuses'    => ['available' => 'Disponible', 'beta' => 'Bêta', 'internal' => 'Interne'],
            'audiences'   => ConsoleFeatures::AUDIENCES,
            'open_dialog' => 'all' === $request->query->getString('dialog') && [] !== $detail['missing'] ? 'dialog-all' : '',
        ]);
    }

    /**
     * The filters of the clinics table, from a query or a form.
     *
     * @return array{q: string, state: string, plan: string}
     */
    public static function filters(Request $request): array
    {
        $bag   = $request->isMethod('POST') ? $request->request : $request->query;
        $state = $bag->getString('state', 'all');
        $plan  = $bag->getString('plan', 'all');

        return [
            'q'     => mb_substr(trim($bag->getString('q')), 0, 80),
            'state' => \in_array($state, ConsoleFeatures::STATES, true) ? $state : 'all',
            'plan'  => \in_array($plan, ConsoleFeatures::PLANS, true) ? $plan : 'all',
        ];
    }
}
