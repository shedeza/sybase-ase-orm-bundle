<?php

namespace Shedeza\SybaseAseOrmBundle\ORM\Repository;

use Shedeza\SybaseAseOrmBundle\ORM\EntityManager;

/**
 * Repositorio base para entidades
 * 
 * Proporciona funcionalidad básica de repositorio para cualquier entidad
 * que no tenga un repositorio personalizado definido.
 */
class EntityRepository extends AbstractRepository
{
    /**
     * Constructor del repositorio de entidad
     * 
     * @param EntityManager $entityManager Administrador de entidades
     * @param string $entityClass Clase de la entidad
     * @throws \InvalidArgumentException Si la clase de entidad es inválida
     */
    public function __construct(EntityManager $entityManager, string $entityClass)
    {
        try {
            parent::__construct($entityManager, $entityClass);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to initialize EntityRepository for '{$entityClass}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    /**
     * Busca una entidad por ID con validación mejorada
     * 
     * @param mixed $id Identificador de la entidad
     * @return object|null La entidad encontrada o null
     * @throws \InvalidArgumentException Si el ID es inválido
     * @throws \RuntimeException Si la búsqueda falla
     */
    public function find(mixed $id): ?object
    {
        if ($id === null || $id === '' || (is_array($id) && empty($id))) {
            return null;
        }
        
        try {
            return parent::find($id);
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to find entity '{$this->entityClass}' with ID: " . json_encode($id) . ". Error: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    /**
     * Busca entidades por criterios complejos
     * 
     * @param array $criteria Criterios de búsqueda
     * @param array|null $orderBy Ordenamiento
     * @param int|null $limit Límite de resultados
     * @param int|null $offset Desplazamiento
     * @return array Array de entidades encontradas
     * @throws \RuntimeException Si la consulta falla
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        try {
            return parent::findBy($criteria, $orderBy, $limit, $offset);
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to execute findBy query for '{$this->entityClass}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    /**
     * Verifica si existe una entidad con los criterios dados
     * 
     * @param array $criteria Criterios de búsqueda
     * @return bool True si existe al menos una entidad
     * @throws \RuntimeException Si la verificación falla
     */
    public function exists(array $criteria): bool
    {
        try {
            return $this->count($criteria) > 0;
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to check existence for '{$this->entityClass}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    /**
     * Busca entidades con paginación segura
     * 
     * @param int $page Número de página (empezando en 1)
     * @param int $pageSize Tamaño de página
     * @param array $criteria Criterios de búsqueda
     * @param array|null $orderBy Ordenamiento
     * @return array Array con 'data' y 'total'
     * @throws \InvalidArgumentException Si los parámetros son inválidos
     * @throws \RuntimeException Si la consulta falla
     */
    public function findPaginated(int $page, int $pageSize, array $criteria = [], ?array $orderBy = null): array
    {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page number must be greater than 0');
        }
        
        if ($pageSize < 1 || $pageSize > 1000) {
            throw new \InvalidArgumentException('Page size must be between 1 and 1000');
        }
        
        try {
            $offset = ($page - 1) * $pageSize;
            $data = $this->findBy($criteria, $orderBy, $pageSize, $offset);
            $total = $this->count($criteria);
            
            return [
                'data' => $data,
                'total' => $total,
                'page' => $page,
                'pageSize' => $pageSize,
                'totalPages' => (int) ceil($total / $pageSize)
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to execute paginated query for '{$this->entityClass}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    /**
     * Busca entidades por múltiples IDs
     * 
     * @param array $ids Array de identificadores
     * @return array Array de entidades encontradas
     * @throws \InvalidArgumentException Si el array de IDs está vacío
     * @throws \RuntimeException Si la búsqueda falla
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            throw new \InvalidArgumentException('IDs array cannot be empty');
        }
        
        try {
            $entities = [];
            foreach ($ids as $id) {
                $entity = $this->find($id);
                if ($entity !== null) {
                    $entities[] = $entity;
                }
            }
            return $entities;
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to find entities by IDs for '{$this->entityClass}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    /**
     * Ejecuta una consulta personalizada con manejo de errores
     * 
     * @param string $oql Consulta OQL
     * @param array $parameters Parámetros de la consulta
     * @return array Resultados de la consulta
     * @throws \InvalidArgumentException Si la consulta está vacía
     * @throws \RuntimeException Si la ejecución falla
     */
    public function executeQuery(string $oql, array $parameters = []): array
    {
        if (empty(trim($oql))) {
            throw new \InvalidArgumentException('OQL query cannot be empty');
        }
        
        try {
            $query = $this->createQuery($oql);
            
            foreach ($parameters as $name => $value) {
                $query->setParameter($name, $value);
            }
            
            return $query->getResult();
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to execute custom query for '{$this->entityClass}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    /**
     * Obtiene estadísticas básicas del repositorio
     * 
     * @return array Estadísticas de la entidad
     * @throws \RuntimeException Si falla la obtención de estadísticas
     */
    public function getStatistics(): array
    {
        try {
            $total = $this->count();
            
            return [
                'entityClass' => $this->entityClass,
                'tableName' => $this->metadata->getFullTableName(),
                'totalRecords' => $total,
                'identifierFields' => $this->metadata->getIdentifiers(),
                'fieldCount' => count($this->metadata->getFields()),
                'associationCount' => count($this->metadata->getAssociations())
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to get statistics for '{$this->entityClass}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    /**
     * Busca entidades con filtros avanzados
     * 
     * @param array $filters Filtros complejos
     * @param array $options Opciones adicionales
     * @return array Entidades filtradas
     * @throws \RuntimeException Si el filtrado falla
     */
    public function findWithFilters(array $filters, array $options = []): array
    {
        try {
            $criteria = $this->buildCriteriaFromFilters($filters);
            $orderBy = $options['orderBy'] ?? null;
            $limit = $options['limit'] ?? null;
            $offset = $options['offset'] ?? null;
            
            return $this->findBy($criteria, $orderBy, $limit, $offset);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to apply filters for '{$this->entityClass}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    /**
     * Construye criterios desde filtros complejos
     * 
     * @param array $filters Filtros a procesar
     * @return array Criterios construidos
     * @throws \InvalidArgumentException Si los filtros son inválidos
     */
    private function buildCriteriaFromFilters(array $filters): array
    {
        $criteria = [];
        
        foreach ($filters as $field => $value) {
            if (!$this->metadata->getField($field)) {
                throw new \InvalidArgumentException("Unknown field '{$field}' for entity '{$this->entityClass}'");
            }
            
            if (is_array($value)) {
                // Manejar filtros complejos como IN, BETWEEN, etc.
                if (isset($value['operator'])) {
                    switch ($value['operator']) {
                        case 'in':
                            if (empty($value['values'])) {
                                throw new \InvalidArgumentException('IN operator requires non-empty values array');
                            }
                            // Por simplicidad, usar el primer valor
                            $criteria[$field] = $value['values'][0];
                            break;
                        case 'like':
                            if (empty($value['pattern'])) {
                                throw new \InvalidArgumentException('LIKE operator requires pattern');
                            }
                            $criteria[$field] = $value['pattern'];
                            break;
                        default:
                            throw new \InvalidArgumentException("Unsupported operator '{$value['operator']}'");
                    }
                } else {
                    $criteria[$field] = $value;
                }
            } else {
                $criteria[$field] = $value;
            }
        }
        
        return $criteria;
    }
    
    /**
     * Valida una entidad antes de operaciones
     * 
     * @param object $entity Entidad a validar
     * @return bool True si es válida
     * @throws \InvalidArgumentException Si la entidad es inválida
     * @throws \RuntimeException Si la validación falla
     */
    public function validateEntity(object $entity): bool
    {
        if (!is_a($entity, $this->entityClass)) {
            throw new \InvalidArgumentException(
                "Entity must be instance of '{$this->entityClass}', got '" . get_class($entity) . "'"
            );
        }
        
        try {
            // Validar campos requeridos
            foreach ($this->metadata->getFields() as $fieldName => $fieldMapping) {
                if (!($fieldMapping['nullable'] ?? true)) {
                    $value = $this->getPropertyValue($entity, $fieldName);
                    if ($value === null) {
                        throw new \InvalidArgumentException("Required field '{$fieldName}' cannot be null");
                    }
                }
            }
            
            return true;
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to validate entity '{$this->entityClass}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    /**
     * Obtiene el valor de una propiedad de entidad
     * 
     * @param object $entity Entidad
     * @param string $property Nombre de la propiedad
     * @return mixed Valor de la propiedad
     * @throws \RuntimeException Si no se puede acceder a la propiedad
     */
    private function getPropertyValue(object $entity, string $property): mixed
    {
        try {
            $reflection = new \ReflectionProperty($entity, $property);
            $reflection->setAccessible(true);
            return $reflection->getValue($entity);
        } catch (\ReflectionException $e) {
            throw new \RuntimeException(
                "Cannot access property '{$property}' in entity: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    /**
     * Busca entidades con agregaciones
     * 
     * @param string $aggregateFunction Función de agregación (COUNT, SUM, AVG, etc.)
     * @param string $field Campo a agregar
     * @param array $criteria Criterios de filtrado
     * @return mixed Resultado de la agregación
     * @throws \InvalidArgumentException Si los parámetros son inválidos
     * @throws \RuntimeException Si la agregación falla
     */
    public function aggregate(string $aggregateFunction, string $field, array $criteria = []): mixed
    {
        $allowedFunctions = ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX'];
        
        if (!in_array(strtoupper($aggregateFunction), $allowedFunctions, true)) {
            throw new \InvalidArgumentException(
                "Invalid aggregate function '{$aggregateFunction}'. Allowed: " . implode(', ', $allowedFunctions)
            );
        }
        
        if ($field !== '*' && !$this->metadata->getField($field)) {
            throw new \InvalidArgumentException("Unknown field '{$field}' for entity '{$this->entityClass}'");
        }
        
        try {
            $fieldMapping = $field === '*' ? ['columnName' => '*'] : $this->metadata->getField($field);
            $columnName = $fieldMapping['columnName'];
            
            $sql = sprintf(
                'SELECT %s(%s) as result FROM %s',
                strtoupper($aggregateFunction),
                $columnName,
                $this->metadata->getFullTableName()
            );
            
            $params = [];
            if (!empty($criteria)) {
                $whereParts = [];
                foreach ($criteria as $criteriaField => $value) {
                    $criteriaMapping = $this->metadata->getField($criteriaField);
                    if ($criteriaMapping) {
                        $whereParts[] = $criteriaMapping['columnName'] . ' = ?';
                        $params[] = $value;
                    }
                }
                
                if (!empty($whereParts)) {
                    $sql .= ' WHERE ' . implode(' AND ', $whereParts);
                }
            }
            
            $stmt = $this->entityManager->getConnection()->executeQuery($sql, $params);
            $result = $stmt->fetch();
            
            return $result['result'] ?? null;
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to execute aggregate function '{$aggregateFunction}' for '{$this->entityClass}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    /**
     * Busca entidades con búsqueda de texto completo
     * 
     * @param string $searchTerm Término de búsqueda
     * @param array $fields Campos donde buscar
     * @param array $options Opciones de búsqueda
     * @return array Entidades encontradas
     * @throws \InvalidArgumentException Si los parámetros son inválidos
     * @throws \RuntimeException Si la búsqueda falla
     */
    public function fullTextSearch(string $searchTerm, array $fields, array $options = []): array
    {
        if (empty(trim($searchTerm))) {
            throw new \InvalidArgumentException('Search term cannot be empty');
        }
        
        if (empty($fields)) {
            throw new \InvalidArgumentException('Search fields cannot be empty');
        }
        
        // Validar que todos los campos existen
        foreach ($fields as $field) {
            if (!$this->metadata->getField($field)) {
                throw new \InvalidArgumentException("Unknown field '{$field}' for entity '{$this->entityClass}'");
            }
        }
        
        try {
            $searchPattern = '%' . $searchTerm . '%';
            $whereParts = [];
            $params = [];
            
            foreach ($fields as $field) {
                $fieldMapping = $this->metadata->getField($field);
                $whereParts[] = $fieldMapping['columnName'] . ' LIKE ?';
                $params[] = $searchPattern;
            }
            
            $sql = sprintf(
                'SELECT * FROM %s WHERE %s',
                $this->metadata->getFullTableName(),
                implode(' OR ', $whereParts)
            );
            
            // Agregar ordenamiento si se especifica
            if (isset($options['orderBy'])) {
                $orderParts = [];
                foreach ($options['orderBy'] as $field => $direction) {
                    $fieldMapping = $this->metadata->getField($field);
                    if ($fieldMapping) {
                        $orderParts[] = $fieldMapping['columnName'] . ' ' . strtoupper($direction);
                    }
                }
                
                if (!empty($orderParts)) {
                    $sql .= ' ORDER BY ' . implode(', ', $orderParts);
                }
            }
            
            // Agregar límite si se especifica
            if (isset($options['limit']) && $options['limit'] > 0) {
                $sql .= ' TOP ' . (int) $options['limit'];
            }
            
            $stmt = $this->entityManager->getConnection()->executeQuery($sql, $params);
            
            $entities = [];
            while ($data = $stmt->fetch()) {
                $entities[] = $this->hydrate($data);
            }
            
            return $entities;
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to execute full text search for '{$this->entityClass}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    /**
     * Busca entidades con rangos de fechas
     * 
     * @param string $dateField Campo de fecha
     * @param \DateTime $startDate Fecha de inicio
     * @param \DateTime $endDate Fecha de fin
     * @param array $additionalCriteria Criterios adicionales
     * @return array Entidades en el rango de fechas
     * @throws \InvalidArgumentException Si los parámetros son inválidos
     * @throws \RuntimeException Si la consulta falla
     */
    public function findByDateRange(string $dateField, \DateTime $startDate, \DateTime $endDate, array $additionalCriteria = []): array
    {
        if (!$this->metadata->getField($dateField)) {
            throw new \InvalidArgumentException("Unknown date field '{$dateField}' for entity '{$this->entityClass}'");
        }
        
        if ($startDate > $endDate) {
            throw new \InvalidArgumentException('Start date cannot be greater than end date');
        }
        
        try {
            $fieldMapping = $this->metadata->getField($dateField);
            $columnName = $fieldMapping['columnName'];
            
            $whereParts = [
                $columnName . ' >= ?',
                $columnName . ' <= ?'
            ];
            $params = [
                $startDate->format('Y-m-d H:i:s'),
                $endDate->format('Y-m-d H:i:s')
            ];
            
            // Agregar criterios adicionales
            foreach ($additionalCriteria as $field => $value) {
                $criteriaMapping = $this->metadata->getField($field);
                if ($criteriaMapping) {
                    $whereParts[] = $criteriaMapping['columnName'] . ' = ?';
                    $params[] = $value;
                }
            }
            
            $sql = sprintf(
                'SELECT * FROM %s WHERE %s ORDER BY %s ASC',
                $this->metadata->getFullTableName(),
                implode(' AND ', $whereParts),
                $columnName
            );
            
            $stmt = $this->entityManager->getConnection()->executeQuery($sql, $params);
            
            $entities = [];
            while ($data = $stmt->fetch()) {
                $entities[] = $this->hydrate($data);
            }
            
            return $entities;
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to find entities by date range for '{$this->entityClass}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    /**
     * Ejecuta una consulta SQL nativa con parámetros
     * 
     * @param string $sql Consulta SQL nativa
     * @param array $parameters Parámetros de la consulta
     * @param bool $hydrateResults Si hidratar los resultados como entidades
     * @return array Resultados de la consulta
     * @throws \InvalidArgumentException Si la consulta está vacía
     * @throws \RuntimeException Si la ejecución falla
     */
    public function executeNativeQuery(string $sql, array $parameters = [], bool $hydrateResults = true): array
    {
        if (empty(trim($sql))) {
            throw new \InvalidArgumentException('SQL query cannot be empty');
        }
        
        // Validación básica de seguridad
        $dangerousKeywords = ['DROP', 'DELETE', 'TRUNCATE', 'ALTER', 'CREATE', 'INSERT', 'UPDATE'];
        $upperSql = strtoupper($sql);
        
        foreach ($dangerousKeywords as $keyword) {
            if (strpos($upperSql, $keyword) !== false) {
                throw new \InvalidArgumentException("Dangerous SQL keyword '{$keyword}' detected in query");
            }
        }
        
        try {
            $stmt = $this->entityManager->getConnection()->executeQuery($sql, array_values($parameters));
            
            $results = [];
            while ($data = $stmt->fetch()) {
                if ($hydrateResults) {
                    try {
                        $results[] = $this->hydrate($data);
                    } catch (\Throwable $hydrateError) {
                        // Si falla la hidratación, devolver datos raw
                        $results[] = $data;
                    }
                } else {
                    $results[] = $data;
                }
            }
            
            return $results;
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to execute native query for '{$this->entityClass}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    /**
     * Obtiene metadatos extendidos del repositorio
     * 
     * @return array Metadatos completos
     * @throws \RuntimeException Si falla la obtención de metadatos
     */
    public function getExtendedMetadata(): array
    {
        try {
            $basicStats = $this->getStatistics();
            
            // Obtener información adicional de campos
            $fieldDetails = [];
            foreach ($this->metadata->getFields() as $fieldName => $fieldMapping) {
                $fieldDetails[$fieldName] = [
                    'columnName' => $fieldMapping['columnName'],
                    'type' => $fieldMapping['type'],
                    'length' => $fieldMapping['length'] ?? null,
                    'nullable' => $fieldMapping['nullable'] ?? true,
                    'default' => $fieldMapping['default'] ?? null
                ];
            }
            
            // Obtener información de asociaciones
            $associationDetails = [];
            foreach ($this->metadata->getAssociations() as $assocName => $assocMapping) {
                $associationDetails[$assocName] = [
                    'type' => $assocMapping['type'],
                    'targetEntity' => $assocMapping['targetEntity'],
                    'mappedBy' => $assocMapping['mappedBy'] ?? null,
                    'inversedBy' => $assocMapping['inversedBy'] ?? null,
                    'cascade' => $assocMapping['cascade'] ?? false,
                    'fetch' => $assocMapping['fetch'] ?? 'LAZY'
                ];
            }
            
            return array_merge($basicStats, [
                'fields' => $fieldDetails,
                'associations' => $associationDetails,
                'schema' => $this->metadata->getSchema(),
                'hasCompositeId' => $this->metadata->hasCompositeId(),
                'idGeneratorType' => $this->metadata->getIdGeneratorType()
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to get extended metadata for '{$this->entityClass}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    /**
     * Limpia el cache del repositorio
     * 
     * @return bool True si se limpió correctamente
     * @throws \RuntimeException Si falla la limpieza
     */
    public function clearCache(): bool
    {
        try {
            // Limpiar cache de metadatos del EntityManager
            $this->entityManager->clearMetadataCache();
            
            // Limpiar mapa de identidad
            $this->entityManager->clear();
            
            return true;
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to clear cache for '{$this->entityClass}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }
}