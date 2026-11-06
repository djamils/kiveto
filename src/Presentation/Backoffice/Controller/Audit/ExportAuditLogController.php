<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Audit;

use App\Presentation\Backoffice\Security\CurrentAdmin;
use App\Presentation\Backoffice\ViewModel\Audit\AuditLogFilters;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Audit\Application\Query\AuditDetailView;
use App\System\Audit\Application\Query\AuditEntryView;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Exporter (CSV) » — the entries of the current filters, semicolon
 * separated with a BOM so that a French spreadsheet opens them as is. The
 * IP addresses are exported in full: the file is for an investigation.
 */
final class ExportAuditLogController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentAdmin $currentAdmin,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route(path: '/audit/export', name: 'backoffice_audit_export', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        /** @var list<AuditEntryView> $entries */
        $entries = $this->queryBus->ask(AuditLogFilters::fromRequest($request)->export());
        $zone    = $this->currentAdmin->timeZone();

        $response = new StreamedResponse(static function () use ($entries, $zone): void {
            $out = fopen('php://output', 'w');
            \assert(false !== $out);

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'Acteur', 'Type d’acteur', 'Action', 'Code', 'Catégorie', 'Clinique', 'IP', 'Détail'], ';', '"', '');

            foreach ($entries as $entry) {
                fputcsv($out, [
                    $entry->occurredAt->setTimezone($zone)->format('Y-m-d H:i:s'),
                    $entry->actorName ?? 'Inconnu',
                    AuditLogFilters::ACTORS[$entry->actorKind] ?? $entry->actorKind,
                    $entry->summary,
                    $entry->action,
                    AuditLogFilters::CATEGORIES[$entry->category] ?? $entry->category,
                    $entry->clinicName ?? '',
                    $entry->ipAddress ?? '',
                    implode(' · ', array_map(static fn (AuditDetailView $d): string => $d->label . ' : ' . $d->value, $entry->details)),
                ], ';', '"', '');
            }

            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', \sprintf(
            'attachment; filename="journal-audit-%s.csv"',
            $this->clock->now()->setTimezone($zone)->format('Y-m-d'),
        ));

        return $response;
    }
}
