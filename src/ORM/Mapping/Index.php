<?php

namespace Shedeza\SybaseAseOrmBundle\ORM\Mapping;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class Index
{
    public function __construct(
        public ?string $name = null,
        public array $columns = [],
        public bool $unique = false
    ) {
        if (empty($this->columns)) {
            throw new \InvalidArgumentException('Index must have at least one column');
        }
    }
}