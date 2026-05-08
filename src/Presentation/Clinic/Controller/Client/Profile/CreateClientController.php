<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Client\Profile;

use App\Context\Client\Application\Command\CreateClient\ContactMethodDto;
use App\Context\Client\Application\Command\CreateClient\CreateClient;
use App\Context\Client\Application\Port\ClientReadRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClinicId as ClientClinicId;
use App\Presentation\Clinic\Form\Client\ClientFormType;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\Shared\Infrastructure\Http\ReturnToResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Turbo\TurboBundle;

#[Route('/clients/create', name: 'clinic_clients_create', methods: ['POST'])]
final class CreateClientController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ReturnToResolver $returnToResolver,
        private readonly ClientReadRepositoryInterface $clientReadRepository,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $form = $this->createForm(ClientFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            \assert(\is_array($data));

            $firstName = \is_string($data['firstName'] ?? null) ? $data['firstName'] : '';
            $lastName  = \is_string($data['lastName'] ?? null) ? $data['lastName'] : '';
            $email     = trim(\is_string($data['email'] ?? null) ? $data['email'] : '');
            $phone     = trim(\is_string($data['phone'] ?? null) ? $data['phone'] : '');

            $emailIsDuplicate = '' !== $email
                && $this->clientReadRepository->emailExistsInClinic(
                    ClientClinicId::fromString($currentClinicId->toString()),
                    $email,
                );

            if ($emailIsDuplicate) {
                $form->get('email')->addError(
                    new FormError('Un client avec cet email existe déjà dans cette clinique.'),
                );
            }

            if (!$emailIsDuplicate) {
                $contactMethods = [];
                if ('' !== $email) {
                    $contactMethods[] = new ContactMethodDto(
                        type: 'email',
                        label: 'home',
                        value: $email,
                        isPrimary: true,
                    );
                }
                if ('' !== $phone) {
                    $contactMethods[] = new ContactMethodDto(
                        type: 'phone',
                        label: 'mobile',
                        value: $phone,
                        isPrimary: '' === $email,
                    );
                }

                try {
                    $this->commandBus->dispatch(new CreateClient(
                        clinicId: $currentClinicId->toString(),
                        firstName: $firstName,
                        lastName: $lastName,
                        contactMethods: $contactMethods,
                    ));
                } catch (\InvalidArgumentException $e) {
                    $form->addError(new FormError($e->getMessage()));

                    return $this->renderError($request, $form);
                }

                $rawReturnToData = $form->has('_returnTo') ? $form->get('_returnTo')->getData() : null;
                $rawReturnTo     = \is_string($rawReturnToData) ? $rawReturnToData : '';
                $targetUrl       = $this->returnToResolver->resolve(
                    '' !== $rawReturnTo ? $rawReturnTo : null,
                    'clinic_clients_list',
                );

                $this->addFlash('success', \sprintf(
                    'Client "%s %s" créé avec succès.',
                    $firstName,
                    $lastName,
                ));

                return $this->redirect($targetUrl, Response::HTTP_SEE_OTHER);
            }
        }

        return $this->renderError($request, $form);
    }

    private function renderError(Request $request, \Symfony\Component\Form\FormInterface $form): Response
    {
        if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            return $this->render(
                'clinic/clients/list/_create_client_error.stream.html.twig',
                ['form' => $form],
                new Response('', Response::HTTP_UNPROCESSABLE_ENTITY, [
                    'Content-Type' => TurboBundle::STREAM_MEDIA_TYPE,
                ]),
            );
        }

        return $this->render(
            'clinic/clients/list/_form_client_body.html.twig',
            ['form' => $form],
        );
    }
}
