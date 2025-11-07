<?php

namespace Shedeza\SybaseAseOrmBundle\ORM\Mapping;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class OneToOne
{
    public function __construct(
        public string $targetEntity,
        public ?string $mappedBy = null,
        public ?string $inversedBy = null,
        public bool $cascade = false,
        public bool $fetchEager = false
    ) {
        if (empty($this->targetEntity)) {
            throw new \InvalidArgumentException('Target entity cannot be empty');
        }
    }
}