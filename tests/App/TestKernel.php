<?php

namespace MLukman\SaasBundle\Tests\App;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use MLukman\SaasBundle\SaasBundle;
use MLukman\SaasBundle\Service\SaasUtil;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\Kernel;

class TestKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new SaasBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(__DIR__ . '/../config/doctrine.yaml');
        $loader->load(__DIR__ . '/../config/saas.yaml');
    }
}
