<?php

namespace MLukman\SaasBundle\Tests\App;

use Doctrine\ORM\Tools\SchemaTool;
use MLukman\SaasBundle\Service\SaasUtil;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class TestCaseBase extends KernelTestCase
{
    protected SaasUtil $saas;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        $doctrine = static::getContainer()->get('doctrine');
        $entityManager = $doctrine->getManager();
        $metaData = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->updateSchema($metaData);

        $this->saas = $this->service(SaasUtil::class);
    }

    protected function service(string $className)
    {
        return $this->getContainer()->get($className);
    }
}
