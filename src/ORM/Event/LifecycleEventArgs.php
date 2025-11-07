<?php

namespace Shedeza\SybaseAseOrmBundle\ORM\Event;

use Shedeza\SybaseAseOrmBundle\ORM\EntityManager;

class LifecycleEventArgs
{
    private object $entity;
    private EntityManager $entityManager;

    public function __construct(object $entity, EntityManager $entityManager)
    {
        $this->entity = $entity;
        $this->entityManager = $entityManager;
    }

    public function getEntity(): object
    {
        return $this->entity;
    }

    public function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }
}