<?php

namespace Shedeza\SybaseAseOrmBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuración del bundle Sybase ASE ORM
 * 
 * Define la estructura y validación de la configuración del bundle,
 * incluyendo conexiones a base de datos y administradores de entidades.
 */
class Configuration implements ConfigurationInterface
{
    /**
     * Construye el árbol de configuración
     * 
     * @return TreeBuilder El constructor del árbol de configuración
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sybase_ase_orm');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('connections')
                    ->useAttributeAsKey('name')
                    ->defaultValue([])
                    ->arrayPrototype()
                        ->beforeNormalization()
                            ->ifString()
                            ->then(function ($v) {
                                if (!is_string($v) || trim($v) === '') {
                                    throw new \InvalidArgumentException('Connection URL must be a non-empty string');
                                }
                                
                                // Validar formato básico de URL
                                if (!filter_var($v, FILTER_VALIDATE_URL) && !preg_match('/^sybase:\/\//', $v)) {
                                    throw new \InvalidArgumentException('Invalid connection URL format. Expected format: sybase://username:password@host:port/database');
                                }
                                
                                return ['url' => $v];
                            })
                        ->end()
                        ->children()
                            ->scalarNode('url')->end()
                            ->scalarNode('host')->end()
                            ->scalarNode('port')->defaultValue(5000)->end()
                            ->scalarNode('database')->end()
                            ->scalarNode('username')->end()
                            ->scalarNode('password')->end()
                            ->scalarNode('charset')->defaultValue('utf8')->end()
                        ->end()
                        ->validate()
                            ->ifTrue(function ($v) {
                                // Validar que se proporcione URL o configuración detallada
                                $hasUrl = isset($v['url']) && !empty(trim($v['url']));
                                $hasDetailedConfig = isset($v['host']) && isset($v['database']) && isset($v['username']);
                                
                                return !$hasUrl && !$hasDetailedConfig;
                            })
                            ->thenInvalid('Either "url" or "host", "database", and "username" must be provided')
                        ->end()
                        ->validate()
                            ->ifTrue(function ($v) {
                                // Validar puerto si se proporciona
                                if (isset($v['port'])) {
                                    $port = (int) $v['port'];
                                    return $port <= 0 || $port > 65535;
                                }
                                return false;
                            })
                            ->thenInvalid('Port must be a valid number between 1 and 65535')
                        ->end()
                        ->validate()
                            ->ifTrue(function ($v) {
                                // Validar charset si se proporciona
                                if (isset($v['charset'])) {
                                    $validCharsets = ['utf8', 'utf8mb4', 'latin1', 'ascii', 'cp1252'];
                                    return !in_array(strtolower($v['charset']), $validCharsets, true);
                                }
                                return false;
                            })
                            ->thenInvalid('Invalid charset. Supported charsets: utf8, utf8mb4, latin1, ascii, cp1252')
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('default_connection')
                    ->defaultValue('default')
                    ->validate()
                        ->ifTrue(function ($v) {
                            return !is_string($v) || trim($v) === '';
                        })
                        ->thenInvalid('Default connection name must be a non-empty string')
                    ->end()
                ->end()
                ->arrayNode('entity_managers')
                    ->useAttributeAsKey('name')
                    ->defaultValue([])
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('connection')->defaultValue('default')->end()
                            ->arrayNode('mappings')
                                ->useAttributeAsKey('name')
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('type')
                                            ->defaultValue('attribute')
                                            ->validate()
                                                ->ifNotInArray(['attribute', 'annotation'])
                                                ->thenInvalid('Invalid mapping type. Supported types: attribute, annotation')
                                            ->end()
                                        ->end()
                                        ->scalarNode('dir')
                                            ->isRequired()
                                            ->validate()
                                                ->ifTrue(function ($v) {
                                                    return !is_string($v) || trim($v) === '';
                                                })
                                                ->thenInvalid('Directory path must be a non-empty string')
                                            ->end()
                                        ->end()
                                        ->scalarNode('prefix')
                                            ->validate()
                                                ->ifTrue(function ($v) {
                                                    return $v !== null && (!is_string($v) || trim($v) === '');
                                                })
                                                ->thenInvalid('Prefix must be a non-empty string or null')
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('default_entity_manager')
                    ->defaultValue('default')
                    ->validate()
                        ->ifTrue(function ($v) {
                            return !is_string($v) || trim($v) === '';
                        })
                        ->thenInvalid('Default entity manager name must be a non-empty string')
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}