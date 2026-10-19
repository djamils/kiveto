<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Presentation\Clinic\ViewModel\Catalog\CatalogEndpoint;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogPageBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Exporter le catalogue »: acts, articles, packages and the price lists'
 * particular prices in one CSV (semicolons, French decimals, UTF-8 with BOM so
 * a spreadsheet opens it as is) — for the clinic's accountant.
 */
#[Route(path: '/catalog/export', name: 'clinic_catalogue_export', methods: ['GET'])]
final readonly class ExportCatalogController
{
    private const array CATEGORIES = [
        'CONSULTATION'    => 'Consultation', 'SURGERY' => 'Chirurgie', 'IMAGING' => 'Imagerie', 'LABORATORY' => 'Laboratoire',
        'HOSPITALIZATION' => 'Hospitalisation', 'VACCINATION' => 'Vaccination', 'PREVENTION' => 'Prévention', 'OTHER' => 'Autre',
    ];

    private const array TYPES = ['med' => 'Médicament', 'conso' => 'Consommable', 'ali' => 'Alimentation', 'mat' => 'Matériel'];

    public function __construct(
        private CatalogPageBuilder $pages,
        private CatalogEndpoint $endpoint,
    ) {
    }

    public function __invoke(): Response
    {
        $data  = $this->pages->data($this->endpoint->clinicId());
        $rates = [];
        $names = [];

        foreach ($data['fam'] as $family) {
            $rates[$family['v']] = $family['t'];
            $names[$family['v']] = $family['l'];
        }

        $rows = [['Nature', 'Code', 'Libellé', 'Catégorie', 'Famille comptable', 'TVA %', 'Prix HT', 'Prix TTC', 'Prix saisi', 'Statut', 'Grille tarifaire']];
        $line = function (string $nature, array $item, string $category, string $list = '') use (&$rows, $rates, $names): void {
            $family = \is_string($item['fam'] ?? null) ? $item['fam'] : '';
            $rate   = $rates[$family] ?? 0.0;
            $price  = self::cents($item['price'] ?? null);
            $incl   = 'incl' === ($item['pm'] ?? null);
            // The entered figure is exact; the other one is derived from the family's rate for the reader.
            $net   = null === $price ? null : ($incl ? (int) round($price / (1 + $rate / 100)) : $price);
            $gross = null === $price ? null : ($incl ? $price : (int) round($price * (1 + $rate / 100)));

            $rows[] = [
                $nature,
                \is_string($item['code'] ?? null) ? $item['code'] : '',
                \is_string($item['lib'] ?? null) ? $item['lib'] : '',
                $category,
                $names[$family] ?? $family,
                self::number($rate),
                null !== $net ? self::number($net / 100) : '',
                null !== $gross ? self::number($gross / 100) : '',
                null === $price ? '' : ($incl ? 'TTC' : 'HT'),
                true === ($item['arch'] ?? false) ? 'Archivé' : 'Au catalogue',
                $list,
            ];
        };

        foreach ($data['acts'] as $act) {
            $line('Acte', $act, self::CATEGORIES[\is_string($act['cat'] ?? null) ? $act['cat'] : ''] ?? '');
        }

        foreach ($data['articles'] as $article) {
            $line('Article', $article, self::TYPES[\is_string($article['type'] ?? null) ? $article['type'] : ''] ?? '');
        }

        foreach ($data['forfaits'] as $package) {
            $line('Forfait', $package, 'fixe' === ($package['mode'] ?? null) ? 'Prix forfaitaire' : 'Somme des prestations');
        }

        // A particular price is taxed as its prestation, unless the list overrides it.
        $itemFamilies = [];

        foreach ([...$data['acts'], ...$data['articles']] as $item) {
            if (\is_string($item['id'] ?? null) && \is_string($item['fam'] ?? null)) {
                $itemFamilies[$item['id']] = $item['fam'];
            }
        }

        foreach ($data['grilles'] as $list) {
            $tarifs = \is_array($list['tarifs'] ?? null) ? $list['tarifs'] : [];

            foreach ($tarifs as $tarif) {
                if (\is_array($tarif)) {
                    $tarif['fam'] ??= $itemFamilies[\is_string($tarif['item'] ?? null) ? $tarif['item'] : ''] ?? null;
                    $line('Tarif particulier', $tarif, 'ACT' === ($tarif['type'] ?? null) ? 'Acte' : 'Article', \is_string($list['lib'] ?? null) ? $list['lib'] : '');
                }
            }
        }

        $out = fopen('php://temp', 'r+');
        \assert(false !== $out);

        foreach ($rows as $row) {
            fputcsv($out, $row, ';', '"', '');
        }

        rewind($out);
        $csv = "\u{FEFF}" . stream_get_contents($out);
        fclose($out);

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'catalogue-' . date('Y-m-d') . '.csv',
        ));

        return $response;
    }

    private static function cents(mixed $value): ?int
    {
        return \is_int($value) ? $value : null;
    }

    private static function number(float $value): string
    {
        return number_format($value, 2, ',', '');
    }
}
