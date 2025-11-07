<?php

namespace Shedeza\SybaseAseOrmBundle\ORM\Mapping;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class GeneratedValue
{
    public const IDENTITY = 'IDENTITY';
    public const SEQUENCE = 'SEQUENCE';

    public function __construct(
        public string $strategy = self::IDENTITY
    ) {
        if (!in_array($this->strategy, [self::IDENTITY, self::SEQUENCE], true)) {
            throw new \InvalidArgumentException('Invalid generation strategy');
        }
    }
}