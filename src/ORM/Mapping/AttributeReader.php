<?php

namespace Shedeza\SybaseAseOrmBundle\ORM\Mapping;

use ReflectionClass;
use ReflectionProperty;

class AttributeReader
{
    public function getEntityMetadata(string $className): EntityMetadata
    {
        if (empty($className) || !class_exists($className)) {
            throw new \InvalidArgumentException("Class '{$className}' does not exist");
        }
        
        $reflection = new ReflectionClass($className);
        $metadata = new EntityMetadata($className);

        // Read Entity attribute
        $entityAttrs = $reflection->getAttributes(Entity::class);
        if (empty($entityAttrs)) {
            throw new \InvalidArgumentException("Class $className is not an entity");
        }
        
        // Read Repository attribute
        $repositoryAttrs = $reflection->getAttributes(Repository::class);
        if (!empty($repositoryAttrs)) {
            $repository = $repositoryAttrs[0]->newInstance();
            $metadata->setRepositoryClass($repository->repositoryClass);
        }

        // Read Table attribute
        $tableAttrs = $reflection->getAttributes(Table::class);
        if (!empty($tableAttrs)) {
            $table = $tableAttrs[0]->newInstance();
            $metadata->setTableName($table->name);
            $metadata->setSchema($table->schema);
        } else {
            $metadata->setTableName(strtolower($reflection->getShortName()));
        }

        // Read properties
        foreach ($reflection->getProperties() as $property) {
            $this->readPropertyMetadata($property, $metadata);
        }

        return $metadata;
    }

    private function readPropertyMetadata(ReflectionProperty $property, EntityMetadata $metadata): void
    {
        $columnAttrs = $property->getAttributes(Column::class);
        if (!empty($columnAttrs)) {
            $column = $columnAttrs[0]->newInstance();
            $fieldName = $property->getName();
            $columnName = $column->name ?? $fieldName;
            
            $fieldMapping = [
                'columnName' => $columnName,
                'type' => $column->type ?? 'string',
                'length' => $column->length,
                'nullable' => $column->nullable ?? false,
                'default' => $column->default
            ];
            
            $metadata->addField($fieldName, $fieldMapping);

            // Check if it's an ID
            $idAttrs = $property->getAttributes(Id::class);
            if (!empty($idAttrs)) {
                $metadata->addIdentifier($fieldName);
                
                $genAttrs = $property->getAttributes(GeneratedValue::class);
                if (!empty($genAttrs)) {
                    $gen = $genAttrs[0]->newInstance();
                    $metadata->setIdGeneratorType($gen->strategy);
                }
            }
        }

        // Read associations
        $this->readAssociations($property, $metadata);
    }

    private function readAssociations(ReflectionProperty $property, EntityMetadata $metadata): void
    {
        $fieldName = $property->getName();

        // OneToOne
        $oneToOneAttrs = $property->getAttributes(OneToOne::class);
        if (!empty($oneToOneAttrs)) {
            $assoc = $oneToOneAttrs[0]->newInstance();
            if (empty($assoc->targetEntity)) {
                throw new \InvalidArgumentException("OneToOne association must have a target entity");
            }
            
            $joinColumn = $this->readJoinColumn($property);
            
            $metadata->addAssociation($fieldName, [
                'type' => 'oneToOne',
                'targetEntity' => $assoc->targetEntity,
                'mappedBy' => $assoc->mappedBy,
                'inversedBy' => $assoc->inversedBy,
                'cascade' => $assoc->cascade ?? false,
                'fetch' => $assoc->fetchEager ? 'EAGER' : 'LAZY',
                'joinColumn' => $joinColumn
            ]);
        }

        // OneToMany
        $oneToManyAttrs = $property->getAttributes(OneToMany::class);
        if (!empty($oneToManyAttrs)) {
            $assoc = $oneToManyAttrs[0]->newInstance();
            if (empty($assoc->targetEntity)) {
                throw new \InvalidArgumentException("OneToMany association must have a target entity");
            }
            
            $metadata->addAssociation($fieldName, [
                'type' => 'oneToMany',
                'targetEntity' => $assoc->targetEntity,
                'mappedBy' => $assoc->mappedBy,
                'cascade' => $assoc->cascade ?? false,
                'fetch' => $assoc->fetchEager ? 'EAGER' : 'LAZY'
            ]);
        }

        // ManyToOne
        $manyToOneAttrs = $property->getAttributes(ManyToOne::class);
        if (!empty($manyToOneAttrs)) {
            $assoc = $manyToOneAttrs[0]->newInstance();
            if (empty($assoc->targetEntity)) {
                throw new \InvalidArgumentException("ManyToOne association must have a target entity");
            }
            
            $joinColumn = $this->readJoinColumn($property);
            
            $metadata->addAssociation($fieldName, [
                'type' => 'manyToOne',
                'targetEntity' => $assoc->targetEntity,
                'inversedBy' => $assoc->inversedBy,
                'cascade' => $assoc->cascade ?? false,
                'fetch' => $assoc->fetch,
                'joinColumn' => $joinColumn
            ]);
        }
        
        // ManyToMany
        $manyToManyAttrs = $property->getAttributes(ManyToMany::class);
        if (!empty($manyToManyAttrs)) {
            $assoc = $manyToManyAttrs[0]->newInstance();
            if (empty($assoc->targetEntity)) {
                throw new \InvalidArgumentException("ManyToMany association must have a target entity");
            }
            
            $joinTable = $this->readJoinTable($property);
            
            $metadata->addAssociation($fieldName, [
                'type' => 'manyToMany',
                'targetEntity' => $assoc->targetEntity,
                'mappedBy' => $assoc->mappedBy,
                'inversedBy' => $assoc->inversedBy,
                'cascade' => $assoc->cascade ?? false,
                'fetch' => $assoc->fetchEager ? 'EAGER' : 'LAZY',
                'joinTable' => $joinTable
            ]);
        }
    }
    
    private function readJoinColumn(ReflectionProperty $property): ?array
    {
        $joinAttrs = $property->getAttributes(JoinColumn::class);
        if (!empty($joinAttrs)) {
            $join = $joinAttrs[0]->newInstance();
            return [
                'name' => $join->name,
                'referencedColumnName' => $join->referencedColumnName,
                'nullable' => $join->nullable
            ];
        }
        return null;
    }
    
    private function readJoinTable(ReflectionProperty $property): ?array
    {
        $joinTableAttrs = $property->getAttributes(JoinTable::class);
        if (!empty($joinTableAttrs)) {
            $joinTable = $joinTableAttrs[0]->newInstance();
            return [
                'name' => $joinTable->name,
                'schema' => $joinTable->schema,
                'joinColumns' => $joinTable->joinColumns,
                'inverseJoinColumns' => $joinTable->inverseJoinColumns
            ];
        }
        return null;
    }
}