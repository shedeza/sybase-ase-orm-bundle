<?php

namespace Shedeza\SybaseAseOrmBundle\ORM\Query;

use Shedeza\SybaseAseOrmBundle\ORM\EntityManager;

/**
 * Parser de consultas OQL (Object Query Language)
 * 
 * Convierte consultas OQL similares a DQL en SQL nativo para Sybase ASE.
 * Soporta JOINs, WHERE, ORDER BY y sintaxis WITH estilo Doctrine.
 */
class OQLParser
{
    /** @var EntityManager Administrador de entidades */
    private EntityManager $entityManager;
    
    /** @var array Mapa de alias a nombres de entidad */
    private array $aliases = [];
    
    /** @var array Información de JOINs parseados */
    private array $joins = [];
    
    /** @var array Cache estático de consultas parseadas */
    private static array $parseCache = [];

    /**
     * Constructor del parser OQL
     * 
     * @param EntityManager $entityManager Administrador de entidades
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Parsea una consulta OQL y retorna un objeto Query
     * 
     * Utiliza cache para mejorar el rendimiento de consultas repetidas.
     * 
     * @param string $oql Consulta OQL a parsear
     * @return Query Objeto de consulta ejecutable
     * @throws \InvalidArgumentException Si la consulta está vacía
     */
    public function parse(string $oql): Query
    {
        if (empty($oql)) {
            throw new \InvalidArgumentException('OQL query cannot be empty');
        }
        
        $oql = trim($oql);
        $cacheKey = hash('sha256', $oql);
        
        // Verificar cache para evitar reparsear consultas idénticas
        if (!isset(self::$parseCache[$cacheKey])) {
            $this->aliases = [];
            $this->joins = [];
            
            // Parsear consulta OQL compleja con JOINs
            self::$parseCache[$cacheKey] = $this->parseOQL($oql);
        }
        
        $parsed = self::$parseCache[$cacheKey];
        $this->aliases = $parsed['aliases'] ?? [];
        
        return new Query(
            $this->entityManager,
            $parsed['select'],
            $parsed['from'],
            $parsed['alias'],
            $parsed['joins'],
            $parsed['where'],
            $parsed['orderBy'],
            $this->aliases
        );
    }
    
    /**
     * Parsea la consulta OQL completa
     * 
     * @param string $oql Consulta OQL normalizada
     * @return array Componentes parseados de la consulta
     * @throws \InvalidArgumentException Si la sintaxis OQL es inválida
     */
    private function parseOQL(string $oql): array
    {
        // Eliminar espacios extra y normalizar
        $oql = preg_replace('/\s+/', ' ', $oql);
        
        // Parsear cláusula SELECT
        if (!preg_match('/^SELECT\s+(.+?)\s+FROM\s+(.+)$/i', $oql, $matches)) {
            throw new \InvalidArgumentException('Invalid OQL syntax');
        }
        
        $select = trim($matches[1]);
        $fromClause = trim($matches[2]);
        
        // Parsear cláusula FROM con posibles JOINs
        $fromParts = $this->parseFromClause($fromClause);
        
        return [
            'select' => $select,
            'from' => $fromParts['entity'],
            'alias' => $fromParts['alias'],
            'joins' => $fromParts['joins'],
            'where' => $fromParts['where'],
            'orderBy' => $fromParts['orderBy'],
            'aliases' => $this->aliases
        ];
    }
    
