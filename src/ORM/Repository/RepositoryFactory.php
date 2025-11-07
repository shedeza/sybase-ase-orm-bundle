<?php

namespace Shedeza\SybaseAseOrmBundle\ORM\Repository;

use Shedeza\SybaseAseOrmBundle\ORM\EntityManager;

class RepositoryFactory
{
    private EntityManager $entityManager;
    
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }
    
    public function getRepository(string $entityClass): RepositoryInterface
    {
        return $this->entityManager->getRepository($entityClass);
    }
}