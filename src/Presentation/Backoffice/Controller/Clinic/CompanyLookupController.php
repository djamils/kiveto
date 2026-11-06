<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Clinic;

use App\Context\Client\Application\Port\CompanySummary;
use App\Context\Client\Application\Query\SearchCompanyRegistry\CompanySearchResult;
use App\Context\Client\Application\Query\SearchCompanyRegistry\SearchCompanyRegistry;
use App\Context\Client\Application\Query\VerifyVatNumber\VatNumberCheck;
use App\Context\Client\Application\Query\VerifyVatNumber\VerifyVatNumber;
use App\Context\Clinic\Application\Query\Clinic\CheckClinicSlug\CheckClinicSlug;
use App\Context\Clinic\Application\Query\Clinic\CheckClinicSlug\ClinicSlugCheck;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\ValueObject\FrenchVatNumber;
use App\Shared\Domain\ValueObject\Siren;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * B3 — the live lookups of the creation form (JSON): `what=company` searches
 * the company registry (SIRET, SIREN or name), `what=vat` checks a VAT
 * number with VIES, `what=tenant` tells whether a tenant id is free.
 */
final class CompanyLookupController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    #[Route(path: '/clinics/new/lookup', name: 'backoffice_clinic_lookup', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $value = trim($request->query->getString('q'));

        return match ($request->query->getString('what')) {
            'vat'    => $this->json(['check' => $this->vat($value)->value]),
            'tenant' => $this->json(['check' => $this->tenant($value)->value]),
            default  => $this->companies($value),
        };
    }

    private function companies(string $query): JsonResponse
    {
        if (mb_strlen($query) < SearchCompanyRegistry::MIN_LENGTH) {
            return $this->json(['available' => true, 'hits' => []]);
        }

        $result = $this->queryBus->ask(new SearchCompanyRegistry($query, 6));
        \assert($result instanceof CompanySearchResult);

        return $this->json([
            'available' => $result->available,
            'reason'    => $result->reason,
            'hits'      => array_map(static fn (CompanySummary $hit): array => [
                'siren'      => $hit->siren,
                'siret'      => $hit->headOfficeSiret,
                'name'       => $hit->name,
                'legalForm'  => $hit->legalFormLabel,
                'naf'        => $hit->nafCode,
                'active'     => $hit->isActive,
                'street'     => $hit->addressLine,
                'postalCode' => $hit->postalCode,
                'city'       => $hit->city,
                'vat'        => self::vatOf($hit->siren),
            ], $result->hits),
        ]);
    }

    private function vat(string $number): VatNumberCheck
    {
        $check = $this->queryBus->ask(new VerifyVatNumber($number));
        \assert($check instanceof VatNumberCheck);

        return $check;
    }

    private function tenant(string $slug): ClinicSlugCheck
    {
        $check = $this->queryBus->ask(new CheckClinicSlug(mb_strtolower($slug)));
        \assert($check instanceof ClinicSlugCheck);

        return $check;
    }

    private static function vatOf(string $siren): ?string
    {
        try {
            return FrenchVatNumber::forSiren(Siren::fromInput($siren))->value();
        } catch (\LogicException) {
            return null;
        }
    }
}
