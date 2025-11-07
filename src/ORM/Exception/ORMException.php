<?php

namespace Shedeza\SybaseAseOrmBundle\ORM\Exception;

/**
 * Excepción base del ORM
 */
class ORMException extends \Exception
{
    public static function entityNotFound(string $className, mixed $id): self
    {
        return new self("Entity '{$className}' with ID '" . json_encode($id) . "' not found");
    }
    
    public static function invalidEntity(string $className): self
    {
        return new self("Class '{$className}' is not a valid entity");
    }
    
    public static function metadataNotFound(string $className): self
    {
        return new self("Metadata for entity '{$className}' not found");
    }
}