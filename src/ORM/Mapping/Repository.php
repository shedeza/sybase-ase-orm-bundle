<?php

namespace Shedeza\SybaseAseOrmBundle\ORM\Mapping;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Repository
{
    public function __construct(
        public string $repositoryClass
    ) {
        if (empty($this->repositoryClass) || !class_exists($this->repositoryClass)) {
            throw new \InvalidArgumentException('Repository class must exist');
        }
    }
}