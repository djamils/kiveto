<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Twig;

use App\Context\Clinic\Application\Query\Clinic\ListClinics\ClinicCollection;
use App\Context\Clinic\Application\Query\Clinic\ListClinics\ListClinics;
use App\Presentation\Backoffice\Security\CurrentAdmin;
use App\Presentation\Backoffice\ViewModel\ConsoleFormat;
use App\Presentation\Backoffice\ViewModel\Shell\ConsoleShell;
use App\Presentation\Backoffice\ViewModel\Shell\ConsoleTab;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Infrastructure\Deployment\Deployment;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class ConsoleRuntime implements RuntimeExtensionInterface
{
    /**
     * The tabs of the console: label, route, the route prefix that makes it
     * active. A tab whose page does not exist yet is not shown; Migrations
     * is shown greyed (pre-alpha).
     */
    private const array TABS = [
        ['Cliniques', 'backoffice_clinics', 'backoffice_clinic'],
        ['Migrations', null, null],
        ['Utilisateurs', 'backoffice_users', 'backoffice_user'],
        ['Fonctionnalités', 'backoffice_features', 'backoffice_feature'],
        ['Registre ANMV', 'backoffice_registry', 'backoffice_registry'],
        ['Journal d’audit', 'backoffice_audit', 'backoffice_audit'],
    ];

    public function __construct(
        private CurrentAdmin $currentAdmin,
        private RouterInterface $router,
        private RequestStack $requestStack,
        private QueryBusInterface $queryBus,
        private ClockInterface $clock,
        #[Autowire(env: 'enum:App\Shared\Infrastructure\Deployment\Deployment:KIVETO_DEPLOYMENT')]
        private Deployment $deployment,
    ) {
    }

    public function shell(): ConsoleShell
    {
        $route = $this->requestStack->getCurrentRequest()?->attributes->getString('_route') ?? '';
        $tabs  = [];

        foreach (self::TABS as [$label, $tabRoute, $prefix]) {
            if (null === $tabRoute) {
                $tabs[] = new ConsoleTab($label, null, preAlpha: true);

                continue;
            }

            if (null === $this->router->getRouteCollection()->get($tabRoute)) {
                continue;
            }

            $tabs[] = new ConsoleTab(
                label: $label,
                url: $this->router->generate($tabRoute),
                active: null !== $prefix && str_starts_with($route, $prefix),
                count: 'backoffice_clinics' === $tabRoute ? $this->clinicCount() : null,
            );
        }

        [$tierLabel, $tierModifier] = match ($this->deployment) {
            Deployment::PRODUCTION => ['Production', 'is-production'],
            Deployment::STAGING    => ['Staging', 'is-staging'],
            Deployment::DEMO       => ['Démo', 'is-demo'],
            Deployment::DEV        => ['Développement', 'is-dev'],
        };

        return new ConsoleShell(
            tierLabel: $tierLabel,
            tierModifier: $tierModifier,
            admin: $this->currentAdmin->find(),
            tabs: $tabs,
            searchUrl: $this->url('backoffice_search'),
            accountUrl: $this->url('backoffice_account'),
            translationsUrl: $this->url('backoffice_translations'),
            auditUrl: $this->url('backoffice_audit'),
        );
    }

    public function when(?\DateTimeImmutable $at): string
    {
        return null === $at ? '—' : ConsoleFormat::when($at, $this->clock->now(), $this->currentAdmin->timeZone());
    }

    public function shortDate(?\DateTimeImmutable $at, bool $withYear = false): string
    {
        return null === $at ? '—' : ConsoleFormat::shortDate($at, $this->currentAdmin->timeZone(), $withYear);
    }

    public function longDate(?\DateTimeImmutable $at): string
    {
        return null === $at ? '—' : ConsoleFormat::longDate($at, $this->currentAdmin->timeZone());
    }

    public function dayDate(?\DateTimeImmutable $at, bool $withTime = false): string
    {
        return null === $at ? '—' : ConsoleFormat::dayDate($at, $this->currentAdmin->timeZone(), $withTime);
    }

    public function shortDayTime(?\DateTimeImmutable $at): string
    {
        return null === $at ? '—' : ConsoleFormat::shortDayTime($at, $this->currentAdmin->timeZone());
    }

    public function logDate(?\DateTimeImmutable $at): string
    {
        return null === $at ? '—' : ConsoleFormat::logDate($at, $this->currentAdmin->timeZone());
    }

    public function time(?\DateTimeImmutable $at): string
    {
        return null === $at ? '—' : $at->setTimezone($this->currentAdmin->timeZone())->format('H:i');
    }

    private function url(string $route): ?string
    {
        return null !== $this->router->getRouteCollection()->get($route) ? $this->router->generate($route) : null;
    }

    private function clinicCount(): int
    {
        $clinics = $this->queryBus->ask(new ListClinics());

        return $clinics instanceof ClinicCollection ? $clinics->total : 0;
    }
}
