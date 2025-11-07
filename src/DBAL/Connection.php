<?php

namespace Shedeza\SybaseAseOrmBundle\DBAL;

use PDO;
use PDOException;

class Connection
{
    private PDO $pdo;
    private array $config;
    private ?Logger\QueryLogger $logger = null;

    public function __construct(array $config, ?Logger\QueryLogger $logger = null)
    {
        $this->validateConfig($config);
        $this->config = $config;
        $this->logger = $logger;
        $this->connect();
    }

    private function connect(): void
    {
        $dsn = sprintf(
            'dblib:host=%s:%d;dbname=%s;charset=%s',
            $this->config['host'],
            $this->config['port'],
            $this->config['database'],
            $this->config['charset']
        );

        try {
            $this->pdo = new PDO($dsn, $this->config['username'], $this->config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('Connection failed: ' . $e->getMessage());
        }
    }

    public function executeQuery(string $sql, array $params = []): \PDOStatement
    {
        $startTime = microtime(true);
        
        try {
            $stmt = $this->pdo->prepare($sql);
            if (!$stmt) {
                throw new \RuntimeException('Failed to prepare statement');
            }
            $stmt->execute($params);
            
            if ($this->logger) {
                $executionTime = microtime(true) - $startTime;
                $this->logger->logQuery($sql, $params, $executionTime);
            }
            
            return $stmt;
        } catch (PDOException $e) {
            throw new \RuntimeException('Query execution failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function executeUpdate(string $sql, array $params = []): int
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            if (!$stmt) {
                throw new \RuntimeException('Failed to prepare statement');
            }
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new \RuntimeException('Update execution failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function lastInsertId(): string
    {
        try {
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            throw new \RuntimeException('Failed to get last insert ID: ' . $e->getMessage(), 0, $e);
        }
    }

    public function beginTransaction(): bool
    {
        try {
            return $this->pdo->beginTransaction();
        } catch (PDOException $e) {
            throw new \RuntimeException('Failed to begin transaction: ' . $e->getMessage(), 0, $e);
        }
    }

    public function commit(): bool
    {
        try {
            return $this->pdo->commit();
        } catch (PDOException $e) {
            throw new \RuntimeException('Failed to commit transaction: ' . $e->getMessage(), 0, $e);
        }
    }

    public function rollback(): bool
    {
        try {
            return $this->pdo->rollback();
        } catch (PDOException $e) {
            throw new \RuntimeException('Failed to rollback transaction: ' . $e->getMessage(), 0, $e);
        }
    }

    public function quote(string $value): string
    {
        return $this->pdo->quote($value);
    }

    private function validateConfig(array $config): void
    {
        $required = ['host', 'port', 'database', 'username', 'password'];
        foreach ($required as $key) {
            if (!isset($config[$key]) || empty($config[$key])) {
                throw new \InvalidArgumentException("Missing required config key: {$key}");
            }
        }
    }
}