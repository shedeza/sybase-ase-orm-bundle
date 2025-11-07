<?php

namespace Shedeza\SybaseAseOrmBundle\Tests\ORM\Mapping;

use PHPUnit\Framework\TestCase;
use Shedeza\SybaseAseOrmBundle\ORM\Mapping\EntityMetadata;

class EntityMetadataTest extends TestCase
{
    public function testConstructor(): void
    {
        $metadata = new EntityMetadata('TestClass');
        $this->assertEquals('TestClass', $metadata->getClassName());
    }
    
    public function testEmptyClassNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Class name cannot be empty');
        
        new EntityMetadata('');
    }
    
    public function testSetTableName(): void
    {
        $metadata = new EntityMetadata('TestClass');
        $metadata->setTableName('test_table');
        
        $this->assertEquals('test_table', $metadata->getTableName());
    }
    
    public function testEmptyTableNameThrowsException(): void
    {
        $metadata = new EntityMetadata('TestClass');
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Table name cannot be empty');
        
        $metadata->setTableName('');
    }
    
    public function testAddField(): void
    {
        $metadata = new EntityMetadata('TestClass');
        $fieldMapping = ['columnName' => 'test_column', 'type' => 'string'];
        
        $metadata->addField('testField', $fieldMapping);
        
        $this->assertEquals($fieldMapping, $metadata->getField('testField'));
        $this->assertArrayHasKey('testField', $metadata->getFields());
    }
}