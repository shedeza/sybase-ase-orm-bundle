<?php

namespace Shedeza\SybaseAseOrmBundle\Tests\DBAL;

use PHPUnit\Framework\TestCase;
use Shedeza\SybaseAseOrmBundle\DBAL\DatabaseUrlParser;

class DatabaseUrlParserTest extends TestCase
{
    public function testParseBasicUrl(): void
    {
        $url = 'sybase://sa:password@localhost:5000/testdb';
        $config = DatabaseUrlParser::parseUrl($url);
        
        $this->assertEquals('localhost', $config['host']);
        $this->assertEquals(5000, $config['port']);
        $this->assertEquals('testdb', $config['database']);
        $this->assertEquals('sa', $config['username']);
        $this->assertEquals('password', $config['password']);
        $this->assertEquals('utf8', $config['charset']);
    }
    
    public function testParseUrlWithCharset(): void
    {
        $url = 'sybase://user:pass@host:1433/db?charset=latin1';
        $config = DatabaseUrlParser::parseUrl($url);
        
        $this->assertEquals('latin1', $config['charset']);
    }
    
    public function testParseUrlWithDefaults(): void
    {
        $url = 'sybase://user:pass@host/db';
        $config = DatabaseUrlParser::parseUrl($url);
        
        $this->assertEquals(5000, $config['port']);
        $this->assertEquals('utf8', $config['charset']);
    }
    
    public function testEmptyUrlThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Database URL cannot be empty');
        
        DatabaseUrlParser::parseUrl('');
    }
    
    public function testInvalidUrlThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid database URL format');
        
        DatabaseUrlParser::parseUrl('invalid-url');
    }
    
    public function testMissingDatabaseThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Database name is required in URL');
        
        DatabaseUrlParser::parseUrl('sybase://user:pass@host:5000');
    }
}