<?php

namespace Shedeza\SybaseAseOrmBundle\ORM\Mapping;

final class AssociationType
{
    public const ONE_TO_ONE = 'oneToOne';
    public const ONE_TO_MANY = 'oneToMany';
    public const MANY_TO_ONE = 'manyToOne';
    public const MANY_TO_MANY = 'manyToMany';
    
    public const FETCH_LAZY = 'LAZY';
    public const FETCH_EAGER = 'EAGER';
    
    private function __construct() {}
}