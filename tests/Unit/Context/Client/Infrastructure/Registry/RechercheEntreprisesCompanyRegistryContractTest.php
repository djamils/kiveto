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
    /** Recorded answers of the API (see tests/Fixtures/recherche-entreprises), by query. */
    private const array ANSWERS = [
        'la poste'       => 'search-by-name',
        '35600000000048' => 'search-siret',
        // A secondary establishment of La Poste: the API answers with the company.
        '35600000000049' => 'search-siret',
        '356000000'      => 'siren-active',
        '879404929'      => 'siren-ceased',
        '900000019'      => 'siren-non-diffusible',
    ];

    private const string UNKNOWN_SIREN = '000000000';

    /** @var list<string> */
    private array $urls = [];

    public function testTheSearchAsksTheHeadOfficeAndTheComplements(): void
    {
        $this->registry()->search('la poste', 7);

        self::assertCount(1, $this->urls);
        $url = parse_url($this->urls[0]);
        self::assertIsArray($url);
        self::assertSame('/search', $url['path'] ?? null);
        parse_str($url['query'] ?? '', $query);
        self::assertSame(['q' => 'la poste', 'per_page' => '7', 'page' => '1', 'minimal' => 'true', 'include' => 'siege,complements'], $query);
    }

    public function testAHitCarriesTheCompanyAndItsHeadOffice(): void
    {
        $hits = $this->registry()->search('la poste', 10);

        self::assertCount(10, $hits);
        self::assertSame('356000000', $hits[0]->siren);
        self::assertSame('LA POSTE', $hits[0]->name);
        self::assertSame('SA nationale à conseil d\'administration', $hits[0]->legalFormLabel);
        self::assertSame('53.10Z', $hits[0]->nafCode);
        self::assertFalse($hits[0]->isAssociation);
        self::assertSame('35600000000048', $hits[0]->headOfficeSiret);
        self::assertSame('DIRECTION GENERALE DE LA POSTE 9 RUE DU COLONEL PIERRE AVIA', $hits[0]->addressLine);
        self::assertSame('75015', $hits[0]->postalCode);
        self::assertSame('PARIS', $hits[0]->city);
        self::assertSame('356000117', $hits[9]->siren);
        self::assertFalse($hits[9]->isActive);
        self::assertSame('2011-12-31', $hits[9]->ceasedOn);
    }

    public function testThePageSizeStaysWithinTheApiBounds(): void
    {
        $this->registry()->search('la poste', 100);
        $this->registry()->search('la poste', 0);

        self::assertStringContainsString('per_page=25', $this->urls[0]);
        self::assertStringContainsString('per_page=1', $this->urls[1]);
    }

    public function testASiretQueryKeepsOnlyTheMatchingHeadOffice(): void
    {
        self::assertSame([], $this->registry()->search('356 000 000 00049', 5));
    }

    public function testACeasedCompanyRecord(): void
    {
        $record = $this->registry()->findBySiren('879404929');

        self::assertNotNull($record);
        self::assertFalse($record->isActive);
        self::assertFalse($record->isConfidential);
        self::assertSame('2023-12-19', $record->ceasedOn);
        self::assertSame('M&L TRANSPORTS', $record->legalName);
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

        $this->registryAnswering(new MockResponse('<html>maintenance</html>'))->search('la poste', 5);
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
        return ['35600000000048', '356000000'];
    }

    protected function queryWithActiveAndCeased(): string
    {
        return 'la poste';
    }

    protected function knownSiren(): array
    {
        return ['356000000', 'LA POSTE'];
    }

    protected function unknownSiren(): string
    {
        // Recorded as siren-not-found (no result).
        return self::UNKNOWN_SIREN;
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
