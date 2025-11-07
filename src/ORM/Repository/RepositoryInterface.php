<?php

namespace Shedeza\SybaseAseOrmBundle\ORM\Repository;

interface RepositoryInterface
{
    public function find(mixed $id): ?object;
    
    public function findAll(): array;
    
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;
    
    public function findOneBy(array $criteria): ?object;
    
    public function count(array $criteria = []): int;
}