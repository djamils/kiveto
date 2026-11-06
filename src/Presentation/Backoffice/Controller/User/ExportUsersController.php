<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\User;

use App\Context\Clinic\Application\Query\Console\ConsoleMemberRow;
use App\Presentation\Backoffice\Audit\ConsoleAuditTrail;
use App\Presentation\Backoffice\Security\CurrentAdmin;
use App\Presentation\Backoffice\ViewModel\ConsoleDirectory;
use App\Presentation\Backoffice\ViewModel\User\UserFilters;
use App\Presentation\Backoffice\ViewModel\User\UserListBuilder;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Exporter »: the users of the list as filtered, in a semicolon CSV
 * (Excel, UTF-8 with BOM). Audited: it holds personal data.
 */
final class ExportUsersController extends AbstractController
{
    /** The owners exported at most at once. */
    private const int PORTAL_LIMIT = 10000;

    public function __construct(
        private readonly UserListBuilder $builder,
        private readonly ConsoleDirectory $directory,
        private readonly ConsoleAuditTrail $auditTrail,
        private readonly CurrentAdmin $currentAdmin,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route(path: '/users/export', name: 'backoffice_users_export', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $filters = UserFilters::fromRequest($request, $this->directory);
        $zone    = $this->currentAdmin->timeZone();
        $date    = static fn (?\DateTimeImmutable $at): string => null !== $at ? $at->setTimezone($zone)->format('Y-m-d H:i') : '';

        if ('portal' === $filters->tab) {
            $header = ['E-mail', 'Téléphone', 'État', 'Créé le', 'Dernière connexion'];
            $lines  = [];
            foreach ($this->builder->portal($filters->search, 1, self::PORTAL_LIMIT)->items as $account) {
                $lines[] = [(string) $account->email, (string) $account->phone, $account->status, $date($account->createdAt), $date($account->lastLoginAt)];
            }
        } else {
            $header = ['Nom', 'E-mail', 'Téléphone', 'Cliniques', 'Rôle', 'État', 'Créé le', 'Dernière connexion'];
            $lines  = [];
            foreach ($this->builder->staffRows($filters->clinicId, $filters->search) as $row) {
                if (!$row->hasStatus($filters->status)) {
                    continue;
                }

                $lines[] = [
                    $row->name,
                    (string) $row->account->email,
                    (string) ($row->account->phone ?? $row->first()?->phone),
                    implode(', ', array_map(static fn (ConsoleMemberRow $m): string => $m->clinicName, $row->memberships)),
                    (string) $row->role(),
                    $row->state['label'],
                    $date($row->account->createdAt),
                    $date($row->account->lastLoginAt),
                ];
            }
        }

        $this->auditTrail->record(
            'users.exported',
            AuditCategory::USERS,
            \sprintf('Export de %d %s', \count($lines), 'portal' === $filters->tab ? 'propriétaires' : 'comptes clinique'),
            details: ['Filtres' => [] !== $filters->params() ? http_build_query($filters->params()) : 'aucun'],
        );

        $response = new StreamedResponse(static function () use ($header, $lines): void {
            $out = fopen('php://output', 'w');
            \assert(false !== $out);

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ';', '"', '');
            foreach ($lines as $line) {
                fputcsv($out, $line, ';', '"', '');
            }

            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', \sprintf(
            'attachment; filename="utilisateurs-%s-%s.csv"',
            'portal' === $filters->tab ? 'portal' : 'cliniques',
            $this->clock->now()->setTimezone($zone)->format('Y-m-d'),
        ));

        return $response;
    }
}
