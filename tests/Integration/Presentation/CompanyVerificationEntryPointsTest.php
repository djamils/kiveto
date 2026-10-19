<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation;

use App\Context\Client\Application\Command\VerifyClientTaxProfile\VerifyClientTaxProfile;
use App\Context\Client\Domain\ValueObject\ClientTaxStatus;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Presentation\Clinic\Controller\Client\Profile\CompanyRegistrySearchController;
use App\Presentation\Clinic\Controller\Client\Sheet\VerifyClientTaxProfileController;
use App\Tests\Support\RecordingExternalApiCommandBus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Zenstruck\Foundry\Test\Factories;

/**
 * The registry search and « Revérifier » endpoints, on the fixture adapters.
 */
final class CompanyVerificationEntryPointsTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID       = '01960000-0000-7000-8000-0000000004c1';
    private const string OTHER_CLINIC_ID = '01960000-0000-7000-8000-0000000004c2';
    private const string CLIENT_ID       = '01960000-0000-7000-8000-0000000004d1';
    private const string ARCHIVED_ID     = '01960000-0000-7000-8000-0000000004d2';
    private const string ELSEWHERE_ID    = '01960000-0000-7000-8000-0000000004d3';

    private Session $session;

    public function testASearchListsTheRegistryHits(): void
    {
        $answer = $this->search('calanques');

        self::assertTrue($answer['available']);
        self::assertNull($answer['reason']);
        self::assertIsArray($answer['items']);
        self::assertCount(1, $answer['items']);
        $hit = $answer['items'][0];
        self::assertIsArray($hit);
        self::assertSame('523456788', $hit['siren']);
        self::assertSame('Élevage des Calanques', $hit['n']);
        self::assertSame('EARL', $hit['forme']);
        self::assertSame('FR31523456788', $hit['tva']);
        self::assertSame([['siret' => '52345678800018', 'l' => 'Siège', 'a' => '12 Chemin des Goudes, 13008 Marseille']], $hit['ets']);
        self::assertArrayNotHasKey('ann', $hit);
    }

    public function testAShortQueryFindsNothing(): void
    {
        self::assertSame(['available' => true, 'reason' => null, 'items' => []], $this->search('ca'));
    }

    public function testAnOutageAndASpentBudgetAreTold(): void
    {
        self::assertSame(['available' => false, 'reason' => 'down', 'items' => []], $this->search('indisponible'));
        self::assertSame(['available' => false, 'reason' => 'throttled', 'items' => []], $this->search('limite'));
    }

    public function testASearchWithoutSessionGoesToTheLoginPage(): void
    {
        $kernel = self::bootKernel();

        $response = $kernel->handle(Request::create('http://clinic.kiveto.local/clients/company-registry/search?q=calanques'), HttpKernelInterface::MAIN_REQUEST);

        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('/login', (string) $response->headers->get('Location'));
    }

    public function testReverifyingQueuesOnceThenSaysItIsPending(): void
    {
        $this->seed();

        $this->verify(self::CLIENT_ID);
        self::assertSame(['success' => ['Vérification lancée : le résultat s’affichera sur la fiche d’ici quelques minutes.']], $this->session->getFlashBag()->all());

        $this->verify(self::CLIENT_ID);
        self::assertSame(['info' => ['Vérification déjà en cours.']], $this->session->getFlashBag()->all());

        $queued = $this->recorder()->recorded(VerifyClientTaxProfile::class);
        self::assertCount(1, $queued);
        self::assertSame(self::CLIENT_ID, $queued[0]->clientId);
    }

    public function testAnArchivedClientIsNotReverified(): void
    {
        $this->seed();

        $response = $this->verify(self::ARCHIVED_ID);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame(['error' => ['Client archivé : la vérification n’est pas relancée.']], $this->session->getFlashBag()->all());
        self::assertSame([], $this->recorder()->recorded(VerifyClientTaxProfile::class));
    }

    public function testAnotherClinicsClientOrAnUnknownIdIsNotFound(): void
    {
        $this->seed();

        foreach ([self::ELSEWHERE_ID, '01960000-0000-7000-8000-0000000004ff'] as $id) {
            try {
                $this->verify($id);
                self::fail('A 404 was expected.');
            } catch (NotFoundHttpException) {
            }
        }

        self::assertSame([], $this->recorder()->recorded(VerifyClientTaxProfile::class));
    }

    public function testAnInvalidCsrfTokenIsRefused(): void
    {
        $this->seed();

        try {
            $this->verify(self::CLIENT_ID, 'forged');
            self::fail('The token was expected to be refused.');
        } catch (AccessDeniedException) {
        }

        self::assertSame([], $this->recorder()->recorded(VerifyClientTaxProfile::class));
    }

    /**
     * @return array<mixed>
     */
    private function search(string $query): array
    {
        $request = $this->request('GET', '/clients/company-registry/search', ['q' => $query]);
        $request->query->set('q', $query);

        $controller = self::getContainer()->get(CompanyRegistrySearchController::class);
        \assert($controller instanceof CompanyRegistrySearchController);

        $answer = json_decode((string) $controller($request)->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($answer));

        return $answer;
    }

    private function verify(string $clientId, ?string $token = null): Response
    {
        $request = $this->request('POST', '/clients/' . $clientId . '/tax-profile/verify');

        $csrf = self::getContainer()->get(CsrfTokenManagerInterface::class);
        \assert($csrf instanceof CsrfTokenManagerInterface);
        $request->request->set('_token', $token ?? $csrf->getToken('client_sheet')->getValue());

        $controller = self::getContainer()->get(VerifyClientTaxProfileController::class);
        \assert($controller instanceof VerifyClientTaxProfileController);

        return $controller($clientId, $request);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function request(string $method, string $path, array $parameters = []): Request
    {
        if (!isset($this->session)) {
            $this->session = new Session(new MockArraySessionStorage());
            $this->session->set('current_clinic_id', self::CLINIC_ID);
        }

        $request = Request::create('http://clinic.kiveto.local' . $path, $method, $parameters);
        $request->setSession($this->session);

        $stack = self::getContainer()->get(RequestStack::class);
        \assert($stack instanceof RequestStack);
        $stack->push($request);

        return $request;
    }

    private function recorder(): RecordingExternalApiCommandBus
    {
        $recorder = self::getContainer()->get(RecordingExternalApiCommandBus::class);
        \assert($recorder instanceof RecordingExternalApiCommandBus);

        return $recorder;
    }

    private function seed(): void
    {
        $pro = static fn (string $id, string $clinic): ClientEntityFactory => ClientEntityFactory::new()->withId($id)->withClinicId($clinic)->withName('Hugo', 'Marchal')
            ->withTaxProfile(ClientTaxStatus::FRENCH_BUSINESS, 'Centre équestre du Lac', '812345676')->since('2026-01-01')
        ;

        $pro(self::CLIENT_ID, self::CLINIC_ID)->active()->create();
        $pro(self::ARCHIVED_ID, self::CLINIC_ID)->archived()->create();
        $pro(self::ELSEWHERE_ID, self::OTHER_CLINIC_ID)->active()->create();
    }
}
