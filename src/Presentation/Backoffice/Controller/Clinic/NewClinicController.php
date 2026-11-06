<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Clinic;

use App\Context\Clinic\Application\Exception\DuplicateClinicSlugException;
use App\Context\Clinic\Application\Query\Clinic\CheckClinicSlug\CheckClinicSlug;
use App\Context\Clinic\Application\Query\Clinic\CheckClinicSlug\ClinicSlugCheck;
use App\Context\Clinic\Domain\Account\ParallelSoftware;
use App\Presentation\Backoffice\Clinic\ClinicCreator;
use App\Presentation\Backoffice\Clinic\ClinicDraft;
use App\Presentation\Backoffice\Controller\Account\ShowAccountController;
use App\Presentation\Backoffice\Feature\ConsoleFeatures;
use App\Presentation\Backoffice\ViewModel\ConsoleDirectory;
use App\Presentation\Backoffice\ViewModel\ConsoleFormat;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * B3 / B4 / B5 — « Nouvelle clinique » in three steps (`?step=1..3`), each a
 * form posted back to the same address; the draft stays in the session until
 * the clinic is created or « Annuler ».
 */
final class NewClinicController extends AbstractController
{
    public const string CREATED_KEY = 'console.created_clinic';

    private const array ROLES = ['VETERINARY', 'VETERINARY_ASSISTANT', 'RECEPTIONIST', 'MANAGER'];

    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly ClinicCreator $creator,
        private readonly ConsoleFeatures $features,
        #[Autowire('%app.console.price_per_vet_cents%')]
        private readonly int $pricePerVetCents,
    ) {
    }

    #[Route(path: '/clinics/new', name: 'backoffice_clinic_new', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $session = $request->getSession();

        if ($request->query->getBoolean('cancel')) {
            ClinicDraft::clear($session);

            return $this->redirectToRoute('backoffice_clinics');
        }

        $draft  = ClinicDraft::from($session);
        $step   = max(1, min(3, $request->query->getInt('step', 1)));
        $step   = $step > 1 && !$draft->hasEstablishment() ? 1 : ($step > 2 && !$draft->hasTeam() ? 2 : $step);
        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(UpdateClinicController::CSRF_ID, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $errors = match ($step) {
                1       => $this->establishment($request, $draft),
                2       => $this->team($request, $draft),
                default => $this->subscription($request, $draft),
            };
            $draft->store($session);

            if ([] === $errors && 3 === $step) {
                try {
                    $created = $this->creator->create($draft);
                } catch (DuplicateClinicSlugException) {
                    return $this->redirectToRoute('backoffice_clinic_new', ['step' => 1, 'taken' => 1]);
                }

                ClinicDraft::clear($session);
                $session->set(self::CREATED_KEY, $created);

                return $this->redirectToRoute('backoffice_clinic_created', ['id' => $created['clinicId']]);
            }

            if ([] === $errors) {
                return $this->redirectToRoute('backoffice_clinic_new', ['step' => $step + 1]);
            }
        }

        if ($request->query->getBoolean('taken')) {
            $errors['slug'] = 'Ce tenant vient d’être pris : choisis-en un autre.';
        }

        $vets     = $draft->veterinarians();
        $features = 3 === $step ? $this->features->starting() : [];

        return $this->render('backoffice/console/clinics/new.html.twig', [
            'step'        => $step,
            'draft'       => $draft,
            'errors'      => $errors,
            'roles'       => array_combine(self::ROLES, array_map(ConsoleDirectory::roleLabel(...), self::ROLES)),
            'softwares'   => array_combine(array_map(static fn (ParallelSoftware $s): string => $s->value, ParallelSoftware::cases()), array_map(static fn (ParallelSoftware $s): string => $s->label(), ParallelSoftware::cases())),
            'zones'       => ShowAccountController::TIME_ZONES,
            'features'    => $features,
            'price'       => ConsoleFormat::euros($this->pricePerVetCents),
            'price_cents' => $this->pricePerVetCents,
            'summary'     => [
                'vets'    => $vets,
                'monthly' => ConsoleFormat::euros($vets * $this->pricePerVetCents),
            ],
        ], new Response(status: [] !== $errors ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * @return array<string, string>
     */
    private function establishment(Request $request, ClinicDraft $draft): array
    {
        $form   = $request->request;
        $errors = [];

        $draft->company = array_map(static fn (string $v): string => trim($v), [
            'siren'      => $form->getString('company_siren'),
            'siret'      => (string) preg_replace('/\D/', '', $form->getString('company_siret')),
            'legalName'  => $form->getString('company_name'),
            'naf'        => $form->getString('company_naf'),
            'street'     => $form->getString('company_street'),
            'postalCode' => $form->getString('company_postal_code'),
            'city'       => $form->getString('company_city'),
            'vat'        => $form->getString('company_vat'),
            'vatCheck'   => $form->getString('company_vat_check'),
        ]);
        $draft->place = array_map(static fn (string $v): string => trim($v), [
            'name'             => $form->getString('name'),
            'slug'             => mb_strtolower($form->getString('slug')),
            'sameAddress'      => $form->getBoolean('same_address') ? '1' : '0',
            'street'           => $form->getString('street'),
            'postalCode'       => $form->getString('postal_code'),
            'city'             => $form->getString('city'),
            'phone'            => $form->getString('phone'),
            'email'            => mb_strtolower($form->getString('email')),
            'timeZone'         => \in_array($form->getString('time_zone'), ShowAccountController::TIME_ZONES, true) ? $form->getString('time_zone') : 'Europe/Paris',
            'parallelSoftware' => null !== ParallelSoftware::tryFrom($form->getString('parallel_software')) ? $form->getString('parallel_software') : '',
        ]);

        if (14 !== \strlen($draft->company['siret'])) {
            $errors['company'] = 'Choisis l’entreprise dans le registre (SIRET à 14 chiffres).';
        }

        if ('' === $draft->place['name']) {
            $errors['name'] = 'Donne le nom affiché de la clinique.';
        }

        $check = $this->queryBus->ask(new CheckClinicSlug($draft->place['slug']));

        if (ClinicSlugCheck::AVAILABLE !== $check) {
            $errors['slug'] = ClinicSlugCheck::TAKEN === $check ? 'Ce tenant est déjà pris.' : 'Le tenant n’accepte que des minuscules, des chiffres et des tirets.';
        }

        if (null !== ($phone = ConsoleFormat::phone($draft->place['phone'])) && 1 !== preg_match('/^\+[1-9]\d{7,14}$/', $phone)) {
            $errors['phone'] = 'Ce numéro de téléphone n’est pas valide.';
        }

        if ('' !== $draft->place['email'] && false === filter_var($draft->place['email'], \FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Cette adresse e-mail n’est pas valide.';
        }

        if ('0' === $draft->place['sameAddress'] && ('' === $draft->place['street'] || '' === $draft->place['city'])) {
            $errors['address'] = 'Donne l’adresse de l’établissement, ou coche « identique au siège ».';
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private function team(Request $request, ClinicDraft $draft): array
    {
        $rows = $request->request->all('team');
        $team = [];

        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }

            $person = [
                'firstName' => trim(\is_string($row['firstName'] ?? null) ? $row['firstName'] : ''),
                'lastName'  => trim(\is_string($row['lastName'] ?? null) ? $row['lastName'] : ''),
                'email'     => mb_strtolower(trim(\is_string($row['email'] ?? null) ? $row['email'] : '')),
                'role'      => \is_string($row['role'] ?? null) && \in_array($row['role'], self::ROLES, true) ? $row['role'] : 'VETERINARY',
            ];

            if ('' !== $person['firstName'] || '' !== $person['lastName'] || '' !== $person['email']) {
                $team[] = $person;
            }
        }

        $draft->team = $team;

        if ([] === $team) {
            return ['team' => 'Ajoute au moins une personne à inviter.'];
        }

        foreach ($team as $person) {
            if ('' === $person['firstName'] || '' === $person['lastName'] || false === filter_var($person['email'], \FILTER_VALIDATE_EMAIL)) {
                return ['team' => 'Chaque personne a un prénom, un nom et une adresse e-mail valide.'];
            }
        }

        if (\count(array_unique(array_column($team, 'email'))) !== \count($team)) {
            return ['team' => 'Une même adresse e-mail apparaît deux fois.'];
        }

        if (0 === $draft->veterinarians()) {
            return ['team' => 'Invite au moins un vétérinaire : il administrera la clinique.'];
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private function subscription(Request $request, ClinicDraft $draft): array
    {
        $plan            = $request->request->getString('plan');
        $draft->plan     = \in_array($plan, ['pilot', 'paid'], true) ? $plan : 'pilot';
        $offered         = array_column(array_filter($this->features->starting(), static fn (array $f): bool => !$f['upcoming']), 'code');
        $features        = $request->request->all('features');
        $draft->features = array_values(array_filter($features, static fn (mixed $code): bool => \in_array($code, $offered, true)));

        return [];
    }
}
