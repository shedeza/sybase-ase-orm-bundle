<?php

namespace Shedeza\SybaseAseOrmBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Definition;

class RepositoryServicePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('sybase_ase_orm.entity_manager')) {
            return;
        }

        // Register custom repository classes for direct injection
        $this->registerCustomRepositories($container);
        
        // Register entity repositories based on entities
        $this->registerEntityRepositories($container);
    }
    
    private function registerCustomRepositories(ContainerBuilder $container): void
    {
        // Find all repository classes in the bundle
        $repositoryClasses = [
            'Shedeza\\SybaseAseOrmBundle\\Repository\\UserRepository',
            'Shedeza\\SybaseAseOrmBundle\\Repository\\PostRepository'
        ];
        
        foreach ($repositoryClasses as $repositoryClass) {
            if (class_exists($repositoryClass)) {
                $definition = new Definition($repositoryClass);
                $definition->setFactory([new Reference('sybase_ase_orm.entity_manager'), 'getRepository']);
                $definition->setPublic(true);
                
                // Extract entity class from repository
                $entityClass = $this->getEntityClassFromRepository($repositoryClass);
                if ($entityClass) {
                    $definition->setArguments([$entityClass]);
                    $container->setDefinition($repositoryClass, $definition);
                }
            }
        }
    }
    
    private function registerEntityRepositories(ContainerBuilder $container): void
    {
        // Register generic repositories for entities without custom repositories
        $entityClasses = [
            'Shedeza\\SybaseAseOrmBundle\\Entity\\User',
            'Shedeza\\SybaseAseOrmBundle\\Entity\\Post'
        ];
        
        foreach ($entityClasses as $entityClass) {
            $repositoryServiceId = 'repository.' . strtolower(str_replace('\\', '_', $entityClass));
            
            $definition = new Definition('Shedeza\\SybaseAseOrmBundle\\ORM\\Repository\\EntityRepository');
            $definition->setFactory([new Reference('sybase_ase_orm.entity_manager'), 'getRepository']);
            $definition->setArguments([$entityClass]);
            $definition->setPublic(true);
            
            $container->setDefinition($repositoryServiceId, $definition);
        }
    }
    
    private function getEntityClassFromRepository(string $repositoryClass): ?string
    {
        // Map repository classes to their entity classes
        $mapping = [
            'Shedeza\\SybaseAseOrmBundle\\Repository\\UserRepository' => 'Shedeza\\SybaseAseOrmBundle\\Entity\\User',
            'Shedeza\\SybaseAseOrmBundle\\Repository\\PostRepository' => 'Shedeza\\SybaseAseOrmBundle\\Entity\\Post'
        ];
        
        return $mapping[$repositoryClass] ?? null;
    }
}