<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    /**
     * The routes are bound to `clinic.{kiveto_domain}`, `portal.{kiveto_domain}`
     * and `backoffice.{kiveto_domain}` (config/routes/). The router's context
     * carries the domain of this deployment (KIVETO_DOMAIN), read at runtime:
     * URLs are generated for it in requests and in the CLI alike, and one
     * compiled cache serves every deployment.
     */
    public function process(ContainerBuilder $container): void
    {
        $container->getDefinition('router.request_context')
            ->addMethodCall('setParameter', ['kiveto_domain', '%env(kiveto_domain:KIVETO_DOMAIN)%'])
        ;
    }
}
