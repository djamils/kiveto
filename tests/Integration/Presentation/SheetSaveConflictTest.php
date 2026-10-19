<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation;

use App\Presentation\Clinic\Controller\Client\Sheet\UpdateClientTaxProfileController;
use App\Shared\Application\Bus\CommandBusInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * AC 30: a sheet save that loses a race to a recorded verification (or any
 * concurrent save) comes back to the sheet with a message, never a 500.
 */
final class SheetSaveConflictTest extends KernelTestCase
{
    private const string CLINIC_ID = '01960000-0000-7000-8000-0000000003c1';
    private const string CLIENT_ID = '01960000-0000-7000-8000-0000000003d1';

    public function testAConcurrentSaveIsToldToTryAgain(): void
    {
        $container = self::getContainer();

        $session = new Session(new MockArraySessionStorage());
        $session->set('current_clinic_id', self::CLINIC_ID);

        $request = Request::create('/clients/' . self::CLIENT_ID . '/tax-profile', 'POST', ['fisc' => 'pro', 'rs' => 'Élevage des Calanques', 'siren' => '523456788', 'tva' => '']);
        $request->setSession($session);

        $requestStack = $container->get(RequestStack::class);
        \assert($requestStack instanceof RequestStack);
        $requestStack->push($request);

        $csrf = $container->get(CsrfTokenManagerInterface::class);
        \assert($csrf instanceof CsrfTokenManagerInterface);
        $request->request->set('_token', $csrf->getToken('client_sheet')->getValue());

        $container->set(CommandBusInterface::class, new class implements CommandBusInterface {
            public function dispatch(object $command, object ...$stamps): mixed
            {
                throw new OptimisticLockException('The optimistic lock on an entity failed.', null);
            }
        });

        $controller = $container->get(UpdateClientTaxProfileController::class);
        \assert($controller instanceof UpdateClientTaxProfileController);

        $response = $controller(self::CLIENT_ID, $request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(303, $response->getStatusCode());
        self::assertStringEndsWith('/clients/' . self::CLIENT_ID, $response->getTargetUrl());
        self::assertSame(['error' => ['La fiche vient d’être mise à jour, réessayez.']], $session->getFlashBag()->all());
    }
}
