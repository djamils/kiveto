<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Http\ReturnToResolver;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

final class ReturnToResolverRouteExistenceTest extends KernelTestCase
{
    public function testEveryWhitelistedRouteExistsInTheRouter(): void
    {
        /** @var RouterInterface $router */
        $router = static::getContainer()->get('router');

        $collection = $router->getRouteCollection();

        foreach (array_keys(ReturnToResolver::ALLOWED_ROUTES) as $routeName) {
            self::assertNotNull(
                $collection->get($routeName),
                \sprintf(
                    'Route "%s" is in ReturnToResolver::ALLOWED_ROUTES but does not exist in the Router. '
                    . 'Either remove it from the whitelist or restore the route.',
                    $routeName,
                ),
            );
        }
    }
}
