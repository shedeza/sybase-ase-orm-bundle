<?php

namespace Shedeza\SybaseAseOrmBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Shedeza\SybaseAseOrmBundle\DBAL\Connection;
use Shedeza\SybaseAseOrmBundle\DBAL\DatabaseUrlParser;
use Shedeza\SybaseAseOrmBundle\ORM\EntityManager;

class SybaseAseOrmExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        
        // Provide default configuration if none exists
        if (empty($configs) || (count($configs) === 1 && empty($configs[0]))) {
            $configs = [[
                'connections' => [
                    'default' => '%env(DATABASE_SYBASE_URL)%'
                ],
                'entity_managers' => [
                    'default' => [
                        'connection' => 'default',
                        'mappings' => [
                            'App' => [
                                'type' => 'attribute',
                                'dir' => '%kernel.project_dir%/src/Entity',
                                'prefix' => 'App\Entity'
                            ]
                        ]
                    ]
                ],
                'default_connection' => 'default',
                'default_entity_manager' => 'default'
            ]];
        }
        
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        // Register connections
        foreach ($config['connections'] as $name => $connectionConfig) {
            // Parse database URL if provided
            if (isset($connectionConfig['url'])) {
                $connectionConfig = DatabaseUrlParser::parseUrl($connectionConfig['url']);
            }
            
            $container->register("sybase_ase_orm.connection.$name", Connection::class)
                ->setArguments([$connectionConfig])
                ->setPublic(false);
        }

        // Register entity managers
        foreach ($config['entity_managers'] as $name => $emConfig) {
            $connectionName = $emConfig['connection'];
            
            if (!isset($config['connections'][$connectionName])) {
                throw new \InvalidArgumentException("Connection '{$connectionName}' not found for entity manager '{$name}'");
            }
            
            $container->register("sybase_ase_orm.entity_manager.$name", EntityManager::class)
                ->setArguments([
                    new Reference("sybase_ase_orm.connection.$connectionName"),
                    $emConfig['mappings']
                ])
                ->setPublic(false);
        }

        // Set default services only if they exist and are configured
        if (!empty($config['connections']) && !empty($config['entity_managers'])) {
            $defaultConnection = $config['default_connection'] ?? null;
            $defaultEntityManager = $config['default_entity_manager'] ?? null;
            
            if ($defaultConnection && $defaultEntityManager && 
                isset($config['connections'][$defaultConnection]) && 
                isset($config['entity_managers'][$defaultEntityManager])) {
                
$container->setAlias('sybase_ase_orm.connection', "sybase_ase_orm.connection.$defaultConnection")
                    ->setPublic(false);
                $container->setAlias('sybase_ase_orm.entity_manager', "sybase_ase_orm.entity_manager.$defaultEntityManager")
                    ->setPublic(true);
                $container->setAlias(EntityManager::class, 'sybase_ase_orm.entity_manager')
                    ->setPublic(true);
                
                // Register dependent services
                $container->register('sybase_ase_orm.oql_parser', \Shedeza\SybaseAseOrmBundle\ORM\Query\OQLParser::class)
                    ->setArguments([new Reference('sybase_ase_orm.entity_manager')])
                    ->setPublic(true);
                    
                $container->register('sybase_ase_orm.schema_validator', \Shedeza\SybaseAseOrmBundle\ORM\Tools\SchemaValidator::class)
                    ->setArguments([new Reference('sybase_ase_orm.entity_manager')])
                    ->setPublic(true);
                    
$container->register('sybase_ase_orm.validate_schema_command', \Shedeza\SybaseAseOrmBundle\Command\ValidateSchemaCommand::class)
                    ->setArguments([new Reference('sybase_ase_orm.entity_manager')])
                    ->addTag('console.command');
                
// Register repositories for entities
                $this->registerRepositories($container, $config['entity_managers'][$defaultEntityManager]['mappings']);
}
        }
    }
    
private function registerRepositories(ContainerBuilder $container, array $mappings): void
    {
        // Store mapping info for compiler pass
        $container->setParameter('sybase_ase_orm.entity_mappings', $mappings);
    }
}