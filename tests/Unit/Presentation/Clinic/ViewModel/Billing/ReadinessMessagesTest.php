<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Clinic\ViewModel\Billing;

use App\Context\Billing\Domain\ValueObject\ReadinessRequirement;
use App\Presentation\Clinic\ViewModel\Billing\ReadinessMessages;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class ReadinessMessagesTest extends TestCase
{
    private const string CLIENT = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b';
    private const string RETURN = 'clinic_checkout|admissionId=adm-1';

    public function testBuyerItemsLeadToTheCardOfTheClientSheetWithTheWayBack(): void
    {
        $messages = $this->messages()->for(
            [ReadinessRequirement::BUYER_ADDRESS, ReadinessRequirement::BUYER_SIREN, ReadinessRequirement::BUYER_LEGAL_NAME],
            self::CLIENT,
            self::RETURN,
        );

        $back = '_returnTo=' . rawurlencode(self::RETURN);

        self::assertSame([
            ['text' => 'Adresse du client incomplète', 'href' => '/clients/' . self::CLIENT . '?card=cCoord&' . $back, 'label' => 'Corriger la fiche'],
            ['text' => 'SIREN du client manquant', 'href' => '/clients/' . self::CLIENT . '?card=cFisc&' . $back, 'label' => 'Corriger la fiche'],
            ['text' => 'Raison sociale du client manquante', 'href' => '/clients/' . self::CLIENT . '?card=cFisc&' . $back, 'label' => 'Corriger la fiche'],
        ], $messages);
    }

    public function testBuyerItemsOfANewcomerHaveNoLink(): void
    {
        self::assertSame(
            [['text' => 'Adresse du client incomplète', 'href' => null, 'label' => null]],
            $this->messages()->for([ReadinessRequirement::BUYER_ADDRESS], null, 'clinic_invoices'),
        );
    }

    public function testSellerItemsLeadToTheFieldOfTheSettings(): void
    {
        $messages = $this->messages()->for(
            [
                ReadinessRequirement::SELLER_VAT,
                ReadinessRequirement::SELLER_SIREN,
                ReadinessRequirement::SELLER_ADDRESS,
                ReadinessRequirement::SELLER_LEGAL_NAME,
                ReadinessRequirement::SELLER_NUMBERING_NOT_UNIQUE,
            ],
            self::CLIENT,
            'clinic_invoices',
        );

        self::assertSame([
            ['text' => 'N° de TVA de la clinique invalide', 'href' => '/settings?card=id.tva&_returnTo=clinic_invoices', 'label' => 'Paramètres'],
            ['text' => 'SIREN de la clinique manquant ou invalide', 'href' => '/settings?card=id.siret&_returnTo=clinic_invoices', 'label' => 'Paramètres'],
            ['text' => 'Adresse de la clinique incomplète', 'href' => '/settings?card=id.adr&_returnTo=clinic_invoices', 'label' => 'Paramètres'],
            ['text' => 'Raison sociale de la clinique manquante', 'href' => '/settings?card=id.rs&_returnTo=clinic_invoices', 'label' => 'Paramètres'],
            ['text' => 'Préfixe de numérotation déjà utilisé par un autre établissement du même SIREN', 'href' => '/settings/billing?card=fa.prefixe&_returnTo=clinic_invoices', 'label' => 'Paramètres'],
        ], $messages);
    }

    public function testLineItemsHaveNoLink(): void
    {
        self::assertSame([
            ['text' => 'Une ligne n\'a pas de catégorie fiscale', 'href' => null, 'label' => null],
            ['text' => 'Une ligne est à 0 % de TVA', 'href' => null, 'label' => null],
        ], $this->messages()->for([ReadinessRequirement::LINE_TAX_CATEGORY, ReadinessRequirement::LINE_ZERO_RATE], self::CLIENT, self::RETURN));
    }

    public function testTheFlashComesBackOnlyForItsInvoiceAndOnlyOnce(): void
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $items = [['text' => 'SIREN du client manquant', 'href' => '/clients/x', 'label' => 'Corriger la fiche']];

        ReadinessMessages::flash($request, 'inv-1', $items);
        self::assertSame([], ReadinessMessages::takeFlash($request, 'inv-2'));

        ReadinessMessages::flash($request, 'inv-1', $items);
        self::assertSame($items, ReadinessMessages::takeFlash($request, 'inv-1'));
        self::assertSame([], ReadinessMessages::takeFlash($request, 'inv-1'));
    }

    public function testAMalformedFlashIsIgnored(): void
    {
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);
        $session->getFlashBag()->set(ReadinessMessages::FLASH, ['not json', '{"invoiceId":"inv-1","items":[{"href":"/x"},{"text":"Ok"}]}']);

        self::assertSame([['text' => 'Ok', 'href' => null, 'label' => null]], ReadinessMessages::takeFlash($request, 'inv-1'));
        self::assertSame([], ReadinessMessages::takeFlash(new Request(), 'inv-1'));
    }

    private function messages(): ReadinessMessages
    {
        $routes = new RouteCollection();
        $routes->add('clinic_clients_view', new Route('/clients/{id}'));
        $routes->add('clinic_settings', new Route('/settings'));
        $routes->add('clinic_settings_billing', new Route('/settings/billing'));

        return new ReadinessMessages(new UrlGenerator($routes, new RequestContext()));
    }
}
