<?php

namespace Shedeza\SybaseAseOrmBundle\ORM\Repository;

use Shedeza\SybaseAseOrmBundle\ORM\EntityManager;
use Shedeza\SybaseAseOrmBundle\ORM\Mapping\EntityMetadata;
use Shedeza\SybaseAseOrmBundle\ORM\Query\Query;
use Shedeza\SybaseAseOrmBundle\Exception\ConfigurationException;

abstract class AbstractRepository implements RepositoryInterface
{
protected ?EntityManager $entityManager = null;
    protected string $entityClass = '';
    protected ?EntityMetadata $metadata = null;

public function __construct($entityManager = null, string $entityClass = '')
    {
        if ($entityManager instanceof EntityManager && !empty($entityClass)) {
            $this->initialize($entityManager, $entityClass);
        }
    }
    
    /**
     * Initialize repository with EntityManager and entity class
     */
    public function initialize(EntityManager $entityManager, string $entityClass): void
    {
        if (empty($entityClass) || !class_exists($entityClass)) {
            throw new \InvalidArgumentException("Invalid entity class: {$entityClass}");
        }
        
        $this->entityManager = $entityManager;
        $this->entityClass = $entityClass;
        $this->metadata = $entityManager->getClassMetadata($entityClass);
    }
    
    /**
     * Check if repository is properly initialized
     */
    private function ensureInitialized(): void
    {
        if ($this->entityManager === null) {
            throw ConfigurationException::entityManagerNotConfigured();
        }
    }

public function find(mixed $id): ?object
    {
        $this->ensureInitialized();
        
        if ($id === null || $id === '') {
            return null;
        }
        
        return $this->entityManager->find($this->entityClass, $id);
    }

public function findAll(): array
    {
        $this->ensureInitialized();
        
        $sql = sprintf('SELECT * FROM %s', $this->metadata->getFullTableName());
        $stmt = $this->entityManager->getConnection()->executeQuery($sql);
        
        $entities = [];
        while ($data = $stmt->fetch()) {
            $entities[] = $this->hydrate($data);
        }
        
        return $entities;
    }

public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        $this->ensureInitialized();
        
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('Limit must be a positive integer');
        }
        
        if ($offset !== null && $offset < 0) {
            throw new \InvalidArgumentException('Offset must be a positive integer');
        }
        
        $sql = sprintf('SELECT * FROM %s', $this->metadata->getFullTableName());
        $params = [];
        
        if (!empty($criteria)) {
            $whereParts = [];
            foreach ($criteria as $field => $value) {
                $fieldMapping = $this->metadata->getField($field);
                if ($fieldMapping) {
                    $whereParts[] = $fieldMapping['columnName'] . ' = ?';
                    $params[] = $value;
                }
            }
            
            if (!empty($whereParts)) {
                $sql .= ' WHERE ' . implode(' AND ', $whereParts);
            }
        }
        
        if ($orderBy) {
            $orderParts = [];
            foreach ($orderBy as $field => $direction) {
                $fieldMapping = $this->metadata->getField($field);
                if ($fieldMapping) {
                    $orderParts[] = $fieldMapping['columnName'] . ' ' . strtoupper($direction);
                }
            }
            
            if (!empty($orderParts)) {
                $sql .= ' ORDER BY ' . implode(', ', $orderParts);
            }
        }
        
        if ($limit) {
            $sql .= ' TOP ' . $limit;
        }
        
        try {
            $stmt = $this->entityManager->getConnection()->executeQuery($sql, $params);
            
            $entities = [];
            while ($data = $stmt->fetch()) {
                $entities[] = $this->hydrate($data);
            }
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to execute query: ' . $e->getMessage(), 0, $e);
        }
        
        return $entities;
    }

public function findOneBy(array $criteria): ?object
    {
        $this->ensureInitialized();
        
        $results = $this->findBy($criteria, null, 1);
        return $results[0] ?? null;
    }

public function count(array $criteria = []): int
    {
        $this->ensureInitialized();
        
        $sql = sprintf('SELECT COUNT(*) as cnt FROM %s', $this->metadata->getFullTableName());
        $params = [];
        
        if (!empty($criteria)) {
            $whereParts = [];
            foreach ($criteria as $field => $value) {
                $fieldMapping = $this->metadata->getField($field);
                if ($fieldMapping) {
                    $whereParts[] = $fieldMapping['columnName'] . ' = ?';
                    $params[] = $value;
                }
            }
            
            if (!empty($whereParts)) {
                $sql .= ' WHERE ' . implode(' AND ', $whereParts);
            }
        }
        
        try {
            $stmt = $this->entityManager->getConnection()->executeQuery($sql, $params);
            $result = $stmt->fetch();
            
            return (int) ($result['cnt'] ?? 0);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to count entities: ' . $e->getMessage(), 0, $e);
        }
    }

protected function createQuery(string $oql): Query
    {
        $this->ensureInitialized();
        
        return $this->entityManager->createQuery($oql);
    }

    /**
     * Hidrata una entidad con datos de la base de datos
     * 
     * @param array $data Datos de la base de datos
     * @return object Entidad hidratada
     * @throws \RuntimeException Si falla la hidratación crítica
     */
    protected function hydrate(array $data): object
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Cannot hydrate entity with empty data');
        }
        
        try {
            $entity = new $this->entityClass();
        } catch (\Throwable $e) {
            throw new \RuntimeException("Failed to instantiate entity '{$this->entityClass}': " . $e->getMessage(), 0, $e);
        }
        
        $hydratedFields = 0;
        $errors = [];
        
        foreach ($this->metadata->getFields() as $fieldName => $fieldMapping) {
            $columnName = $fieldMapping['columnName'];
            
            if (!isset($data[$columnName])) {
                continue;
            }
            
            try {
                $reflection = new \ReflectionProperty($entity, $fieldName);
                $reflection->setAccessible(true);
                
                $value = $this->convertValue($data[$columnName], $fieldMapping['type']);
                $reflection->setValue($entity, $value);
                $hydratedFields++;
                
            } catch (\ReflectionException $e) {
                $errors[] = "Property '{$fieldName}' not found: " . $e->getMessage();
            } catch (\Throwable $e) {
                $errors[] = "Failed to set property '{$fieldName}': " . $e->getMessage();
            }
        }
        
        if ($hydratedFields === 0 && !empty($this->metadata->getFields())) {
            throw new \RuntimeException('Failed to hydrate any fields for entity ' . $this->entityClass);
        }
        
        if (!empty($errors) && count($errors) > count($this->metadata->getFields()) / 2) {
            throw new \RuntimeException('Too many hydration errors: ' . implode(', ', array_slice($errors, 0, 3)));
        }
        
        return $entity;
    }
    
    /**
     * Convierte un valor según el tipo de campo
     * 
     * @param mixed $value Valor a convertir
     * @param string $type Tipo de campo
     * @return mixed Valor convertido
     */
    private function convertValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }
        
        return match ($type) {
            'integer', 'int' => (int) $value,
            'float', 'decimal' => (float) $value,
            'boolean', 'bool' => (bool) $value,
            'datetime' => $value instanceof \DateTime ? $value : new \DateTime($value),
            'date' => $value instanceof \DateTime ? $value : new \DateTime($value),
            default => $value
        };
    }
}