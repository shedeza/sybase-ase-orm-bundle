<?php

namespace Shedeza\SybaseAseOrmBundle\ORM\Mapping;

/**
 * Atributo para mapear una entidad a una tabla de base de datos
 * 
 * Define el nombre de la tabla y opcionalmente el esquema donde
 * se almacenarán los datos de la entidad.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Table
{
    /**
     * Constructor del atributo Table
     * 
     * @param string $name Nombre de la tabla en la base de datos
     * @param string|null $schema Esquema de la base de datos (opcional)
     * @throws \InvalidArgumentException Si el nombre de tabla está vacío o es inválido
     */
    public function __construct(
        public string $name,
        public ?string $schema = null
    ) {
        if (empty(trim($this->name))) {
            throw new \InvalidArgumentException('Table name cannot be empty');
        }
        
        if (!$this->isValidTableName($this->name)) {
            throw new \InvalidArgumentException('Invalid table name format');
        }
        
        if ($this->schema !== null && !$this->isValidSchemaName($this->schema)) {
            throw new \InvalidArgumentException('Invalid schema name format');
        }
    }
    
    /**
     * Valida el formato del nombre de tabla
     * 
     * @param string $name Nombre a validar
     * @return bool True si es válido
     */
    private function isValidTableName(string $name): bool
    {
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name) === 1;
    }
    
    /**
     * Valida el formato del nombre de esquema
     * 
     * @param string $schema Esquema a validar
     * @return bool True si es válido
     */
    private function isValidSchemaName(string $schema): bool
    {
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $schema) === 1;
    }
}