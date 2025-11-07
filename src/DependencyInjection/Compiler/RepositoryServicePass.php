<?php

namespace Shedeza\SybaseAseOrmBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class RepositoryServicePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('sybase_ase_orm.entity_manager')) {
            return;
        }

        $repositoryServices = $container->findTaggedServiceIds('sybase_ase_orm.repository');
        
        foreach ($repositoryServices as $serviceId => $tags) {
            $definition = $container->getDefinition($serviceId);
            $definition->setFactory([new Reference('sybase_ase_orm.entity_manager'), 'getRepository']);
            $definition->setPublic(true);
            
            foreach ($tags as $tag) {
                if (isset($tag['entity'])) {
                    $definition->setArguments([$tag['entity']]);
                }
            }
        }
    }
}