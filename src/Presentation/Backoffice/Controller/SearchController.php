<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller;

use App\Context\Clinic\Application\Query\Console\ConsoleClinicRow;
use App\Presentation\Backoffice\Feature\ConsoleFeatures;
use App\Presentation\Backoffice\ViewModel\ConsoleDirectory;
use App\Presentation\Backoffice\ViewModel\User\StaffUserRow;
use App\Presentation\Backoffice\ViewModel\User\UserListBuilder;
use App\System\FeatureCatalog\Application\Query\FeatureView;
use App\System\IdentityAccess\Application\Query\GetAccountSummaries\AccountSummary;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * ⌘K — « Clinique, utilisateur, SIRET… »: clinics (name, tenant, SIRET,
 * legal name), clinic staff (name, e-mail, phone), owners of the Portal
 * (e-mail, phone), features and the console's pages. JSON for the
 * palette, a plain page without JavaScript.
 */
final class SearchController extends AbstractController
{
    private const int PER_GROUP = 6;

    private const array PAGES = [
        ['Cliniques', 'backoffice_clinics', 'clinique'],
        ['Nouvelle clinique', 'backoffice_clinic_new', 'nouvelle clinique créer'],
        ['Utilisateurs', 'backoffice_users', 'utilisateurs personnel comptes'],
        ['Fonctionnalités', 'backoffice_features', 'fonctionnalités catalogue'],
        ['Registre ANMV', 'backoffice_registry', 'registre anmv médicaments import'],
        ['Journal d’audit', 'backoffice_audit', 'journal audit historique'],
        ['Mon compte', 'backoffice_account', 'mon compte profil mot de passe'],
        ['Traductions', 'backoffice_translations', 'traductions'],
    ];

    public function __construct(
        private readonly ConsoleDirectory $directory,
        private readonly UserListBuilder $users,
        private readonly ConsoleFeatures $features,
    ) {
    }

    #[Route(path: '/search', name: 'backoffice_search', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $query  = trim($request->query->getString('q'));
        $groups = mb_strlen($query) < 2 ? [] : $this->groups($query);

        if (str_contains((string) $request->headers->get('Accept'), 'json')) {
            return new JsonResponse(['query' => $query, 'groups' => $groups]);
        }

        return $this->render('backoffice/console/search.html.twig', ['query' => $query, 'groups' => $groups]);
    }

    /**
     * @return list<array{label: string, items: list<array{label: string, sub: string, url: string}>}>
     */
    private function groups(string $query): array
    {
        $needle = mb_strtolower($query);
        $groups = [];

        $clinics  = \array_slice($this->directory->clinics($query), 0, self::PER_GROUP);
        $groups[] = ['label' => 'Cliniques', 'items' => array_map(fn (ConsoleClinicRow $c): array => [
            'label' => $c->name,
            'sub'   => trim(implode(' · ', array_filter([$c->city, $c->slug, $c->isSuspended() ? 'suspendue' : null]))),
            'url'   => $this->generateUrl('backoffice_clinic_show', ['id' => $c->id]),
        ], $clinics)];

        $staff    = \array_slice($this->users->staffRows(null, $query), 0, self::PER_GROUP);
        $groups[] = ['label' => 'Personnel clinique', 'items' => array_map(fn (StaffUserRow $r): array => [
            'label' => $r->name,
            'sub'   => trim(implode(' · ', array_filter([$r->account->email, $r->first()?->clinicName, $r->state['label']]))),
            'url'   => $this->generateUrl('backoffice_user_show', ['id' => $r->account->id]),
        ], $staff)];

        $owners   = $this->users->portal($query, 1, self::PER_GROUP)->items;
        $groups[] = ['label' => 'Propriétaires (Portal)', 'items' => array_map(fn (AccountSummary $a): array => [
            'label' => (string) ($a->email ?? $a->phone ?? 'Compte Portal'),
            'sub'   => trim(implode(' · ', array_filter([null !== $a->email ? $a->phone : null, 'Portal']))),
            'url'   => $this->generateUrl('backoffice_users', ['tab' => 'portal', 'q' => $query]),
        ], $owners)];

        $features = array_values(array_filter($this->features->list(), static fn (FeatureView $f): bool => str_contains(mb_strtolower($f->name . ' ' . $f->code), $needle)));
        $groups[] = ['label' => 'Fonctionnalités', 'items' => array_map(fn (FeatureView $f): array => [
            'label' => $f->name,
            'sub'   => $f->code,
            'url'   => $this->generateUrl('backoffice_feature_show', ['code' => $f->code]),
        ], \array_slice($features, 0, self::PER_GROUP))];

        $pages    = array_values(array_filter(self::PAGES, static fn (array $p): bool => str_contains($p[2], $needle) || str_contains(mb_strtolower($p[0]), $needle)));
        $groups[] = ['label' => 'Pages', 'items' => array_map(fn (array $p): array => [
            'label' => $p[0],
            'sub'   => 'Console',
            'url'   => $this->generateUrl($p[1]),
        ], $pages)];

        $found = [];
        foreach ($groups as $group) {
            if ([] !== $group['items']) {
                $found[] = ['label' => $group['label'], 'items' => array_values($group['items'])];
            }
        }

        return $found;
    }
}
