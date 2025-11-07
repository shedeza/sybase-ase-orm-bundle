<?php

namespace Shedeza\SybaseAseOrmBundle\DBAL\Logger;

interface QueryLogger
{
    public function logQuery(string $sql, array $params = [], float $executionTime = 0.0): void;
}