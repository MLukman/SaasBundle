<?php

namespace MLukman\SaasBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use MLukman\SaasBundle\Config\SaasConfig;
use MLukman\SaasBundle\Payment\ProviderInterface;
use MLukman\SaasBundle\Service\SaasUtil;
use MLukman\SymfonyConfigOOP\ConfigUtil;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class SaasBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        ConfigUtil::populateDefinitionConfigurator($definition, SaasConfig::class);
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(
            DoctrineOrmMappingsPass::createXmlMappingDriver([
                    realpath(__DIR__ . '/../config/doctrine') => 'MLukman\\SaasBundle\\Entity'
                ])
        );
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.yaml');
        $builder->registerForAutoconfiguration(ProviderInterface::class)->addTag('saas.payment.provider');
        $builder->getDefinition(SaasUtil::class)->addMethodCall('setConfiguration', [$config]);
    }
}
