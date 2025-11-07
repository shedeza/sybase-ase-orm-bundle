<?php

namespace Shedeza\SybaseAseOrmBundle\ORM\Mapping;

/**
 * Atributo para definir relaciones ManyToOne
 * 
 * Representa una relación donde muchas entidades pueden estar asociadas
 * a una sola entidad del tipo destino.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ManyToOne
{
    public const FETCH_LAZY = 'LAZY';
    public const FETCH_EAGER = 'EAGER';
    
    /**
     * Constructor de la relación ManyToOne
     * 
     * @param string $targetEntity Clase de la entidad destino
     * @param string|null $inversedBy Propiedad inversa en la entidad destino
     * @param bool $cascade Si aplicar operaciones en cascada
     * @param string $fetch Estrategia de carga (LAZY o EAGER)
     */
    public function __construct(
        public string $targetEntity,
        public ?string $inversedBy = null,
        public bool $cascade = false,
        public string $fetch = self::FETCH_LAZY
    ) {
        if (empty($this->targetEntity)) {
            throw new \InvalidArgumentException('Target entity cannot be empty');
        }
        
        if (!in_array($this->fetch, [self::FETCH_LAZY, self::FETCH_EAGER], true)) {
            throw new \InvalidArgumentException('Fetch strategy must be LAZY or EAGER');
        }
    }
    
    /**
     * Verifica si la carga es eager
     * 
     * @return bool True si la carga es eager
     */
    public function isEager(): bool
    {
        return $this->fetch === self::FETCH_EAGER;
    }
}