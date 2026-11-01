<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Clinic;

use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Embeddable\LetterheadEmbeddable;
use App\Context\ClinicListing\Application\Query\GetListingSettings\GetListingSettings;
use App\Context\ClinicListing\Application\Query\GetListingSettings\ListingSettingsView;
use App\Context\ClinicListing\Application\Query\SearchClinicListings\ClinicListingPage;
use App\Context\ClinicListing\Application\Query\SearchClinicListings\SearchClinicListings;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * Paramètres › Annuaire (S7c): the defaults, the settings saved as they
 * change and the directory following at once, tags refused with a French
 * message, the missing-address warning, and the section kept for the
 * clinic's administrators.
 */
final class DirectoryListingSettingsTest extends WebTestCase
{
    use Factories;

    private const string CLINIC = '01960000-0000-7000-8000-0000000b5d01';
    private const string HOST   = 'http://clinic.kiveto.local';

    private KernelBrowser $browser;
    private string $token = '';

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();
    }

    public function testTheDefaultsKeepTheClinicOutOfTheDirectoryAndWarnOfAMissingAddress(): void
    {
        $this->clinic(withAddress: false);
        $this->signIn('claire.admin@example.com');

        $payload = $this->open();

        self::assertSame(['listed' => false, 'acceptsNewClients' => true, 'tags' => [], 'addressMissing' => true], $payload['annuaire']);
        self::assertTrue(\is_array($payload['can']) && true === $payload['can']['annuaire']);
        $urls = $payload['urls'];
        \assert(\is_array($urls) && \is_array($urls['save']) && \is_string($urls['save']['annuaire']));
        self::assertStringEndsWith('/settings/directory', $urls['save']['annuaire']);
    }

    public function testTheSettingsAreSavedAndTheClinicIsFoundAtOnce(): void
    {
        $this->clinic(withAddress: true);
        $this->signIn('claire.admin@example.com');
        $section = $this->open()['annuaire'];
        \assert(\is_array($section));
        self::assertFalse($section['addressMissing']);

        $this->post(['listed' => true, 'acceptsNewClients' => false, 'tags' => ['NAC', ' nac ', 'Urgences']]);
        self::assertResponseIsSuccessful();

        $settings = $this->queryBus()->ask(new GetListingSettings(self::CLINIC));
        \assert($settings instanceof ListingSettingsView);
        self::assertSame([true, false, ['NAC', 'Urgences']], [$settings->listed, $settings->acceptsNewClients, $settings->tags]);

        $page = $this->queryBus()->ask(new SearchClinicListings(q: 'annuaire'));
        \assert($page instanceof ClinicListingPage);
        self::assertCount(1, $page->items);
        self::assertSame(['Clinique de l\'Annuaire', ['NAC', 'Urgences']], [$page->items[0]->name, $page->items[0]->tags]);

        self::assertSame(['listed' => true, 'acceptsNewClients' => false, 'tags' => ['NAC', 'Urgences'], 'addressMissing' => false], $this->open()['annuaire']);
    }

    public function testInvalidTagsAreRefusedWithAFrenchMessage(): void
    {
        $this->clinic(withAddress: true);
        $this->signIn('claire.admin@example.com');
        $this->open();

        self::assertSame(['error' => '« X » : un tag compte de 2 à 30 caractères.', 'field' => 'tags'], $this->post(['listed' => true, 'tags' => ['NAC', 'X']]));
        self::assertResponseStatusCodeSame(422);

        self::assertSame(['error' => '8 tags au plus.', 'field' => 'tags'], $this->post(['listed' => true, 'tags' => array_map(static fn (int $i): string => 'Tag ' . $i, range(1, 9))]));
        self::assertSame(['error' => 'Les tags doivent être des textes.', 'field' => 'tags'], $this->post(['listed' => true, 'tags' => [12]]));

        // A single text is one tag
        $this->post(['listed' => true, 'acceptsNewClients' => true, 'tags' => 'Équidés']);
        self::assertResponseIsSuccessful();

        $settings = $this->queryBus()->ask(new GetListingSettings(self::CLINIC));
        \assert($settings instanceof ListingSettingsView);
        self::assertSame([true, ['Équidés']], [$settings->listed, $settings->tags]);
    }

    public function testAMemberWithoutTheStaffRightCannotOpenNorSaveTheSection(): void
    {
        $this->clinic(withAddress: true);
        $this->signIn('hugo.vet@example.com');

        $this->browser->request('GET', self::HOST . '/settings/directory');
        self::assertResponseStatusCodeSame(403);

        $crawler = $this->browser->request('GET', self::HOST . '/settings/preferences');
        $payload = json_decode((string) $crawler->filter('[data-payload]')->attr('data-payload'), true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($payload) && \is_string($payload['token']));
        self::assertNull($payload['annuaire']);
        $this->token = $payload['token'];

        $this->post(['listed' => true]);
        self::assertResponseStatusCodeSame(403);
    }

    private function clinic(bool $withAddress): void
    {
        ClinicEntityFactory::new()
            ->withId(self::CLINIC)
            ->withName('Clinique de l\'Annuaire')
            ->withLetterhead($withAddress ? new LetterheadEmbeddable(streetLine1: '3 rue Neuve', postalCode: '33000', city: 'Bordeaux', countryCode: 'FR') : new LetterheadEmbeddable())
            ->create(['timeZone' => 'Europe/Paris'])
        ;

        $admin = ClinicUserFactory::new()->withEmail('claire.admin@example.com')->withPlainPassword('mot-de-passe')->create();
        ClinicMembershipEntityFactory::new()->withClinicId(self::CLINIC)->withUserId($admin->getId()->toRfc4122())->asManager()->asEmployee()->asClinicAdministrator()->create();

        $vet = ClinicUserFactory::new()->withEmail('hugo.vet@example.com')->withPlainPassword('mot-de-passe')->create();
        ClinicMembershipEntityFactory::new()->withClinicId(self::CLINIC)->withUserId($vet->getId()->toRfc4122())->asVeterinary()->asEmployee()->create();
    }

    private function signIn(string $email): void
    {
        $this->browser->request('POST', self::HOST . '/login', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: json_encode(['email' => $email, 'password' => 'mot-de-passe'], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
    }

    /**
     * Opens the section and keeps the page's token.
     *
     * @return array<array-key, mixed>
     */
    private function open(): array
    {
        $crawler = $this->browser->request('GET', self::HOST . '/settings/directory');
        self::assertResponseIsSuccessful();

        $payload = json_decode((string) $crawler->filter('[data-payload]')->attr('data-payload'), true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($payload) && \is_string($payload['token']));
        self::assertSame('annuaire', $payload['section']);
        $this->token = $payload['token'];

        return $payload;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    private function post(array $values): array
    {
        $this->browser->request('POST', self::HOST . '/settings/directory', ['_token' => $this->token, 'values' => json_encode($values, \JSON_THROW_ON_ERROR)], server: ['HTTP_ACCEPT' => 'application/json']);

        $answer = json_decode((string) $this->browser->getResponse()->getContent(), true);

        return \is_array($answer) ? $answer : [];
    }

    private function queryBus(): QueryBusInterface
    {
        $bus = self::getContainer()->get(QueryBusInterface::class);
        \assert($bus instanceof QueryBusInterface);

        return $bus;
    }
}