    /**
     * Parsea la cláusula FROM incluyendo JOINs, WHERE y ORDER BY
     * 
     * @param string $fromClause Cláusula FROM completa
     * @return array Componentes parseados (entidad, alias, joins, where, orderBy)
     */
    private function parseFromClause(string $fromClause): array
    {
        $result = [
            'entity' => null,
            'alias' => null,
            'joins' => [],
            'where' => null,
            'orderBy' => null
        ];
        
        // Dividir por palabras clave principales
        $parts = preg_split('/(\s+(?:LEFT\s+JOIN|INNER\s+JOIN|JOIN|WHERE|ORDER\s+BY)\s+)/i', $fromClause, -1, PREG_SPLIT_DELIM_CAPTURE);
        
        // Parsear entidad principal del FROM
        $mainFrom = trim($parts[0]);
        if (preg_match('/(\w+)(?:\s+(\w+))?/', $mainFrom, $matches)) {
            $result['entity'] = $matches[1];
            $result['alias'] = $matches[2] ?? null;
            
            // Registrar alias si existe
            if ($result['alias']) {
                $this->aliases[$result['alias']] = $result['entity'];
            }
        }
        
        // Parsear JOINs y otras cláusulas
        $partsCount = count($parts);
        for ($i = 1; $i < $partsCount; $i += 2) {
            $keyword = trim($parts[$i]);
            $content = trim($parts[$i + 1] ?? '');
            
            if (stripos($keyword, 'JOIN') !== false) {
                $result['joins'][] = $this->parseJoin($keyword, $content);
            } elseif (stripos($keyword, 'WHERE') !== false) {
                $result['where'] = $this->extractWhereClause($content);
            } elseif (stripos($keyword, 'ORDER BY') !== false) {
                $result['orderBy'] = $this->extractOrderByClause($content);
            }
        }
        
        return $result;
    }
    
    /**
     * Parsea una cláusula JOIN
     */
    private function parseJoin(string $joinType, string $joinClause): array
    {
        // Intentar sintaxis WITH (Doctrine) primero
        $withJoin = $this->tryParseWithJoin($joinClause);
        if ($withJoin !== null) {
            return $this->buildJoinResult($joinType, $withJoin);
        }
        
        // Intentar sintaxis ON (SQL tradicional)
        $onJoin = $this->tryParseOnJoin($joinClause);
        if ($onJoin !== null) {
            return $this->buildSimpleJoinResult($joinType, $onJoin);
        }
        
        throw new \InvalidArgumentException('Invalid JOIN syntax: ' . $joinClause);
    }
    
    /**
     * Intenta parsear JOIN con sintaxis WITH
     */
    private function tryParseWithJoin(string $joinClause): ?array
    {
        $pattern = '/(\w+)\.(\w+)\s+(\w+)(?:\s+WITH\s+(.+?))?(?=\s+(?:LEFT\s+JOIN|INNER\s+JOIN|JOIN|WHERE|ORDER\s+BY)|$)/i';
        
        if (!preg_match($pattern, $joinClause, $matches)) {
            return null;
        }
        
        return [
            'sourceAlias' => $matches[1],
            'association' => $matches[2],
            'targetAlias' => $matches[3],
            'withCondition' => isset($matches[4]) ? trim($matches[4]) : null
        ];
    }
    
    /**
     * Intenta parsear JOIN con sintaxis ON
     */
    private function tryParseOnJoin(string $joinClause): ?array
    {
        $pattern = '/(\w+)\s+(\w+)\s+ON\s+(.+?)(?=\s+(?:LEFT\s+JOIN|INNER\s+JOIN|JOIN|WHERE|ORDER\s+BY)|$)/i';
        
        if (!preg_match($pattern, $joinClause, $matches)) {
            return null;
        }
        
        return [
            'entity' => $matches[1],
            'alias' => $matches[2],
            'condition' => trim($matches[3])
        ];
    }
    
