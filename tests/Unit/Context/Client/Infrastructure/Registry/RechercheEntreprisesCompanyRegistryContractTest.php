<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Infrastructure\Registry;

use App\Context\Client\Application\Port\CompanyRegistryInterface;
use App\Context\Client\Infrastructure\Registry\RechercheEntreprisesCompanyRegistry;
use App\Context\Client\Infrastructure\Registry\RechercheEntreprisesMapper;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Infrastructure\ExternalApi\ExternalApiUserAgent;
use App\Shared\Infrastructure\ExternalApi\RetryAfterAwareClient;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(RechercheEntreprisesCompanyRegistry::class)]
#[CoversClass(RechercheEntreprisesMapper::class)]
final class RechercheEntreprisesCompanyRegistryContractTest extends CompanyRegistryContractTestCase
{
    private const array ANSWERS = [
        'calanques'      => 'search-calanques',
        '52345678800018' => 'search-siret',
        '812345676'      => 'siren-active',
        '441569738'      => 'siren-ceased',
        '900000019'      => 'siren-non-diffusible',
    ];

    /** @var list<string> */
    private array $urls = [];

    public function testTheSearchAsksTheHeadOfficeAndTheComplements(): void
    {
        $this->registry()->search('calanques', 7);

        self::assertCount(1, $this->urls);
        $url = parse_url($this->urls[0]);
        self::assertIsArray($url);
        self::assertSame('/search', $url['path'] ?? null);
        parse_str($url['query'] ?? '', $query);
        self::assertSame(['q' => 'calanques', 'per_page' => '7', 'page' => '1', 'minimal' => 'true', 'include' => 'siege,complements'], $query);
    }

    public function testAHitCarriesTheCompanyAndItsHeadOffice(): void
    {
        $hits = $this->registry()->search('calanques', 5);

        self::assertCount(2, $hits);
        self::assertSame('523456788', $hits[0]->siren);
        self::assertSame('ELEVAGE DES CALANQUES', $hits[0]->name);
        self::assertSame('EARL', $hits[0]->legalFormLabel);
        self::assertSame('01.45Z', $hits[0]->nafCode);
        self::assertFalse($hits[0]->isAssociation);
        self::assertSame('12 CHEMIN DES GOUDES', $hits[0]->addressLine);
        self::assertSame('13008', $hits[0]->postalCode);
        self::assertSame('MARSEILLE', $hits[0]->city);
        self::assertFalse($hits[1]->isActive);
        self::assertSame('2019-06-30', $hits[1]->ceasedOn);
    }

    public function testThePageSizeStaysWithinTheApiBounds(): void
    {
        $this->registry()->search('calanques', 100);
        $this->registry()->search('calanques', 0);

        self::assertStringContainsString('per_page=25', $this->urls[0]);
        self::assertStringContainsString('per_page=1', $this->urls[1]);
    }

    public function testASiretQueryKeepsOnlyTheMatchingHeadOffice(): void
    {
        self::assertSame([], $this->registry()->search('523 456 788 00026', 5));
    }

    public function testACeasedCompanyRecord(): void
    {
        $record = $this->registry()->findBySiren('441569738');

        self::assertNotNull($record);
        self::assertFalse($record->isActive);
        self::assertFalse($record->isConfidential);
        self::assertSame('2025-12-31', $record->ceasedOn);
        self::assertSame('GAEC DU MONT', $record->legalName);
    }

    public function testANonDiffusibleCompanyIsConfidential(): void
    {
        $record = $this->registry()->findBySiren('900000019');

        self::assertNotNull($record);
        self::assertTrue($record->isConfidential);
        self::assertNull($record->legalName);
        self::assertTrue($record->isActive);
        self::assertSame('Entreprise non diffusible', $this->registry()->search('900000019', 5)[0]->name);
    }

    public function testARefusedQueryFindsNothing(): void
    {
        $registry = $this->registryAnswering(new MockResponse('{"erreur":"Veuillez indiquer au moins 3 caractères"}', ['http_code' => 400]));

        self::assertSame([], $registry->search('ca', 5));
    }

    public function testAnotherClientErrorIsAnOutage(): void
    {
        $this->expectException(ExternalServiceUnavailable::class);
        $this->expectExceptionMessage('recherche-entreprises is unavailable: HTTP 404.');

        $this->registryAnswering(new MockResponse('', ['http_code' => 404]))->findBySiren('523456788');
    }

    public function testAnUnreadableAnswerIsAnOutage(): void
    {
        $this->expectException(ExternalServiceUnavailable::class);
        $this->expectExceptionMessage('recherche-entreprises is unavailable: unreadable answer.');

        $this->registryAnswering(new MockResponse('<html>maintenance</html>'))->search('calanques', 5);
    }

    public function testItCannotBeBuiltWithoutAContact(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('EXTERNAL_API_USER_AGENT must contain a contact (e-mail or URL) to call recherche-entreprises');

        new RechercheEntreprisesCompanyRegistry($this->client(new MockHttpClient()), new RechercheEntreprisesMapper(), new ExternalApiUserAgent('Kiveto/0.5'));
    }

    protected function registry(): CompanyRegistryInterface
    {
        return $this->build(new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->urls[] = $url;
            parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
            $q = \is_string($query['q'] ?? null) ? $query['q'] : '';

            return new MockResponse(self::fixture(self::ANSWERS[$q] ?? 'siren-not-found'));
        }, 'https://recherche-entreprises.api.gouv.fr'));
    }

    protected function registryInOutage(): CompanyRegistryInterface
    {
        return $this->registryAnswering(new MockResponse('', ['http_code' => 503]));
    }

    protected function knownSiret(): array
    {
        return ['52345678800018', '523456788'];
    }

    protected function queryWithActiveAndCeased(): string
    {
        return 'calanques';
    }

    protected function knownSiren(): array
    {
        return ['812345676', 'CENTRE EQUESTRE DU LAC'];
    }

    protected function unknownSiren(): string
    {
        return '123456782';
    }

    private function registryAnswering(MockResponse $response): RechercheEntreprisesCompanyRegistry
    {
        return $this->build(new MockHttpClient($response, 'https://recherche-entreprises.api.gouv.fr'));
    }

    private function build(MockHttpClient $http): RechercheEntreprisesCompanyRegistry
    {
        return new RechercheEntreprisesCompanyRegistry(
            $this->client($http),
            new RechercheEntreprisesMapper(),
            new ExternalApiUserAgent('Kiveto-test (+tests@kiveto.invalid)'),
        );
    }

    private function client(MockHttpClient $http): RetryAfterAwareClient
    {
        return new RetryAfterAwareClient($http, 'recherche-entreprises', new NullLogger(), $this->createStub(ClockInterface::class));
    }

    private static function fixture(string $name): string
    {
        $content = file_get_contents(\dirname(__DIR__, 6) . '/tests/Fixtures/recherche-entreprises/' . $name . '.json');
        \assert(\is_string($content));

        return $content;
    }
}
