<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager;

use Exception;
use Keboola\Db\Import\Snowflake\Connection as SnowflakeConnection;
use Keboola\SnowflakeDwhManager\Connection\Expr;
use RuntimeException;
use Throwable;
use function strtoupper;

class Connection extends SnowflakeConnection
{
    public const OBJECT_TYPE_DATABASE = 'DATABASE';
    public const OBJECT_TYPE_ROLE = 'ROLE';
    public const OBJECT_TYPE_SCHEMA = 'SCHEMA';
    public const OBJECT_TYPE_TABLE = 'TABLE';
    public const OBJECT_TYPE_VIEW = 'VIEW';
    public const OBJECT_TYPE_STAGE = 'STAGE';
    public const OBJECT_TYPE_USER = 'USER';
    public const OBJECT_TYPE_WAREHOUSE = 'WAREHOUSE';

    public function alterUser(string $userName, array $options): void
    {
        if (!count($options)) {
            throw new Exception('Nothing to alter without options');
        }

        $this->query(vsprintf(
            'ALTER USER IF EXISTS 
            %s
            SET 
            ' . $this->createQuotedOptionsStringFromArray($options),
            [
                $this->quoteIdentifier($userName),
            ]
        ));
    }

    /**
     * @param array $otherOptions
     * @return string
     */
    private function createQuotedOptionsStringFromArray(array $otherOptions): string
    {
        $otherOptionsString = '';
        foreach ($otherOptions as $option => $optionValue) {
            $quotedValue = $optionValue instanceof Expr ? $optionValue->getValue() : $this->quote($optionValue);
            $otherOptionsString .= strtoupper($option) . '=' . $quotedValue . \PHP_EOL;
        }
        return $otherOptionsString;
    }

    public function createRole(string $roleName): void
    {
        $this->query(vsprintf(
            'CREATE ROLE IF NOT EXISTS %s',
            [
                $this->quoteIdentifier($roleName),
            ]
        ));
    }

    public function createSchema(string $schema): void
    {
        $this->query(vsprintf(
            'CREATE SCHEMA IF NOT EXISTS %s WITH MANAGED ACCESS',
            [
                $this->quoteIdentifier($schema),
            ]
        ));
    }

    public function createUser(string $userName, string $password, array $otherOptions): void
    {
        $otherOptionsString = $this->createQuotedOptionsStringFromArray($otherOptions);

        $this->query(vsprintf(
            'CREATE USER IF NOT EXISTS 
            %s
            PASSWORD = %s
            ' . $otherOptionsString,
            [
                $this->quoteIdentifier($userName),
                $this->quote($password),
            ]
        ));
    }

    /**
     * @return mixed[]
     */
    public function describeUser(string $userName): array
    {
        $userFields = $this->fetchAll(vsprintf(
            'DESCRIBE USER %s',
            [
                $this->quoteIdentifier($userName),
            ]
        ));
        $result = [];
        foreach ($userFields as $userField) {
            $result[strtolower($userField['property'])] = $userField['value'];
        }
        return $result;
    }

    public function fetchAll(string $sql, array $bind = []): array
    {
        try {
            return parent::fetchAll($sql, $bind);
        } catch (Throwable $e) {
            throw new RuntimeException(sprintf('Error "%s" while executing query "%s"', $e->getMessage(), $sql));
        }
    }

    public function fetchRoles(?string $roleLike = null): array
    {
        $sql = 'SHOW ROLES';
        $args = [];
        if ($roleLike !== null) {
            $sql .= ' LIKE %s';
            $args[] = $this->quote($roleLike);
        }

        return $this->fetchAll(vsprintf(
            $sql,
            $args
        ));
    }

    public function fetchSchemasLike(string $schemaName): array
    {
        return $this->fetchAll(vsprintf(
            'SHOW SCHEMAS LIKE %s',
            [
                $this->quote($schemaName),
            ]
        ));
    }

    public function grantManageAccessToSchema(string $schemaName): void
    {
        $this->query(vsprintf(
            'ALTER SCHEMA IF EXISTS %s ENABLE MANAGED ACCESS',
            [
                $this->quoteIdentifier($schemaName),
            ]
        ));
    }

    public function grantOnDatabaseToRole(string $database, string $role, array $grants): void
    {
        $this->grantToObjectTypeOnObjectType(
            Connection::OBJECT_TYPE_DATABASE,
            $database,
            Connection::OBJECT_TYPE_ROLE,
            $role,
            $grants
        );
    }

    public function grantOnSchemaToRole(string $schemaName, string $role, array $grants): void
    {
        $this->grantToObjectTypeOnObjectType(
            Connection::OBJECT_TYPE_SCHEMA,
            $schemaName,
            Connection::OBJECT_TYPE_ROLE,
            $role,
            $grants
        );
    }

    public function grantOnWarehouseToRole(string $warehouse, string $role, array $grants): void
    {
        $this->grantToObjectTypeOnObjectType(
            Connection::OBJECT_TYPE_WAREHOUSE,
            $warehouse,
            Connection::OBJECT_TYPE_ROLE,
            $role,
            $grants
        );
    }

