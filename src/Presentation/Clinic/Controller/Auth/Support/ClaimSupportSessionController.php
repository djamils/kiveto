<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Auth\Support;

use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\ClinicSettingsView;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\GetClinicSettings;
use App\Context\Clinic\Application\Query\Staff\GetStaffPrintedName\GetStaffPrintedName;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Presentation\Clinic\Support\SupportSessionState;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\IdentityAccess\Application\Command\ClaimSupportSession\ClaimSupportSession;
use App\System\IdentityAccess\Application\Query\GetAccountSummaries\AccountSummary;
use App\System\IdentityAccess\Application\Query\GetAccountSummaries\GetAccountSummaries;
use App\System\IdentityAccess\Application\Query\SupportSessionView;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

/**
 * D1 → D2 — the browser arrives from the console with a one-time secret:
 * the session opens, signed in as the person chosen, in their clinic. No
 * sign-in of theirs is recorded (no LoginSuccessEvent): the console audited
 * the start.
 */
final class ClaimSupportSessionController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly CurrentClinicContextInterface $currentClinic,
        /** @var UserProviderInterface<UserInterface> */
        #[Autowire(service: 'App\System\IdentityAccess\Infrastructure\Security\Symfony\ContextUserProvider.clinic')]
        private readonly UserProviderInterface $users,
    ) {
    }

    #[Route(path: '/support/{secret}', name: 'clinic_support_claim', requirements: ['secret' => '[A-Za-z0-9_-]{16,128}'], methods: ['GET'])]
    public function __invoke(Request $request, string $secret): Response
    {
        $view = $this->commandBus->dispatch(new ClaimSupportSession($secret));

        if (!$view instanceof SupportSessionView) {
            return $this->refuse();
        }

        $accounts = $this->queryBus->ask(new GetAccountSummaries([$view->userId]));
        $account  = \is_array($accounts) ? ($accounts[$view->userId] ?? null) : null;

        if (!$account instanceof AccountSummary || null === $account->email || !$account->isActive()) {
            return $this->refuse();
        }

        try {
            $user = $this->users->loadUserByIdentifier($account->email);
        } catch (UserNotFoundException) {
            return $this->refuse();
        }

        $settings = $this->queryBus->ask(new GetClinicSettings($view->clinicId));
        \assert($settings instanceof ClinicSettingsView);
        $name = $this->queryBus->ask(new GetStaffPrintedName($view->clinicId, $view->userId));

        // A clean session: nothing of a previous one in this browser stays.
        $session = $request->getSession();
        $session->invalidate();

        $token = new PostAuthenticationToken($user, 'clinic', $user->getRoles());
        $this->tokenStorage->setToken($token);
        $session->set('_security_clinic', serialize($token));
        $this->currentClinic->setCurrentClinicId(ClinicId::fromString($view->clinicId));

        SupportSessionState::open($session, [
            'sessionId'  => $view->id,
            'clinicId'   => $view->clinicId,
            'clinicName' => $settings->name,
            'userName'   => \is_string($name) ? $name : $account->email,
            'adminId'    => $view->adminId,
            'adminName'  => $view->adminName,
            'mode'       => $view->mode,
            'modeLabel'  => $view->modeLabel,
            'expiresAt'  => $view->expiresAt->getTimestamp(),
        ]);

        return $this->redirectToRoute('clinic_dashboard');
    }

    private function refuse(): Response
    {
        return new Response(
            '<!doctype html><meta charset="utf-8"><title>Session support</title><p style="font-family:system-ui;margin:3rem auto;max-width:32rem">Ce lien de session support n’est plus valide : il sert une seule fois, dans les deux minutes. Relance la session depuis la console.</p>',
            Response::HTTP_GONE,
        );
    }
}
