<?php
require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Config\Definition\Processor;
use Shedeza\SybaseAseOrmBundle\DependencyInjection\Configuration;

$processor = new Processor();
$configuration = new Configuration();
$tree = $configuration->getConfigTreeBuilder()->buildTree();

$cases = [
    'env_placeholder' => [
        'connections' => [
            'default' => '%env(resolve:DATABASE_SYBASE_URL)%'
        ],
        'entity_managers' => [
            'default' => [
                'connection' => 'default',
                'mappings' => [
                    'App' => [
                        'type' => 'attribute',
                        'dir' => '%kernel.project_dir%/src/Entity',
                        'prefix' => 'App\\Entity'
                    ]
                ]
            ]
        ],
        'default_connection' => 'default',
        'default_entity_manager' => 'default'
    ],
    'literal_url' => [
        'connections' => [
            'default' => 'sybase://username:password@host:5000/database?charset=utf8'
        ],
        'entity_managers' => [
            'default' => [
                'connection' => 'default',
                'mappings' => [
                    'App' => [
                        'type' => 'attribute',
                        'dir' => '%kernel.project_dir%/src/Entity',
                        'prefix' => 'App\\Entity'
                    ]
                ]
            ]
        ],
        'default_connection' => 'default',
        'default_entity_manager' => 'default'
    ],
    'detailed' => [
        'connections' => [
            'default' => [
                'host' => 'host',
                'port' => 5000,
                'database' => 'database',
                'username' => 'user',
                'password' => 'pass',
                'charset' => 'utf8'
            ]
        ],
        'entity_managers' => [
            'default' => [
                'connection' => 'default',
                'mappings' => [
                    'App' => [
                        'type' => 'attribute',
                        'dir' => '%kernel.project_dir%/src/Entity',
                        'prefix' => 'App\\Entity'
                    ]
                ]
            ]
        ],
        'default_connection' => 'default',
        'default_entity_manager' => 'default'
    ]
];

foreach ($cases as $name => $cfg) {
    echo "\n--- CASE: {$name} ---\n";
    try {
        $processed = $processor->process($tree, [$cfg]);
        echo json_encode($processed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    } catch (\Exception $e) {
        echo "ERROR processing {$name}: " . $e->getMessage() . "\n";
    }
}

echo "\nDone.\n";
