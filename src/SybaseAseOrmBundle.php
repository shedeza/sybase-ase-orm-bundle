<?php

namespace Shedeza\SybaseAseOrmBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Shedeza\SybaseAseOrmBundle\DependencyInjection\Compiler\RepositoryServicePass;

class SybaseAseOrmBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new RepositoryServicePass());
    }
}