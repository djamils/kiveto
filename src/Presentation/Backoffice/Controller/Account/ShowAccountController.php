<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Account;

use App\Presentation\Backoffice\Security\CurrentAdmin;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\Audit\Application\Query\SearchAuditLog\AuditLogPage;
use App\System\Audit\Application\Query\SearchAuditLog\SearchAuditLog;
use App\System\IdentityAccess\Application\Query\GetBackofficeProfile\BackofficeProfileView;
use App\System\IdentityAccess\Application\Query\GetBackofficeProfile\GetBackofficeProfile;
use App\System\IdentityAccess\Application\Query\GetSecondFactorStatus\GetSecondFactorStatus;
use App\System\IdentityAccess\Application\Query\GetSecondFactorStatus\SecondFactorStatus;
use App\System\IdentityAccess\Application\Query\ListUserSessions\ListUserSessions;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * H2 — « Mon compte »: profile, security, sessions, preferences.
 */
final class ShowAccountController extends AbstractController
{
    public const array TIME_ZONES = ['Europe/Paris', 'Europe/London', 'Indian/Reunion', 'Indian/Mayotte', 'America/Guadeloupe', 'America/Martinique', 'America/Cayenne', 'Pacific/Noumea', 'Pacific/Tahiti', 'UTC'];

    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentAdmin $currentAdmin,
    ) {
    }

    #[Route(path: '/account', name: 'backoffice_account', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $admin = $this->currentAdmin->get();

        $profile = $this->queryBus->ask(new GetBackofficeProfile($admin->id));
        \assert($profile instanceof BackofficeProfileView);

        $secondFactor = $this->queryBus->ask(new GetSecondFactorStatus($admin->id));
        \assert($secondFactor instanceof SecondFactorStatus);

        $passwordChanges = $this->queryBus->ask(new SearchAuditLog(perPage: 1, userId: $admin->id, text: 'admin.password_changed'));
        \assert($passwordChanges instanceof AuditLogPage);

        return $this->render('backoffice/console/account/index.html.twig', [
            'profile'          => $profile,
            'second_factor'    => $secondFactor,
            'sessions'         => $this->queryBus->ask(new ListUserSessions($admin->id, $request->getSession()->getId())),
            'password_changed' => $passwordChanges->items[0]->occurredAt ?? null,
            'time_zones'       => self::TIME_ZONES,
        ]);
    }
}
