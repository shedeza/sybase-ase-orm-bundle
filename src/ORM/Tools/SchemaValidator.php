<?php

namespace Shedeza\SybaseAseOrmBundle\ORM\Tools;

use Shedeza\SybaseAseOrmBundle\ORM\EntityManager;

class SchemaValidator
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function validateEntity(string $entityClass): array
    {
        $errors = [];
        
        try {
            $metadata = $this->entityManager->getClassMetadata($entityClass);
            
            if (empty($metadata->getTableName())) {
                $errors[] = "Entity {$entityClass} has no table name defined";
            }
            
            if (empty($metadata->getIdentifiers())) {
                $errors[] = "Entity {$entityClass} has no identifier fields";
            }
            
            if (empty($metadata->getFields())) {
                $errors[] = "Entity {$entityClass} has no mapped fields";
            }
            
            foreach ($metadata->getAssociations() as $field => $association) {
                if (!class_exists($association['targetEntity'])) {
                    $errors[] = "Association {$field} targets non-existent class {$association['targetEntity']}";
                }
            }
            
        } catch (\Exception $e) {
            $errors[] = "Failed to load metadata for {$entityClass}: " . $e->getMessage();
        }
        
        return $errors;
    }
}