    /**
     * Construye resultado de JOIN con asociación
     */
    private function buildJoinResult(string $joinType, array $joinData): array
    {
        $sourceAlias = $joinData['sourceAlias'];
        $association = $joinData['association'];
        $targetAlias = $joinData['targetAlias'];
        $withCondition = $joinData['withCondition'];
        
        if (!isset($this->aliases[$sourceAlias])) {
            throw new \InvalidArgumentException("Unknown alias '{$sourceAlias}' in JOIN");
        }
        
        $sourceEntity = $this->aliases[$sourceAlias];
        $targetEntity = $this->resolveAssociationTarget($sourceEntity, $association);
        $joinCondition = $this->buildAssociationCondition($sourceEntity, $association, $sourceAlias, $targetAlias);
        
        if ($withCondition) {
            $joinCondition .= ' AND (' . $withCondition . ')';
        }
        
        $this->aliases[$targetAlias] = $targetEntity;
        
        return [
            'type' => trim($joinType),
            'entity' => $targetEntity,
            'alias' => $targetAlias,
            'condition' => $joinCondition,
            'association' => $association,
            'sourceAlias' => $sourceAlias
        ];
    }
    
    /**
     * Construye resultado de JOIN simple
     */
    private function buildSimpleJoinResult(string $joinType, array $joinData): array
    {
        $this->aliases[$joinData['alias']] = $joinData['entity'];
        
        return [
            'type' => trim($joinType),
            'entity' => $joinData['entity'],
            'alias' => $joinData['alias'],
            'condition' => $joinData['condition']
        ];
    }
    
    /**
     * Resuelve la entidad destino de una asociación
     * 
     * @param string $sourceEntity Entidad origen
     * @param string $association Nombre de la asociación
     * @return string Clase de la entidad destino
     * @throws \InvalidArgumentException Si la entidad o asociación no existe
     */
    private function resolveAssociationTarget(string $sourceEntity, string $association): string
    {
        if (!class_exists($sourceEntity)) {
            throw new \InvalidArgumentException("Entity class '{$sourceEntity}' does not exist");
        }
        
        $metadata = $this->entityManager->getClassMetadata($sourceEntity);
        $associations = $metadata->getAssociations();
        
        if (!isset($associations[$association])) {
            throw new \InvalidArgumentException("Association '{$association}' not found in entity '{$sourceEntity}'");
        }
        
        return $associations[$association]['targetEntity'];
    }
    
    /**
     * Construye la condición de JOIN para una asociación
     */
    private function buildAssociationCondition(string $sourceEntity, string $association, string $sourceAlias, string $targetAlias): string
    {
        $associationMapping = $this->getAssociationMapping($sourceEntity, $association);
        
        return match ($associationMapping['type']) {
            'manyToOne' => $this->buildManyToOneCondition($associationMapping, $sourceAlias, $targetAlias),
            'oneToMany' => $this->buildOneToManyCondition($associationMapping, $sourceAlias, $targetAlias),
            default => throw new \InvalidArgumentException("Unsupported association type '{$associationMapping['type']}' for association '{$association}'")
        };
    }
    
    /**
     * Obtiene el mapeo de asociación validado
     */
    private function getAssociationMapping(string $sourceEntity, string $association): array
    {
        $metadata = $this->entityManager->getClassMetadata($sourceEntity);
        $associations = $metadata->getAssociations();
        
        if (!isset($associations[$association])) {
            throw new \InvalidArgumentException("Association '{$association}' not found in entity '{$sourceEntity}'");
        }
        
        return $associations[$association];
    }
    
    /**
     * Construye condición para relación ManyToOne
     */
    private function buildManyToOneCondition(array $associationMapping, string $sourceAlias, string $targetAlias): string
    {
        $joinColumn = $associationMapping['joinColumn'] ?? null;
        
        if (!$joinColumn) {
            throw new \InvalidArgumentException('ManyToOne association missing joinColumn configuration');
        }
        
        return $this->formatJoinCondition(
            $sourceAlias,
            $joinColumn['name'],
            $targetAlias,
            $joinColumn['referencedColumnName']
        );
    }
    
