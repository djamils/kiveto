<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Auth\Account;

use App\Presentation\Clinic\ViewModel\Auth\PublicAccountPage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Attribute\Route;

/** « Mot de passe oublié » (public): the form, or the answer after a request. */
#[Route(path: '/mot-de-passe-oublie', name: 'clinic_forgot_password', methods: ['GET'])]
final readonly class ShowForgotPasswordController
{
    public const string SENT_FLASH = 'forgot_password_sent';

    public function __construct(private PublicAccountPage $page)
    {
    }

    public function __invoke(Request $request): Response
    {
        $session = $request->getSession();
        $sent    = $session instanceof FlashBagAwareSessionInterface && [] !== $session->getFlashBag()->get(self::SENT_FLASH);

        return $this->page->render('security/forgot_password.html.twig', ['sent' => $sent]);
    }
}
