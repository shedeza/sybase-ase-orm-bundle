<?php

namespace Shedeza\SybaseAseOrmBundle\ORM\Mapping;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class JoinTable
{
    public function __construct(
        public string $name,
        public ?string $schema = null,
        public array $joinColumns = [],
        public array $inverseJoinColumns = []
    ) {
        if (empty($this->name)) {
            throw new \InvalidArgumentException('Join table name cannot be empty');
        }
    }
}