    /**
     * Construye condición para relación OneToMany
     */
    private function buildOneToManyCondition(array $associationMapping, string $sourceAlias, string $targetAlias): string
    {
        $mappedBy = $associationMapping['mappedBy'] ?? null;
        
        if (!$mappedBy) {
            throw new \InvalidArgumentException('OneToMany association missing mappedBy configuration');
        }
        
        $targetMetadata = $this->entityManager->getClassMetadata($associationMapping['targetEntity']);
        $targetAssociations = $targetMetadata->getAssociations();
        
        if (!isset($targetAssociations[$mappedBy])) {
            throw new \InvalidArgumentException("MappedBy association '{$mappedBy}' not found in target entity");
        }
        
        $targetAssociation = $targetAssociations[$mappedBy];
        $joinColumn = $targetAssociation['joinColumn'] ?? null;
        
        if (!$joinColumn) {
            throw new \InvalidArgumentException('Target association missing joinColumn configuration');
        }
        
        return $this->formatJoinCondition(
            $targetAlias,
            $joinColumn['name'],
            $sourceAlias,
            $joinColumn['referencedColumnName']
        );
    }
    
    /**
     * Formatea la condición de JOIN
     * 
     * @param string $leftAlias Alias de la tabla izquierda
     * @param string $leftColumn Columna de la tabla izquierda
     * @param string $rightAlias Alias de la tabla derecha
     * @param string $rightColumn Columna de la tabla derecha
     * @return string Condición de JOIN formateada
     */
    private function formatJoinCondition(string $leftAlias, string $leftColumn, string $rightAlias, string $rightColumn): string
    {
        return "{$leftAlias}.{$leftColumn} = {$rightAlias}.{$rightColumn}";
    }
    
    /**
     * Extrae la cláusula WHERE hasta ORDER BY
     */
    private function extractWhereClause(string $content): string
    {
        if (preg_match('/^(.+?)(?=\s+ORDER\s+BY|$)/i', $content, $matches)) {
            return trim($matches[1]);
        }
        return trim($content);
    }
    
    /**
     * Extrae la cláusula ORDER BY
     */
    private function extractOrderByClause(string $content): string
    {
        return trim($content);
    }
}

/**
 * Clase Query para ejecutar consultas OQL parseadas
 * 
 * Convierte la consulta OQL parseada en SQL nativo y ejecuta la consulta
 * contra la base de datos, hidratando los resultados en objetos entidad.
 */
class Query
{
    /** @var EntityManager Administrador de entidades */
    private EntityManager $entityManager;
    
    /** @var string Cláusula SELECT */
    private string $select;
    
    /** @var string Entidad principal del FROM */
    private string $from;
    
    /** @var string|null Alias de la entidad principal */
    private ?string $alias;
    
    /** @var array Información de JOINs */
    private array $joins;
    
    /** @var string|null Cláusula WHERE */
    private ?string $where;
    
    /** @var string|null Cláusula ORDER BY */
    private ?string $orderBy;
    
    /** @var array Mapa de alias a entidades */
    private array $aliases;
    
    /** @var array Parámetros de la consulta */
    private array $parameters = [];

    /**
     * Constructor de la consulta
     * 
     * @param EntityManager $entityManager Administrador de entidades
     * @param string $select Cláusula SELECT
     * @param string $from Entidad principal
     * @param string|null $alias Alias de la entidad principal
     * @param array $joins Información de JOINs
     * @param string|null $where Cláusula WHERE
     * @param string|null $orderBy Cláusula ORDER BY
     * @param array $aliases Mapa de alias
     */
    public function __construct(EntityManager $entityManager, string $select, string $from, ?string $alias, array $joins, ?string $where, ?string $orderBy, array $aliases)
    {
        $this->entityManager = $entityManager;
        $this->select = $select;
        $this->from = $from;
        $this->alias = $alias;
        $this->joins = $joins;
        $this->where = $where;
        $this->orderBy = $orderBy;
        $this->aliases = $aliases;
    }

