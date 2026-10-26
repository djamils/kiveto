<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Clinic\ViewModel\Client;

use App\Context\Client\Application\Port\CompanySummary;
use App\Context\Client\Application\Query\SearchCompanyRegistry\CompanySearchResult;
use App\Presentation\Clinic\ViewModel\Client\CompanyHitPresenter;
use PHPUnit\Framework\TestCase;

final class CompanyHitPresenterTest extends TestCase
{
    public function testAHitCarriesItsHeadOfficeAndItsVatNumber(): void
    {
        $hit = new CompanyHitPresenter()->hit(new CompanySummary('523456788', 'Élevage des Calanques', 'EARL', '01.45Z', false, true, null, '52345678800018', '12 Chemin des Goudes', '13008', 'Marseille'));

        self::assertSame([
            'siren'   => '523456788',
            'n'       => 'Élevage des Calanques',
            'forme'   => 'EARL',
            'naf'     => '01.45Z',
            'ass'     => true,
            'cesse'   => null,
            'ets'     => [['siret' => '52345678800018', 'l' => 'Siège', 'a' => '12 Chemin des Goudes, 13008 Marseille']],
            'adresse' => ['rue' => '12 Chemin des Goudes', 'cp' => '13008', 'ville' => 'Marseille'],
            'tva'     => 'FR31523456788',
        ], $hit);
    }

    public function testACeasedCompanyWithOrWithoutDate(): void
    {
        $presenter = new CompanyHitPresenter();

        self::assertSame('2025-12-31', $presenter->hit(new CompanySummary('441569738', 'GAEC du Mont', 'GAEC', null, false, false, '2025-12-31', null, null, null, null))['cesse']);
        $undated = $presenter->hit(new CompanySummary('441569738', 'GAEC du Mont', null, null, false, false, null, null, null, null, 'Tulle'));
        self::assertTrue($undated['cesse']);
        self::assertSame('', $undated['forme']);
        self::assertSame([['siret' => '', 'l' => 'Siège', 'a' => 'Tulle']], $undated['ets']);
    }

    public function testAnAssociationIsNotProposedAsVatLiable(): void
    {
        self::assertFalse(new CompanyHitPresenter()->hit(new CompanySummary('775612344', 'Refuge des Collines', 'Association', null, true, true, null, null, null, null, null))['ass']);
    }

    public function testAMalformedSirenHasNoVatSuggestion(): void
    {
        self::assertNull(new CompanyHitPresenter()->hit(new CompanySummary('123456789', 'X', null, null, false, true, null, null, null, null, null))['tva']);
    }

    public function testTheResultTellsAnOutage(): void
    {
        self::assertSame(['available' => false, 'reason' => 'down', 'items' => []], new CompanyHitPresenter()->result(new CompanySearchResult(false, 'down', [])));
    }
}
