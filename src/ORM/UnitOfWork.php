<?php

namespace Shedeza\SybaseAseOrmBundle\ORM;

use Shedeza\SybaseAseOrmBundle\DBAL\Connection;
use Shedeza\SybaseAseOrmBundle\ORM\Mapping\EntityMetadata;

/**
 * Unidad de Trabajo para gestión de cambios de entidades
 * 
 * Implementa el patrón Unit of Work para rastrear cambios
 * y optimizar operaciones de base de datos.
 */
class UnitOfWork
{
    // Estados de entidades
    public const STATE_MANAGED = 1;
    public const STATE_NEW = 2;
    public const STATE_DETACHED = 3;
    public const STATE_REMOVED = 4;
    
    private EntityManager $entityManager;
    private Connection $connection;
    
    /** @var array Mapa de identidad */
    private array $identityMap = [];
    
    /** @var array Estados de entidades */
    private array $entityStates = [];
    
    /** @var array Datos originales de entidades */
    private array $originalEntityData = [];
    
    /** @var array Entidades programadas para inserción */
    private array $entityInsertions = [];
    
    /** @var array Entidades programadas para actualización */
    private array $entityUpdates = [];
    
    /** @var array Entidades programadas para eliminación */
    private array $entityDeletions = [];
    
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->connection = $entityManager->getConnection();
    }
    
    /**
     * Programa una entidad para persistencia
     */
    public function persist(object $entity): void
    {
        $oid = spl_object_id($entity);
        
        if (isset($this->entityStates[$oid])) {
            switch ($this->entityStates[$oid]) {
                case self::STATE_REMOVED:
                    unset($this->entityDeletions[$oid]);
                    $this->entityStates[$oid] = self::STATE_MANAGED;
                    break;
                case self::STATE_DETACHED:
                    $this->entityStates[$oid] = self::STATE_MANAGED;
                    $this->scheduleForInsert($entity);
                    break;
            }
            return;
        }
        
        $this->entityStates[$oid] = self::STATE_NEW;
        $this->scheduleForInsert($entity);
    }
    
    /**
     * Programa una entidad para eliminación
     */
    public function remove(object $entity): void
    {
        $oid = spl_object_id($entity);
        
        if (!isset($this->entityStates[$oid])) {
            throw new \InvalidArgumentException('Entity is not managed');
        }
        
        switch ($this->entityStates[$oid]) {
            case self::STATE_NEW:
                unset($this->entityInsertions[$oid], $this->entityStates[$oid]);
                break;
            case self::STATE_MANAGED:
                $this->scheduleForDelete($entity);
                $this->entityStates[$oid] = self::STATE_REMOVED;
                break;
        }
    }
    
    /**
     * Registra una entidad como gestionada
     */
    public function registerManaged(object $entity, array $id, array $data): void
    {
        $oid = spl_object_id($entity);
        $this->entityStates[$oid] = self::STATE_MANAGED;
        $this->originalEntityData[$oid] = $data;
        $this->addToIdentityMap($entity, $id);
    }
    
    /**
     * Desconecta una entidad
     */
    public function detach(object $entity): void
    {
        $oid = spl_object_id($entity);
        
        unset(
            $this->entityStates[$oid],
            $this->originalEntityData[$oid],
            $this->entityInsertions[$oid],
            $this->entityUpdates[$oid],
            $this->entityDeletions[$oid]
        );
        
        $this->removeFromIdentityMap($entity);
    }
    
    /**
     * Ejecuta todas las operaciones pendientes
     */
    public function commit(): void
    {
        $this->computeChangeSets();
        
        if (empty($this->entityInsertions) && empty($this->entityUpdates) && empty($this->entityDeletions)) {
            return;
        }
        
        $this->connection->beginTransaction();
        
        try {
            $this->executeInsertions();
            $this->executeUpdates();
            $this->executeDeletions();
            
            $this->connection->commit();
            $this->postCommitCleanup();
        } catch (\Throwable $e) {
            $this->connection->rollback();
            throw new \RuntimeException('Commit failed: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Limpia todos los estados
     */
    public function clear(): void
    {
        $this->identityMap = [];
        $this->entityStates = [];
        $this->originalEntityData = [];
        $this->entityInsertions = [];
        $this->entityUpdates = [];
        $this->entityDeletions = [];
    }
    
    /**
     * Obtiene una entidad del mapa de identidad
     */
    public function tryGetById(array $id, string $className): ?object
    {
        $idHash = $this->getIdHash($id, $className);
        return $this->identityMap[$idHash] ?? null;
    }
    
    /**
     * Calcula los conjuntos de cambios
     */
    private function computeChangeSets(): void
    {
        foreach ($this->entityStates as $oid => $state) {
            if ($state !== self::STATE_MANAGED) {
                continue;
            }
            
            $entity = $this->getEntityById($oid);
            if (!$entity) {
                continue;
            }
            
            $metadata = $this->entityManager->getClassMetadata(get_class($entity));
            $actualData = $this->extractEntityData($entity, $metadata);
            $originalData = $this->originalEntityData[$oid] ?? [];
            
            if ($this->hasChanges($actualData, $originalData)) {
                $this->entityUpdates[$oid] = $entity;
            }
        }
    }
    
    /**
     * Programa una entidad para inserción
     */
    private function scheduleForInsert(object $entity): void
    {
        $this->entityInsertions[spl_object_id($entity)] = $entity;
    }
    
    /**
     * Programa una entidad para eliminación
     */
    private function scheduleForDelete(object $entity): void
    {
        $this->entityDeletions[spl_object_id($entity)] = $entity;
    }
    
    /**
     * Ejecuta las inserciones
     */
    private function executeInsertions(): void
    {
        foreach ($this->entityInsertions as $entity) {
            $this->executeInsert($entity);
        }
    }
    
    /**
     * Ejecuta las actualizaciones
     */
    private function executeUpdates(): void
    {
        foreach ($this->entityUpdates as $entity) {
            $this->executeUpdate($entity);
        }
    }
    
    /**
     * Ejecuta las eliminaciones
     */
    private function executeDeletions(): void
    {
        foreach ($this->entityDeletions as $entity) {
            $this->executeDelete($entity);
        }
    }
    
    /**
     * Ejecuta una inserción individual
     */
    private function executeInsert(object $entity): void
    {
        $metadata = $this->entityManager->getClassMetadata(get_class($entity));
        $data = $this->extractEntityData($entity, $metadata);
        
        $columns = [];
        $values = [];
        $params = [];
        
        foreach ($data as $field => $value) {
            $fieldMapping = $metadata->getField($field);
            if (!$fieldMapping) continue;
            
            $columns[] = $fieldMapping['columnName'];
            $values[] = '?';
            $params[] = $value;
        }
        
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $metadata->getFullTableName(),
            implode(', ', $columns),
            implode(', ', $values)
        );
        
        $this->connection->executeUpdate($sql, $params);
        
        // Manejar ID generado
        if ($metadata->getIdGeneratorType() === 'IDENTITY' && !$metadata->hasCompositeId()) {
            $id = $this->connection->lastInsertId();
            $idField = $metadata->getIdentifier();
            $this->setEntityProperty($entity, $idField, $id);
        }
    }
    
    /**
     * Ejecuta una actualización individual
     */
    private function executeUpdate(object $entity): void
    {
        $metadata = $this->entityManager->getClassMetadata(get_class($entity));
        $data = $this->extractEntityData($entity, $metadata);
        $identifiers = $metadata->getIdentifiers();
        
        $setParts = [];
        $params = [];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $identifiers, true)) continue;
            
            $fieldMapping = $metadata->getField($field);
            if (!$fieldMapping) continue;
            
            $setParts[] = $fieldMapping['columnName'] . ' = ?';
            $params[] = $value;
        }
        
        $whereConditions = [];
        foreach ($identifiers as $idField) {
            $fieldMapping = $metadata->getField($idField);
            $whereConditions[] = $fieldMapping['columnName'] . ' = ?';
            $params[] = $data[$idField];
        }
        
        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $metadata->getFullTableName(),
            implode(', ', $setParts),
            implode(' AND ', $whereConditions)
        );
        
        $this->connection->executeUpdate($sql, $params);
    }
    
    /**
     * Ejecuta una eliminación individual
     */
    private function executeDelete(object $entity): void
    {
        $metadata = $this->entityManager->getClassMetadata(get_class($entity));
        $data = $this->extractEntityData($entity, $metadata);
        $identifiers = $metadata->getIdentifiers();
        
        $whereConditions = [];
        $params = [];
        
        foreach ($identifiers as $idField) {
            $fieldMapping = $metadata->getField($idField);
            $whereConditions[] = $fieldMapping['columnName'] . ' = ?';
            $params[] = $data[$idField];
        }
        
        $sql = sprintf(
            'DELETE FROM %s WHERE %s',
            $metadata->getFullTableName(),
            implode(' AND ', $whereConditions)
        );
        
        $this->connection->executeUpdate($sql, $params);
    }
    
    /**
     * Limpieza post-commit
     */
    private function postCommitCleanup(): void
    {
        // Actualizar estados
        foreach ($this->entityInsertions as $oid => $entity) {
            $this->entityStates[$oid] = self::STATE_MANAGED;
            $metadata = $this->entityManager->getClassMetadata(get_class($entity));
            $this->originalEntityData[$oid] = $this->extractEntityData($entity, $metadata);
        }
        
        foreach ($this->entityDeletions as $oid => $entity) {
            $this->detach($entity);
        }
        
        // Limpiar operaciones programadas
        $this->entityInsertions = [];
        $this->entityUpdates = [];
        $this->entityDeletions = [];
    }
    
    /**
     * Extrae datos de una entidad
     */
    private function extractEntityData(object $entity, EntityMetadata $metadata): array
    {
        $data = [];
        
        foreach ($metadata->getFields() as $field => $mapping) {
            $data[$field] = $this->getEntityProperty($entity, $field);
        }
        
        return $data;
    }
    
    /**
     * Verifica si hay cambios entre datos
     */
    private function hasChanges(array $actualData, array $originalData): bool
    {
        return $actualData !== $originalData;
    }
    
    /**
     * Agrega entidad al mapa de identidad
     */
    private function addToIdentityMap(object $entity, array $id): void
    {
        $className = get_class($entity);
        $idHash = $this->getIdHash($id, $className);
        $this->identityMap[$idHash] = $entity;
    }
    
    /**
     * Remueve entidad del mapa de identidad
     */
    private function removeFromIdentityMap(object $entity): void
    {
        $className = get_class($entity);
        $metadata = $this->entityManager->getClassMetadata($className);
        $id = $this->getEntityId($entity, $metadata);
        
        if ($id) {
            $idHash = $this->getIdHash($id, $className);
            unset($this->identityMap[$idHash]);
        }
    }
    
    /**
     * Genera hash de ID
     */
    private function getIdHash(array $id, string $className): string
    {
        ksort($id);
        return $className . '#' . serialize($id);
    }
    
    /**
     * Obtiene ID de entidad
     */
    private function getEntityId(object $entity, EntityMetadata $metadata): ?array
    {
        $identifiers = $metadata->getIdentifiers();
        $id = [];
        
        foreach ($identifiers as $idField) {
            $value = $this->getEntityProperty($entity, $idField);
            if ($value === null) {
                return null;
            }
            $id[$idField] = $value;
        }
        
        return $id;
    }
    
    /**
     * Obtiene propiedad de entidad
     */
    private function getEntityProperty(object $entity, string $property): mixed
    {
        $reflection = new \ReflectionProperty($entity, $property);
        $reflection->setAccessible(true);
        return $reflection->getValue($entity);
    }
    
    /**
     * Establece propiedad de entidad
     */
    private function setEntityProperty(object $entity, string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty($entity, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $value);
    }
    
    /**
     * Obtiene entidad por OID
     */
    private function getEntityById(int $oid): ?object
    {
        foreach ($this->identityMap as $entity) {
            if (spl_object_id($entity) === $oid) {
                return $entity;
            }
        }
        return null;
    }
}