<?php

namespace Shedeza\SybaseAseOrmBundle\ORM\Mapping;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class OneToMany
{
    public function __construct(
        public string $targetEntity,
        public ?string $mappedBy = null,
        public bool $cascade = false,
        public bool $fetchEager = false // false = LAZY, true = EAGER
    ) {
        if (empty($this->targetEntity)) {
            throw new \InvalidArgumentException('Target entity cannot be empty');
        }
    }
}