    private function grantRoleToObject(string $role, string $granteeName, string $objectType): void
    {
        $this->query(vsprintf(
            'GRANT ROLE %s TO ' . $objectType . ' %s',
            [
                $this->quoteIdentifier($role),
                $this->quoteIdentifier($granteeName),
            ]
        ));
    }

    public function grantRoleToRole(string $roleToBeGranted, string $roleToGrantTo): void
    {
        $this->grantRoleToObject($roleToBeGranted, $roleToGrantTo, self::OBJECT_TYPE_ROLE);
    }

    public function grantRoleToUser(string $role, string $userName): void
    {
        $this->grantRoleToObject($role, $userName, self::OBJECT_TYPE_USER);
    }

    public function grantSelectOnAllTablesInSchemaToRole(string $schemaName, string $role): void
    {
        $this->query(vsprintf(
            'GRANT SELECT ON ALL TABLES IN SCHEMA %s TO ROLE %s',
            [
                $this->quoteIdentifier($schemaName),
                $this->quoteIdentifier($role),
            ]
        ));
    }

    private function grantToObjectTypeOnObjectType(
        string $grantOnObjectType,
        string $grantOnName,
        string $granteeObjectType,
        string $grantToName,
        array $grant
    ): void {
        $this->query(vsprintf(
            'GRANT ' . implode(',', $grant) . ' 
            ON ' . $grantOnObjectType . ' %s
            TO ' . $granteeObjectType . ' %s',
            [
                $this->quoteIdentifier($grantOnName),
                $this->quoteIdentifier($grantToName),
            ]
        ));
    }

    private function grantToObjectTypeOnAllObjectTypesInSchema(
        string $grantOnObjectType,
        string $schemaName,
        string $granteeObjectType,
        string $grantToName,
        array $grant
    ): void {
        $this->query(vsprintf(
            'GRANT ' . implode(',', $grant) . ' 
            ON ALL ' . $grantOnObjectType . 'S 
            IN SCHEMA %s
            TO ' . $granteeObjectType . ' %s',
            [
                $this->quoteIdentifier($schemaName),
                $this->quoteIdentifier($grantToName),
            ]
        ));
    }

    private function grantToObjectTypeOnFutureObjectTypesInSchema(
        string $grantOnObjectType,
        string $schemaName,
        string $granteeObjectType,
        string $grantToName,
        array $grant
    ): void {
        $this->query(vsprintf(
            'GRANT ' . implode(',', $grant) . ' 
            ON FUTURE ' . $grantOnObjectType . 'S 
            IN SCHEMA %s
            TO ' . $granteeObjectType . ' %s',
            [
                $this->quoteIdentifier($schemaName),
                $this->quoteIdentifier($grantToName),
            ]
        ));
    }

    public function grantOnAllObjectTypesInSchemaToRole(
        string $grantOnObjectType,
        string $schemaName,
        string $roleName,
        array $grant
    ): void {
        $this->grantToObjectTypeOnAllObjectTypesInSchema(
            $grantOnObjectType,
            $schemaName,
            self::OBJECT_TYPE_ROLE,
            $roleName,
            $grant
        );
    }

    public function grantOnFutureObjectTypesInSchemaToRole(
        string $grantOnObjectType,
        string $schemaName,
        string $roleName,
        array $grant
    ): void {
        $this->grantToObjectTypeOnFutureObjectTypesInSchema(
            $grantOnObjectType,
            $schemaName,
            self::OBJECT_TYPE_ROLE,
            $roleName,
            $grant
        );
    }

    public function query(string $sql, array $bind = []): void
    {
        try {
            parent::query($sql, $bind);
        } catch (Throwable $e) {
            throw new RuntimeException(sprintf('Error "%s" while executing query "%s"', $e->getMessage(), $sql));
        }
    }

    public function quote(string $value): string
    {
        $q = "'";
        return ($q . str_replace("$q", "$q$q", $value) . $q);
    }

    private function revokeRoleFromObjectType(
        string $grantedRole,
        string $roleGrantedTo,
        string $objectTypeGrantedTo
    ): void {
        $this->query(vsprintf(
            'REVOKE ROLE %s
            FROM ' . $objectTypeGrantedTo . ' %s',
            [
                $this->quoteIdentifier($grantedRole),
                $this->quoteIdentifier($roleGrantedTo),
            ]
        ));
    }

    public function revokeRoleGrantFromRole(string $grantedRole, string $roleGrantedTo): void
    {
        $this->revokeRoleFromObjectType($grantedRole, $roleGrantedTo, Connection::OBJECT_TYPE_ROLE);
    }

    /**
     * @return mixed[][]
     */
    public function showGrantsOfRole(string $role): array
    {
        return $this->fetchAll(vsprintf(
            'SHOW GRANTS OF ROLE %s',
            [
                $this->quoteIdentifier($role),
            ]
        ));
    }

    /**
     * @return mixed[]
     */
    public function showGrantsToRole(string $role): array
    {
        return $this->fetchAll(vsprintf(
            'SHOW GRANTS TO ROLE %s',
            [
                $this->quoteIdentifier($role),
            ]
        ));
    }

    public function getCurrentRole(): string
    {
        $res = $this->fetchAll('SELECT CURRENT_ROLE() AS "name"');
        return $res[0]['name'];
    }
}
