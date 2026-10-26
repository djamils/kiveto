<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Clinic\ViewModel\Shared;

use App\Presentation\Clinic\ViewModel\Shared\ReturnLink;
use App\Shared\Infrastructure\Http\ReturnToResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class ReturnLinkTest extends TestCase
{
    public function testAWhitelistedTargetGivesItsButton(): void
    {
        self::assertSame(
            ['raw' => 'clinic_checkout|admissionId=adm-1', 'url' => '/checkout/adm-1', 'label' => 'Retour à l\'encaissement'],
            $this->link()->from('clinic_checkout|admissionId=adm-1'),
        );
        self::assertSame('Retour à la facturation', $this->link()->from('clinic_invoices')['label'] ?? null);
    }

    public function testNoButtonForAForeignOrMissingTarget(): void
    {
        foreach (['https://evil.example', '//evil.example', '/\evil.example', 'clinic_settings_team', '', null] as $raw) {
            self::assertNull($this->link()->from($raw), (string) $raw);
        }
    }

    private function link(): ReturnLink
    {
        $routes = new RouteCollection();
        $routes->add('clinic_checkout', new Route('/checkout/{admissionId}'));
        $routes->add('clinic_invoices', new Route('/invoices'));
        $routes->add('clinic_settings_team', new Route('/settings/team'));

        return new ReturnLink(new ReturnToResolver(new UrlGenerator($routes, new RequestContext())));
    }
}