    /**
     * Establece un parámetro de la consulta
     * 
     * @param string $name Nombre del parámetro
     * @param mixed $value Valor del parámetro
     * @return self Para encadenamiento de métodos
     * @throws \InvalidArgumentException Si el nombre está vacío
     */
    public function setParameter(string $name, mixed $value): self
    {
        if (empty($name)) {
            throw new \InvalidArgumentException('Parameter name cannot be empty');
        }
        
        $this->parameters[$name] = $value;
        return $this;
    }

    /**
     * Ejecuta la consulta y retorna todos los resultados
     * 
     * @return array Array de entidades hidratadas
     * @throws \InvalidArgumentException Si la clase de entidad no existe
     * @throws \RuntimeException Si la ejecución de la consulta falla
     */
    public function getResult(): array
    {
        if (!class_exists($this->from)) {
            throw new \InvalidArgumentException("Entity class '{$this->from}' does not exist");
        }
        
        $sql = $this->buildSQL();
        
        try {
            $stmt = $this->entityManager->getConnection()->executeQuery($sql, array_values($this->parameters));
            
            $entities = [];
            while ($data = $stmt->fetch()) {
                $entity = $this->hydrateEntity($this->from, $data);
                $entities[] = $entity;
            }
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to execute OQL query: ' . $e->getMessage(), 0, $e);
        }
        
        return $entities;
    }

    /**
     * Ejecuta la consulta y retorna un único resultado
     * 
     * @return object|null La entidad encontrada o null si no hay resultados
     * @throws \RuntimeException Si la consulta retorna más de un resultado
     */
    public function getSingleResult(): ?object
    {
        $results = $this->getResult();
        
        if (count($results) > 1) {
            throw new \RuntimeException('Query returned more than one result');
        }
        
        return $results[0] ?? null;
    }
    
    /**
     * Ejecuta la consulta y retorna un valor escalar único
     * 
     * Útil para consultas COUNT, SUM, etc.
     * 
     * @return mixed El valor escalar o null si no hay resultados
     * @throws \RuntimeException Si la consulta retorna más de un resultado
     */
    public function getSingleScalarResult(): mixed
    {
        $results = $this->getResult();
        
        if (count($results) > 1) {
            throw new \RuntimeException('Query returned more than one result');
        }
        
        if (empty($results)) {
            return null;
        }
        
        $result = $results[0];
        if (is_array($result)) {
            return reset($result);
        }
        
        return $result;
    }

    /**
     * Construye la consulta SQL nativa a partir de los componentes OQL
     * 
     * @return string Consulta SQL completa
     */
    private function buildSQL(): string
    {
        $mainMetadata = $this->entityManager->getClassMetadata($this->from);
        
        // Construir cláusula SELECT
        $sql = 'SELECT ';
        if ($this->select === '*' || strtolower($this->select) === strtolower($this->alias)) {
            $sql .= $this->buildSelectFields($mainMetadata, $this->alias);
        } else {
            $sql .= $this->translateSelect($this->select);
        }
        
        // Construir cláusula FROM
        $sql .= ' FROM ' . $mainMetadata->getFullTableName();
        if ($this->alias) {
            $sql .= ' ' . $this->alias;
        }
        
        // Construir cláusulas JOIN
        foreach ($this->joins as $join) {
            $sql .= ' ' . $this->buildJoinSQL($join);
        }
        
        // Construir cláusula WHERE
        if ($this->where) {
            $sql .= ' WHERE ' . $this->translateConditions($this->where);
        }
        
        // Construir cláusula ORDER BY
        if ($this->orderBy) {
            $sql .= ' ORDER BY ' . $this->translateOrderBy($this->orderBy);
        }
        
        return $sql;
    }

    /**
     * Construye la lista de campos SELECT para una entidad
     * 
     * @param EntityMetadata $metadata Metadatos de la entidad
     * @param string|null $alias Alias de la tabla
     * @return string Lista de campos separados por comas
     */
    private function buildSelectFields($metadata, $alias): string
    {
        $fields = [];
        $prefix = $alias ? $alias . '.' : '';
        
        foreach ($metadata->getFields() as $fieldName => $fieldMapping) {
            $fields[] = $prefix . $fieldMapping['columnName'];
        }
        
        return implode(', ', $fields);
    }
    
