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

        if (!$container->hasParameter('sybase_ase_orm.entity_mappings')) {
            return;
        }
        
        $mappings = $container->getParameter('sybase_ase_orm.entity_mappings');
        $this->registerEntityRepositories($container, $mappings);
    }
    
    private function registerEntityRepositories(ContainerBuilder $container, array $mappings): void
    {
        foreach ($mappings as $namespace => $mapping) {
            if (!isset($mapping['dir']) || !isset($mapping['prefix'])) {
                continue;
            }
            
            $entityDir = $mapping['dir'];
            $entityPrefix = rtrim($mapping['prefix'], '\\');
            $repositoryPrefix = str_replace('\\Entity', '\\Repository', $entityPrefix);
            
            $this->scanAndRegisterRepositories($container, $entityDir, $entityPrefix, $repositoryPrefix);
        }
    }
    
    private function scanAndRegisterRepositories(ContainerBuilder $container, string $entityDir, string $entityPrefix, string $repositoryPrefix): void
    {
        $pattern = rtrim($entityDir, '/') . '/*.php';
        $files = glob($pattern);
        
        if ($files === false) {
            return;
        }
        
        foreach ($files as $file) {
            $filename = basename($file, '.php');
            $entityClass = $entityPrefix . '\\' . $filename;
            $repositoryClass = $repositoryPrefix . '\\' . $filename . 'Repository';
            
            $repositoryServiceClass = class_exists($repositoryClass) 
                ? $repositoryClass 
                : 'Shedeza\\SybaseAseOrmBundle\\ORM\\Repository\\EntityRepository';
            
            $definition = new Definition($repositoryServiceClass);
            $definition->setFactory([new Reference('sybase_ase_orm.entity_manager'), 'getRepository']);
            $definition->setArguments([$entityClass]);
            $definition->setPublic(true);
            
            $container->setDefinition($repositoryClass, $definition);
            
            $serviceId = 'repository.' . strtolower(str_replace('\\', '_', $entityClass));
            $container->setDefinition($serviceId, $definition);
        }
    }
}