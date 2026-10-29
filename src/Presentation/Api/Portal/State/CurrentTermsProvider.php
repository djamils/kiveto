<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Presentation\Api\Portal\Resource\CurrentTerms;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProviderInterface<CurrentTerms>
 */
final readonly class CurrentTermsProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire('%env(PORTAL_TERMS_VERSION)%')]
        private string $version,
        #[Autowire('%env(PORTAL_TERMS_URL)%')]
        private string $url,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CurrentTerms
    {
        return new CurrentTerms($this->version, $this->url);
    }
}
