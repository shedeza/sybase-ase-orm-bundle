<?php

namespace Shedeza\SybaseAseOrmBundle\ORM\Mapping;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class JoinColumn
{
    public function __construct(
        public string $name,
        public string $referencedColumnName = 'id',
        public bool $nullable = true
    ) {}
}