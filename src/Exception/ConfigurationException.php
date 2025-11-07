<?php

namespace Shedeza\SybaseAseOrmBundle\Exception;

class ConfigurationException extends \Exception
{
    public static function entityManagerNotConfigured(): self
    {
        return new self(
            'Sybase ASE ORM Bundle is not configured. Please:' . PHP_EOL .
            '1. Set DATABASE_URL in .env: DATABASE_URL=sybase://user:pass@host:port/db' . PHP_EOL .
            '2. Create config/packages/sybase_ase_orm.yaml with bundle configuration' . PHP_EOL .
            '3. See: https://github.com/shedeza/sybase-ase-orm-bundle#installation'
        );
    }
}