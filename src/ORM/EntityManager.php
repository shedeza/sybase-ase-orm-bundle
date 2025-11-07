<?php

namespace Shedeza\SybaseAseOrmBundle\ORM;

use Shedeza\SybaseAseOrmBundle\DBAL\Connection;
use Shedeza\SybaseAseOrmBundle\ORM\Mapping\AttributeReader;
use Shedeza\SybaseAseOrmBundle\ORM\Mapping\EntityMetadata;
use Shedeza\SybaseAseOrmBundle\ORM\Repository\EntityRepository;
use Shedeza\SybaseAseOrmBundle\ORM\Repository\AbstractRepository;
use Shedeza\SybaseAseOrmBundle\ORM\UnitOfWork;
use Shedeza\SybaseAseOrmBundle\ORM\Cache\MetadataCache;
use Shedeza\SybaseAseOrmBundle\ORM\Proxy\ProxyFactory;

/**
 * Administrador de entidades principal del ORM
 * 
 * Maneja la persistencia, consultas y el ciclo de vida de las entidades.
 * Implementa el patrón Unit of Work para optimizar las operaciones de base de datos.
 */
class EntityManager
{
    // Componentes principales
    private Connection $connection;
    private AttributeReader $metadataReader;
    private UnitOfWork $unitOfWork;
    private MetadataCache $metadataCache;
    private ProxyFactory $proxyFactory;
    
    // Configuración
    private array $config;

    /**
     * Constructor del EntityManager
     * 
     * @param Connection $connection Conexión a la base de datos
     * @param array $config Configuración del ORM
     */
    public function __construct(Connection $connection, array $config = [])
    {
        $this->connection = $connection;
        $this->config = $config;
        $this->metadataReader = new AttributeReader();
        $this->metadataCache = new MetadataCache($config['enable_metadata_cache'] ?? true);
        $this->unitOfWork = new UnitOfWork($this);
        $this->proxyFactory = new ProxyFactory($this);
    }

    /**
     * Programa una entidad para persistencia
     * 
     * Si la entidad no tiene ID, se programa para inserción.
     * Si ya tiene ID, se programa para actualización.
     * 
     * @param object $entity La entidad a persistir
     * @throws \InvalidArgumentException Si el parámetro no es un objeto
     */
    public function persist(object $entity): void
    {
        if (!is_object($entity)) {
            throw new \InvalidArgumentException('Entity must be an object');
        }
        
        $this->unitOfWork->persist($entity);
    }

    /**
     * Programa una entidad para eliminación
     * 
     * @param object $entity La entidad a eliminar
     * @throws \InvalidArgumentException Si el parámetro no es un objeto o no tiene ID
     */
    public function remove(object $entity): void
    {
        if (!is_object($entity)) {
            throw new \InvalidArgumentException('Entity must be an object');
        }
        
        $this->unitOfWork->remove($entity);
    }

