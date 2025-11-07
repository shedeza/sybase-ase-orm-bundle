<?php

namespace Shedeza\SybaseAseOrmBundle\ORM\Mapping;

class EntityMetadata
{
    private string $className;
    private ?string $tableName = null;
    private ?string $schema = null;
    private array $fields = [];
    private array $associations = [];
    private array $identifiers = [];
    private string $idGeneratorType = 'IDENTITY';
    private ?string $repositoryClass = null;

    public function __construct(string $className)
    {
        if (empty($className)) {
            throw new \InvalidArgumentException('Class name cannot be empty');
        }
        
        $this->className = $className;
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function setTableName(string $tableName): void
    {
        if (empty($tableName)) {
            throw new \InvalidArgumentException('Table name cannot be empty');
        }
        
        $this->tableName = $tableName;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function setSchema(?string $schema): void
    {
        $this->schema = $schema;
    }

    public function getSchema(): ?string
    {
        return $this->schema;
    }

    public function addField(string $fieldName, array $mapping): void
    {
        if (empty($fieldName)) {
            throw new \InvalidArgumentException('Field name cannot be empty');
        }
        
        if (empty($mapping)) {
            throw new \InvalidArgumentException('Field mapping cannot be empty');
        }
        
        $this->fields[$fieldName] = $mapping;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function getField(string $fieldName): ?array
    {
        return $this->fields[$fieldName] ?? null;
    }

    public function addAssociation(string $fieldName, array $mapping): void
    {
        if (empty($fieldName)) {
            throw new \InvalidArgumentException('Association field name cannot be empty');
        }
        
        if (empty($mapping)) {
            throw new \InvalidArgumentException('Association mapping cannot be empty');
        }
        
        $this->associations[$fieldName] = $mapping;
    }

    public function getAssociations(): array
    {
        return $this->associations;
    }

    public function addIdentifier(string $identifier): void
    {
        if (empty($identifier)) {
            throw new \InvalidArgumentException('Identifier cannot be empty');
        }
        
        if (!in_array($identifier, $this->identifiers, true)) {
            $this->identifiers[] = $identifier;
        }
    }

    public function getIdentifiers(): array
    {
        return $this->identifiers;
    }
    
    public function getIdentifier(): ?string
    {
        return $this->identifiers[0] ?? null;
    }
    
    public function hasCompositeId(): bool
    {
        return count($this->identifiers) > 1;
    }

    public function setIdGeneratorType(string $type): void
    {
        $this->idGeneratorType = $type;
    }

    public function getIdGeneratorType(): string
    {
        return $this->idGeneratorType;
    }

    public function getFullTableName(): string
    {
        if (empty($this->tableName)) {
            throw new \RuntimeException('Table name is not set');
        }
        
        return $this->schema ? $this->schema . '.' . $this->tableName : $this->tableName;
    }
    
    public function setRepositoryClass(string $repositoryClass): void
    {
        if (empty($repositoryClass)) {
            throw new \InvalidArgumentException('Repository class cannot be empty');
        }
        
        $this->repositoryClass = $repositoryClass;
    }
    
    public function getRepositoryClass(): ?string
    {
        return $this->repositoryClass;
    }
}