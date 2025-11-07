<?php

namespace Shedeza\SybaseAseOrmBundle\ORM\Mapping;

/**
 * Atributo para definir columnas de base de datos
 * 
 * Define las propiedades de una columna incluyendo tipo, longitud,
 * si permite nulos y valor por defecto.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Column
{
    /** @var array Tipos de datos válidos para columnas */
    public const VALID_TYPES = ['string', 'integer', 'int', 'float', 'decimal', 'boolean', 'bool', 'datetime', 'date', 'time', 'text', 'blob'];
    
    /**
     * Constructor de la columna con validación completa
     * 
     * @param string|null $name Nombre de la columna
     * @param string $type Tipo de datos de la columna
     * @param int|null $length Longitud de la columna
     * @param bool $nullable Si la columna permite valores nulos
     * @param mixed $default Valor por defecto
     * @throws \InvalidArgumentException Si algún parámetro es inválido
     */
    public function __construct(
        public ?string $name = null,
        public string $type = 'string',
        public ?int $length = null,
        public bool $nullable = false,
        public mixed $default = null
    ) {
        $this->validateName($name);
        $this->validateType($type);
        $this->validateLength($length);
        $this->validateDefault($default, $type, $nullable);
    }
    
    /**
     * Valida el nombre de la columna
     */
    private function validateName(?string $name): void
    {
        if ($name !== null && trim($name) === '') {
            throw new \InvalidArgumentException('Column name cannot be empty string');
        }
    }
    
    /**
     * Valida el tipo de la columna
     */
    private function validateType(string $type): void
    {
        $type = trim($type);
        
        if (empty($type)) {
            throw new \InvalidArgumentException('Column type cannot be empty');
        }
        
        $normalizedType = strtolower($type);
        $validTypes = array_map('strtolower', self::VALID_TYPES);
        
        if (!in_array($normalizedType, $validTypes, true)) {
            throw new \InvalidArgumentException(
                "Invalid column type '{$type}'. Valid types: " . implode(', ', self::VALID_TYPES)
            );
        }
        
        // Normalizar el tipo
        $this->type = $normalizedType;
    }
    
    /**
     * Valida la longitud de la columna
     */
    private function validateLength(?int $length): void
    {
        if ($length !== null) {
            if ($length <= 0) {
                throw new \InvalidArgumentException('Column length must be positive');
            }
            
            if ($length > 8000) {
                throw new \InvalidArgumentException('Column length cannot exceed 8000 characters');
            }
            
            // Verificar si el tipo soporta longitud
            $typesWithLength = ['string', 'text', 'decimal'];
            if (!in_array($this->type, $typesWithLength, true)) {
                throw new \InvalidArgumentException(
                    "Column type '{$this->type}' does not support length specification"
                );
            }
        }
    }
    
    /**
     * Valida el valor por defecto
     */
    private function validateDefault(mixed $default, string $type, bool $nullable): void
    {
        if ($default === null) {
            if (!$nullable) {
                // null es válido solo si la columna es nullable o no se especifica default
                return;
            }
            return;
        }
        
        // Validar compatibilidad del tipo
        $isValid = match ($type) {
            'string', 'text' => is_string($default),
            'integer', 'int' => is_int($default) || (is_string($default) && ctype_digit($default)),
            'float', 'decimal' => is_numeric($default),
            'boolean', 'bool' => is_bool($default) || in_array($default, [0, 1, '0', '1', 'true', 'false'], true),
            'datetime', 'date', 'time' => is_string($default) || $default instanceof \DateTime,
            'blob' => is_string($default),
            default => false
        };
        
        if (!$isValid) {
            throw new \InvalidArgumentException(
                "Default value type is incompatible with column type '{$type}'"
            );
        }
    }
}