    /**
     * Ejecuta todas las operaciones pendientes en la base de datos
     * 
     * Procesa las inserciones, actualizaciones y eliminaciones programadas
     * dentro de una transacción para garantizar la consistencia.
     * 
     * @throws \RuntimeException Si la operación falla
     */
    public function flush(): void
    {
        try {
            $this->unitOfWork->commit();
        } catch (\Throwable $e) {
            throw new \RuntimeException('Flush operation failed: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Ejecuta una función dentro de una transacción
     * 
     * @param callable $func Función a ejecutar que recibe el EntityManager como parámetro
     * @return mixed El resultado de la función
     * @throws \Exception Si la transacción falla
     */
    public function transactional(callable $func): mixed
    {
        $this->connection->beginTransaction();
        
        try {
            $result = $func($this);
            $this->flush();
            $this->connection->commit();
            return $result;
        } catch (\Exception $e) {
            $this->connection->rollback();
            throw $e;
        }
    }

    /**
     * Busca una entidad por su identificador
     * 
     * Soporta tanto claves primarias simples como compuestas.
     * Utiliza el mapa de identidad para evitar consultas duplicadas.
     * 
     * @param string $className Nombre de la clase de la entidad
     * @param mixed $id Identificador (simple o array para claves compuestas)
     * @return object|null La entidad encontrada o null si no existe
     * @throws \InvalidArgumentException Si los parámetros son inválidos
     */
    public function find(string $className, mixed $id): ?object
    {
        if (empty($className) || !class_exists($className)) {
            throw new \InvalidArgumentException('Invalid class name provided');
        }
        
        if ($id === null || $id === '') {
            throw new \InvalidArgumentException('ID cannot be null or empty');
        }
        
        $metadata = $this->getClassMetadata($className);
        $identityKey = $this->buildIdentityKey($className, $id);
        
        // Verificar si ya está en el mapa de identidad
        $entity = $this->unitOfWork->tryGetById(is_array($id) ? $id : [$metadata->getIdentifier() => $id], $className);
        if ($entity) {
            return $entity;
        }
        
        $identifiers = $metadata->getIdentifiers();
        if (empty($identifiers)) {
            throw new \RuntimeException("Entity {$className} has no identifier fields");
        }
        
        $whereConditions = [];
        $params = [];
        
        if (count($identifiers) === 1) {
            // Clave primaria simple
            $idField = $identifiers[0];
            $idColumn = $metadata->getField($idField)['columnName'];
            $whereConditions[] = $idColumn . ' = ?';
            $params[] = $id;
        } else {
            // Clave primaria compuesta
            if (!is_array($id)) {
                throw new \InvalidArgumentException('Composite key requires array of values');
            }
            
            foreach ($identifiers as $idField) {
                if (!isset($id[$idField])) {
                    throw new \InvalidArgumentException("Missing value for identifier field: {$idField}");
                }
                
                $idColumn = $metadata->getField($idField)['columnName'];
                $whereConditions[] = $idColumn . ' = ?';
                $params[] = $id[$idField];
            }
        }
        
        $sql = sprintf(
            'SELECT * FROM %s WHERE %s',
            $metadata->getFullTableName(),
            implode(' AND ', $whereConditions)
        );
        
        $stmt = $this->connection->executeQuery($sql, $params);
        $data = $stmt->fetch();
        
        if (!$data) {
            return null;
        }
        
        // Hidratar la entidad y registrarla como gestionada
        $entity = $this->hydrate($className, $data, $metadata);
        $entityId = is_array($id) ? $id : [$metadata->getIdentifier() => $id];
        $this->unitOfWork->registerManaged($entity, $entityId, $data);
        
        return $entity;
    }

    /**
     * Obtiene el repositorio para una entidad
     * 
     * @param string $className Nombre de la clase de la entidad
     * @return AbstractRepository El repositorio de la entidad
     * @throws \InvalidArgumentException Si el nombre de clase es inválido
     */
    public function getRepository(string $className): AbstractRepository
    {
        if (empty($className) || !class_exists($className)) {
            throw new \InvalidArgumentException('Invalid class name provided');
        }
        
        $metadata = $this->getClassMetadata($className);
        $repositoryClass = $metadata->getRepositoryClass();
        
        // Usar repositorio personalizado si está definido
        if ($repositoryClass && class_exists($repositoryClass)) {
            return new $repositoryClass($this, $className);
        }
        
        // Usar repositorio genérico por defecto
        return new EntityRepository($this, $className);
    }

    /**
     * Obtiene los metadatos de una clase de entidad
     * 
     * Los metadatos se cachean para mejorar el rendimiento.
     * 
     * @param string $className Nombre de la clase
     * @return EntityMetadata Los metadatos de la entidad
     * @throws \InvalidArgumentException Si la clase no existe
     */
    public function getClassMetadata(string $className): EntityMetadata
    {
        if ($this->metadataCache->has($className)) {
            return $this->metadataCache->get($className);
        }
        
        if (!class_exists($className)) {
            throw new \InvalidArgumentException("Class '{$className}' does not exist");
        }
        
        $metadata = $this->metadataReader->getEntityMetadata($className);
        $this->metadataCache->set($className, $metadata);
        
        return $metadata;
    }
    
    /**
     * Limpia el cache de metadatos
     */
    public function clearMetadataCache(): void
    {
        $this->metadataCache->clear();
    }
    
    /**
     * Limpia todas las entidades del contexto
     * 
     * Elimina todas las entidades del mapa de identidad y
     * cancela todas las operaciones programadas.
     */
    public function clear(): void
    {
        $this->unitOfWork->clear();
    }
    
    /**
     * Desconecta una entidad del contexto
     * 
     * La entidad ya no será rastreada por el EntityManager.
     * 
     * @param object $entity La entidad a desconectar
     */
    public function detach(object $entity): void
    {
        $this->unitOfWork->detach($entity);
    }

    /**
     * Obtiene la conexión a la base de datos
     * 
     * @return Connection La conexión activa
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Crea una consulta OQL
     * 
     * @param string $oql La consulta en lenguaje OQL
     * @return \Shedeza\SybaseAseOrmBundle\ORM\Query\Query La consulta parseada
     */
    public function createQuery(string $oql): \Shedeza\SybaseAseOrmBundle\ORM\Query\Query
    {
        $parser = new \Shedeza\SybaseAseOrmBundle\ORM\Query\OQLParser($this);
        return $parser->parse($oql);
    }
    
    /**
     * Crea un proxy para lazy loading
     */
    public function getReference(string $className, mixed $id): object
    {
        // Verificar si ya está en el mapa de identidad
        $entityId = is_array($id) ? $id : [$this->getClassMetadata($className)->getIdentifier() => $id];
        $entity = $this->unitOfWork->tryGetById($entityId, $className);
        
        if ($entity) {
            return $entity;
        }
        
        // Crear proxy para lazy loading
        return $this->proxyFactory->getProxy($className, $id);
    }
    
    /**
     * Verifica si una entidad está gestionada
     */
    public function contains(object $entity): bool
    {
        $metadata = $this->getClassMetadata(get_class($entity));
        $id = $this->getEntityId($entity, $metadata);
        
        if (!$id) {
            return false;
        }
        
        return $this->unitOfWork->tryGetById($id, get_class($entity)) !== null;
    }
    
    /**
     * Refresca una entidad desde la base de datos
     */
    public function refresh(object $entity): void
    {
        $className = get_class($entity);
        $metadata = $this->getClassMetadata($className);
        $id = $this->getEntityId($entity, $metadata);
        
        if (!$id) {
            throw new \InvalidArgumentException('Entity must have an ID to be refreshed');
        }
        
        // Desconectar la entidad actual
        $this->detach($entity);
        
        // Cargar datos frescos
        $freshEntity = $this->find($className, $id);
        
        if (!$freshEntity) {
            throw new \RuntimeException('Entity no longer exists in database');
        }
        
        // Copiar propiedades
        foreach ($metadata->getFields() as $fieldName => $fieldMapping) {
            $value = $this->getPropertyValue($freshEntity, $fieldName);
            $this->setPropertyValue($entity, $fieldName, $value);
        }
        
        // Registrar como gestionada
        $data = [];
        foreach ($metadata->getFields() as $fieldName => $fieldMapping) {
            $data[$fieldName] = $this->getPropertyValue($entity, $fieldName);
        }
        
        $this->unitOfWork->registerManaged($entity, $id, $data);
    }
    
    /**
     * Obtiene el UnitOfWork
     */
    public function getUnitOfWork(): UnitOfWork
    {
        return $this->unitOfWork;
    }

    private function executeInsert(object $entity): void
    {
        $metadata = $this->getClassMetadata(get_class($entity));
        $identifiers = $metadata->getIdentifiers();
        $fields = $metadata->getFields();
        $columns = [];
        $values = [];
        $params = [];
        
        foreach ($fields as $fieldName => $fieldMapping) {
            if (in_array($fieldName, $identifiers, true) && 
                $metadata->getIdGeneratorType() === 'IDENTITY') {
                continue; // Skip auto-generated IDs
            }
            
            $columns[] = $fieldMapping['columnName'];
            $values[] = '?';
            $params[] = $this->getPropertyValue($entity, $fieldName);
        }
        
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $metadata->getFullTableName(),
            implode(', ', $columns),
            implode(', ', $values)
        );
        
        $this->connection->executeUpdate($sql, $params);
        
        // Set generated ID (only for single auto-generated keys)
        if ($metadata->getIdGeneratorType() === 'IDENTITY' && !$metadata->hasCompositeId()) {
            $id = $this->connection->lastInsertId();
            $idField = $metadata->getIdentifier();
            if ($idField) {
                $this->setPropertyValue($entity, $idField, $id);
            }
        }
    }

    private function executeUpdate(object $entity): void
    {
        $metadata = $this->getClassMetadata(get_class($entity));
        $identifiers = $metadata->getIdentifiers();
        $fields = $metadata->getFields();
        $setParts = [];
        $params = [];
        
        foreach ($fields as $fieldName => $fieldMapping) {
            if (in_array($fieldName, $identifiers, true)) {
                continue;
            }
            
            $setParts[] = $fieldMapping['columnName'] . ' = ?';
            $params[] = $this->getPropertyValue($entity, $fieldName);
        }
        
        $whereConditions = [];
        foreach ($identifiers as $idField) {
            $idColumn = $metadata->getField($idField)['columnName'];
            $whereConditions[] = $idColumn . ' = ?';
            $params[] = $this->getPropertyValue($entity, $idField);
        }
        
        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $metadata->getFullTableName(),
            implode(', ', $setParts),
            implode(' AND ', $whereConditions)
        );
        
        $this->connection->executeUpdate($sql, $params);
    }

    private function executeDelete(object $entity): void
    {
        $metadata = $this->getClassMetadata(get_class($entity));
        $identifiers = $metadata->getIdentifiers();
        
        $whereConditions = [];
        $params = [];
        
        foreach ($identifiers as $idField) {
            $idColumn = $metadata->getField($idField)['columnName'];
            $whereConditions[] = $idColumn . ' = ?';
            $params[] = $this->getPropertyValue($entity, $idField);
        }
        
        $sql = sprintf(
            'DELETE FROM %s WHERE %s',
            $metadata->getFullTableName(),
            implode(' AND ', $whereConditions)
        );
        
        $this->connection->executeUpdate($sql, $params);
    }

    private function hydrate(string $className, array $data, EntityMetadata $metadata): object
    {
        $entity = new $className();
        
        foreach ($metadata->getFields() as $fieldName => $fieldMapping) {
            $columnName = $fieldMapping['columnName'];
            if (isset($data[$columnName])) {
                $this->setPropertyValue($entity, $fieldName, $data[$columnName]);
            }
        }
        
        return $entity;
    }

    private function getEntityId(object $entity, EntityMetadata $metadata): mixed
    {
        $identifiers = $metadata->getIdentifiers();
        
        if (empty($identifiers)) {
            return null;
        }
        
        if (count($identifiers) === 1) {
            return $this->getPropertyValue($entity, $identifiers[0]);
        }
        
        // Composite key - return array of values
        $compositeId = [];
        foreach ($identifiers as $idField) {
            $value = $this->getPropertyValue($entity, $idField);
            if ($value === null) {
                return null; // If any part is null, the whole ID is null
            }
            $compositeId[$idField] = $value;
        }
        
        return $compositeId;
    }

    private function getPropertyValue(object $entity, string $property): mixed
    {
        try {
            $reflection = new \ReflectionProperty($entity, $property);
            $reflection->setAccessible(true);
            return $reflection->getValue($entity);
        } catch (\ReflectionException $e) {
            throw new \RuntimeException("Property '{$property}' not found in entity: " . $e->getMessage(), 0, $e);
        }
    }

    private function setPropertyValue(object $entity, string $property, mixed $value): void
    {
        try {
            $reflection = new \ReflectionProperty($entity, $property);
            $reflection->setAccessible(true);
            $reflection->setValue($entity, $value);
        } catch (\ReflectionException $e) {
            throw new \RuntimeException("Property '{$property}' not found in entity: " . $e->getMessage(), 0, $e);
        }
    }
    
    private function processCascadePersist(object $entity, $metadata): void
    {
        foreach ($metadata->getAssociations() as $fieldName => $association) {
            if (!($association['cascade'] ?? false)) {
                continue;
            }
            
            $relatedEntity = $this->getPropertyValue($entity, $fieldName);
            if ($relatedEntity === null) {
                continue;
            }
            
            if (is_array($relatedEntity) || $relatedEntity instanceof \Traversable) {
                foreach ($relatedEntity as $item) {
                    if (is_object($item)) {
                        $this->persist($item);
                    }
                }
            } elseif (is_object($relatedEntity)) {
                $this->persist($relatedEntity);
            }
        }
    }
    
    /**
     * Ejecuta eventos de ciclo de vida
     */
    public function triggerLifecycleEvent(string $eventName, object $entity): void
    {
        $className = get_class($entity);
        $reflection = new \ReflectionClass($className);
        
        foreach ($reflection->getMethods() as $method) {
            $attributes = $method->getAttributes();
            
            foreach ($attributes as $attribute) {
                $attributeName = $attribute->getName();
                
                if ($attributeName === "Shedeza\\SybaseAseOrmBundle\\ORM\\Mapping\\{$eventName}") {
                    $method->setAccessible(true);
                    $method->invoke($entity);
                }
            }
        }
    }
}