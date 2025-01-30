<?php

declare(strict_types=1);

namespace R3H6\OpentelemetryAutoDoctrineDbal;

use WeakMap;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Connection;
use OpenTelemetry\SemConv\TraceAttributes;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use OpenTelemetry\SemConv\TraceAttributeValues;

class DoctrineDbalTracker
{
    /**
     * @var WeakMap<Connection, iterable<non-empty-string, bool|int|float|string|array|null>>
     */
    private WeakMap $connectionAttributes;


    /**
     * @var WeakMap<Result, iterable<non-empty-string, bool|int|float|string|array|null>>
     */
    private WeakMap $resultAttributes;

    public function __construct()
    {
        $this->connectionAttributes = new WeakMap();
        $this->resultAttributes = new WeakMap();
    }

    public function getAttributesByConnection(Connection $connection): iterable
    {
        if (!isset($this->connectionAttributes[$connection])) {
            $attributes = [];

            $attributes[TraceAttributes::DB_NAMESPACE] = $this->getDatabaseNamespace($connection);
            $attributes[TraceAttributes::DB_SYSTEM] = $this->getDatabaseSystem($connection);
            $attributes[TraceAttributes::SERVER_ADDRESS] = $this->getDatabaseHost($connection);

            $this->connectionAttributes[$connection] = $attributes;
        }
        return $this->connectionAttributes[$connection];
    }

    public function getAttributesByResult(Result $result, Connection $connection = null): iterable
    {
        if ($connection !== null) {
            $this->resultAttributes[$result] = $this->getAttributesByConnection($connection);
        }
        return $this->resultAttributes[$result] ?? [];
    }

    private function getDatabaseSystem(Connection $connection): string
    {
        $platform = $connection->getDatabasePlatform();
        if ($platform instanceof \Doctrine\DBAL\Platforms\MySqlPlatform) {
            return TraceAttributeValues::DB_SYSTEM_MYSQL;
        }
        if ($platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
            return TraceAttributeValues::DB_SYSTEM_SQLITE;
        }
        if ($platform instanceof \Doctrine\DBAL\Platforms\PostgreSqlPlatform) {
            return TraceAttributeValues::DB_SYSTEM_POSTGRESQL;
        }
        if ($platform instanceof \Doctrine\DBAL\Platforms\OraclePlatform) {
            return TraceAttributeValues::DB_SYSTEM_ORACLE;
        }
        if ($platform instanceof \Doctrine\DBAL\Platforms\SQLServerPlatform) {
            return TraceAttributeValues::DB_SYSTEM_MSSQL;
        }
        if ($platform instanceof MariaDBPlatform) {
            return TraceAttributeValues::DB_SYSTEM_MARIADB;
        }

        return 'other';
    }

    private function getDatabaseHost(Connection $connection): string
    {
        $params = $connection->getParams();
        return $params['host'] ?? 'undefined';
    }

    private function getDatabaseNamespace(Connection $connection): string
    {
        $params = $connection->getParams();
        return $params['dbname'] ?? 'unknown';
    }


}
