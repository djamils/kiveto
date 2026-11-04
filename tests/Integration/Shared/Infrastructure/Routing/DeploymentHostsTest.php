<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Routing;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * The hosts are `<app>.{kiveto_domain}`: requests are matched on the hosts of
 * every Kiveto domain, URLs are generated for the domain of the deployment
 * (KIVETO_DOMAIN, kiveto.local in tests).
 */
final class DeploymentHostsTest extends WebTestCase
{
    private const string TERMS = '/api/portal/v1/legal/terms/current';

    public function testUrlsAreGeneratedForTheDomainOfTheDeployment(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get('router');
        \assert($router instanceof RouterInterface);

        self::assertSame('kiveto.local', $router->getContext()->getParameter('kiveto_domain'));
        self::assertSame('http://clinic.kiveto.local/dashboard', $router->generate('clinic_dashboard', [], UrlGeneratorInterface::ABSOLUTE_URL));
        self::assertSame('http://backoffice.kiveto.local/users', $router->generate('backoffice_users', [], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    /** @return iterable<string, array{string}> */
    public static function provideThePortalHostOfEveryDomainServesThePortalApiCases(): iterable
    {
        yield 'dev and tests' => ['kiveto.local'];
        yield 'staging' => ['staging.kiveto.app'];
        yield 'demo' => ['demo.kiveto.app'];
        yield 'production' => ['kiveto.app'];
    }

    #[DataProvider('provideThePortalHostOfEveryDomainServesThePortalApiCases')]
    public function testThePortalHostOfEveryDomainServesThePortalApi(string $domain): void
    {
        $client = self::createClient();

        $client->request('GET', 'http://portal.' . $domain . self::TERMS, server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();

        // The firewall and the access rules follow the host to every domain.
        $client->request('GET', 'http://portal.' . $domain . '/api/portal/v1/me', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testTheApiIsNotServedOnTheClinicHostOfAnotherDomain(): void
    {
        $client = self::createClient();

        $client->request('GET', 'http://clinic.staging.kiveto.app' . self::TERMS, server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(404);
    }

    public function testAHostOutsideTheKivetoDomainsIsRefused(): void
    {
        $client = self::createClient();

        $client->request('GET', 'http://portal.kiveto.invalid' . self::TERMS, server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(400);
    }
}
