<?php

namespace Shedeza\SybaseAseOrmBundle\ORM\Cache;

use Shedeza\SybaseAseOrmBundle\ORM\Mapping\EntityMetadata;

/**
 * Cache de metadatos de entidades
 */
class MetadataCache
{
    private array $cache = [];
    private bool $enabled;
    
    public function __construct(bool $enabled = true)
    {
        $this->enabled = $enabled;
    }
    
    public function get(string $className): ?EntityMetadata
    {
        return $this->enabled ? ($this->cache[$className] ?? null) : null;
    }
    
    public function set(string $className, EntityMetadata $metadata): void
    {
        if ($this->enabled) {
            $this->cache[$className] = $metadata;
        }
    }
    
    public function has(string $className): bool
    {
        return $this->enabled && isset($this->cache[$className]);
    }
    
    public function clear(): void
    {
        $this->cache = [];
    }
}