    /**
     * Traduce la cláusula SELECT de OQL a SQL
     * 
     * @param string $select Cláusula SELECT en OQL
     * @return string Cláusula SELECT en SQL
     */
    private function translateSelect(string $select): string
    {
        return $this->translateFieldReferences($select);
    }
    
    /**
     * Construye la cláusula JOIN en SQL
     * 
     * @param array $join Información del JOIN
     * @return string Cláusula JOIN en SQL
     * @throws \InvalidArgumentException Si la entidad del JOIN no existe
     */
    private function buildJoinSQL(array $join): string
    {
        $joinEntity = $join['entity'];
        $joinAlias = $join['alias'];
        $joinType = strtoupper($join['type']);
        $condition = $join['condition'];
        
        if (!class_exists($joinEntity)) {
            throw new \InvalidArgumentException("Join entity class '{$joinEntity}' does not exist");
        }
        
        $joinMetadata = $this->entityManager->getClassMetadata($joinEntity);
        $joinTable = $joinMetadata->getFullTableName();
        
        // Traducir condición (puede incluir condiciones WITH)
        $translatedCondition = $this->translateConditions($condition);
        
        return "{$joinType} {$joinTable} {$joinAlias} ON {$translatedCondition}";
    }
    
    /**
     * Traduce condiciones de OQL a SQL
     * 
     * @param string $conditions Condiciones en OQL
     * @return string Condiciones en SQL
     */
    private function translateConditions(string $conditions): string
    {
        $translated = $this->translateFieldReferences($conditions);
        
        // Reemplazar parámetros nombrados con ?
        $translated = preg_replace('/:(\w+)/', '?', $translated);
        
        return $translated;
    }
    
    private function translateWithCondition(string $condition): string
    {
        return $this->translateFieldReferences($condition);
    }
    
    /**
     * Traduce referencias de campos de OQL (alias.campo) a SQL (alias.columna)
     * 
     * @param string $text Texto con referencias de campos
     * @return string Texto con referencias traducidas
     */
    private function translateFieldReferences(string $text): string
    {
        // Traducir referencias alias.campo a alias.nombre_columna
        foreach ($this->aliases as $alias => $entityClass) {
            if (!class_exists($entityClass)) {
                continue;
            }
            
            $metadata = $this->entityManager->getClassMetadata($entityClass);
            
            foreach ($metadata->getFields() as $fieldName => $fieldMapping) {
                $pattern = '/\b' . preg_quote($alias . '.' . $fieldName, '/') . '\b/';
                $replacement = $alias . '.' . $fieldMapping['columnName'];
                $text = preg_replace($pattern, $replacement, $text);
            }
        }
        
        return $text;
    }

    private function translateOrderBy(string $orderBy): string
    {
        return $this->translateFieldReferences($orderBy);
    }
    
    /**
     * Hidrata una entidad con datos de la base de datos
     * 
     * @param string $entityClass Clase de la entidad
     * @param array $data Datos de la base de datos
     * @return object Entidad hidratada
     */
    private function hydrateEntity(string $entityClass, array $data): object
    {
        $entity = new $entityClass();
        $metadata = $this->entityManager->getClassMetadata($entityClass);
        
        foreach ($metadata->getFields() as $fieldName => $fieldMapping) {
            $columnName = $fieldMapping['columnName'];
            if (isset($data[$columnName])) {
                try {
                    $reflection = new \ReflectionProperty($entity, $fieldName);
                    $reflection->setAccessible(true);
                    $reflection->setValue($entity, $data[$columnName]);
                } catch (\ReflectionException $e) {
                    // Omitir propiedades que no existen
                    continue;
                }
            }
        }
        
        return $entity;
    }
}