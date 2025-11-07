<?php

namespace Shedeza\SybaseAseOrmBundle\ORM\Proxy;

use Shedeza\SybaseAseOrmBundle\ORM\EntityManager;

/**
 * Fábrica de proxies para lazy loading
 */
class ProxyFactory
{
    private EntityManager $entityManager;
    
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }
    
    /**
     * Genera un proxy para una entidad
     */
    public function getProxy(string $className, mixed $identifier): object
    {
        return new class($this->entityManager, $className, $identifier) {
            private $em;
            private $class;
            private $id;
            private $loaded = false;
            private $target;
            
            public function __construct($em, $class, $id) {
                $this->em = $em;
                $this->class = $class;
                $this->id = $id;
            }
            
            private function load() {
                if (!$this->loaded) {
                    $this->target = $this->em->find($this->class, $this->id);
                    $this->loaded = true;
                }
            }
            
            public function __call($method, $args) {
                $this->load();
                return $this->target->$method(...$args);
            }
            
            public function __get($property) {
                $this->load();
                return $this->target->$property;
            }
            
            public function __set($property, $value) {
                $this->load();
                $this->target->$property = $value;
            }
        };
    }
}