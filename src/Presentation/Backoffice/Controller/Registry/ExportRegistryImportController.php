<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Registry;

use App\Presentation\Backoffice\Controller\Clinic\ShowClinicController;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\PharmaceuticalRegistry\Application\Query\Console\GetRegistryImport;
use App\System\PharmaceuticalRegistry\Application\Query\Console\RegistryImportDetail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Exporter le différentiel »: every change of an import, semicolon CSV.
 */
final class ExportRegistryImportController extends AbstractController
{
    /** The registry's fields in the words of the import page (G4). */
    public const array FIELDS = [
        'commercialName'          => 'libellé',
        'holderLaboratoryLabel'   => 'titulaire de l’AMM',
        'statusCode'              => 'statut',
        'authorizationDate'       => 'date d’AMM',
        'natureCode'              => 'nature',
        'pharmaceuticalFormLabel' => 'forme',
        'atcVetCode'              => 'code ATCvet',
        'permanentIdentifier'     => 'identifiant permanent',
        'presentations'           => 'conditionnements',
        'compositions'            => 'composition',
        'voiesAdministration'     => 'voies d’administration',
    ];
    private const int LIMIT = 50000;

    private const array LABELS = ['CREATE' => 'Ajoutée', 'UPDATE' => 'Modifiée', 'WITHDRAW' => 'Retirée'];

    public function __construct(
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    #[Route(path: '/registry/imports/{id}/export', name: 'backoffice_registry_import_export', requirements: ['id' => ShowClinicController::UUID], methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        $detail = $this->queryBus->ask(new GetRegistryImport($id, null, 1, self::LIMIT));

        if (!$detail instanceof RegistryImportDetail) {
            throw $this->createNotFoundException('Import inconnu.');
        }

        $response = new StreamedResponse(static function () use ($detail): void {
            $out = fopen('php://output', 'w');
            \assert(false !== $out);

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['N° AMM', 'Médicament', 'Laboratoire', 'Changement', 'Champs modifiés'], ';', '"', '');
            foreach ($detail->lines as $line) {
                fputcsv($out, [$line->identifier, (string) $line->name, (string) $line->laboratory, self::LABELS[$line->kind] ?? $line->kind, implode(', ', array_map(static fn (string $field): string => self::FIELDS[$field] ?? $field, $line->changedFields))], ';', '"', '');
            }

            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', \sprintf('attachment; filename="registre-anmv-%s.csv"', $detail->import->downloadedAt->format('Y-m-d')));

        return $response;
    }
}
