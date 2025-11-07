<?php

namespace Shedeza\SybaseAseOrmBundle\DBAL;

class DatabaseUrlParser
{
    public static function parseUrl(string $url): array
    {
        if (empty($url)) {
            throw new \InvalidArgumentException('Database URL cannot be empty');
        }

        $parsed = parse_url($url);
        
        if ($parsed === false) {
            throw new \InvalidArgumentException('Invalid database URL format');
        }

        $config = [
            'host' => $parsed['host'] ?? 'localhost',
            'port' => $parsed['port'] ?? 5000,
            'database' => ltrim($parsed['path'] ?? '', '/'),
            'username' => $parsed['user'] ?? '',
            'password' => $parsed['pass'] ?? '',
            'charset' => 'utf8'
        ];

        // Parse query parameters
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $queryParams);
            if (isset($queryParams['charset'])) {
                $config['charset'] = $queryParams['charset'];
            }
        }

        // Validate required fields
        if (empty($config['database'])) {
            throw new \InvalidArgumentException('Database name is required in URL');
        }

        return $config;
    }
}