<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager;

use Exception;
use Keboola\SnowflakeDbAdapter\Connection as SnowflakeConnection;
use Keboola\SnowflakeDwhManager\Connection\Expr;
use RuntimeException;
use Throwable;
use function strtoupper;
use const PHP_EOL;

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

    /**
     * @param array<mixed> $options
     */
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
            ],
        ));
    }

    public function resetUserPassword(string $userName): string
    {
        $this->query(vsprintf(
            'ALTER USER IF EXISTS %s RESET PASSWORD;',
            [
                $this->quoteIdentifier($userName),
            ],
        ));
        $result = $this->fetchAll('SELECT * FROM table(result_scan(-1));');
        return $result[0]['status'];
    }

    public function resetUserPublicKey(string $userName, string $publicKey): void
    {
        $this->query(vsprintf(
            'ALTER USER IF EXISTS %s SET RSA_PUBLIC_KEY=%s;',
            [
                $this->quoteIdentifier($userName),
                $this->quoteIdentifier($publicKey),
            ],
        ));
    }

    public function retrieveUserPublicKey(string $userName, string $type = 'rsa_public_key'): string
    {
        /** @var array<string, string|int> $user */
        $user = $this->describeUser($userName);

        return (string) $user[$type];
    }

    public function isPasswordSet(string $userName): bool
    {
        $user = $this->describeUser($userName);

        return $user['password'] !== null && $user['password'] !== 'null';
    }

    public function unsetPassword(string $userName): void
    {
        $this->query(vsprintf(
            'ALTER USER IF EXISTS %s UNSET PASSWORD;',
            [
                $this->quoteIdentifier($userName),
            ],
        ));
    }

    public function resetUserMFA(string $userName): void
    {
        $this->query(vsprintf(
            'ALTER USER IF EXISTS %s SET DISABLE_MFA=true;',
            [
                $this->quoteIdentifier($userName),
            ],
        ));
    }

    public function migrateUserToTypePerson(string $userName): void
    {
        $this->query(vsprintf(
            'ALTER USER IF EXISTS %s SET type=PERSON;',
            [
                $this->quoteIdentifier($userName),
            ],
        ));
    }

    /**
     * @param array<mixed> $otherOptions
     */
    private function createQuotedOptionsStringFromArray(array $otherOptions): string
    {
        $otherOptionsString = '';
        foreach ($otherOptions as $option => $optionValue) {
            $quotedValue = $optionValue instanceof Expr ? $optionValue->getValue() : $this->quote($optionValue);
            $otherOptionsString .= strtoupper($option) . '=' . $quotedValue . PHP_EOL;
        }
        return $otherOptionsString;
    }

    public function createRole(string $roleName): void
    {
        $this->query(vsprintf(
            'CREATE ROLE IF NOT EXISTS %s',
            [
                $this->quoteIdentifier($roleName),
            ],
        ));
    }

    public function createSchema(string $schema): void
    {
        $this->query(vsprintf(
            'CREATE SCHEMA IF NOT EXISTS %s WITH MANAGED ACCESS',
            [
                $this->quoteIdentifier($schema),
            ],
        ));
    }

    /**
     * @param array<mixed> $otherOptions
     */
    public function createUser(
        string $userName,
        string $passwordOrPublicKey,
        string $type,
        array $otherOptions,
    ): void {
        $otherOptionsString = $this->createQuotedOptionsStringFromArray($otherOptions);

        $this->query(vsprintf(
            'CREATE USER IF NOT EXISTS 
            %s 
            %s = %s 
            TYPE = ' . $type . '
            ' . $otherOptionsString,
            [
                $this->quoteIdentifier($userName),
                $type === 'SERVICE' ? 'RSA_PUBLIC_KEY' : 'PASSWORD',
                $this->quote($passwordOrPublicKey),
            ],
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
            ],
        ));
        $result = [];
        foreach ($userFields as $userField) {
            $result[strtolower($userField['property'])] = $userField['value'];
        }
        return $result;
    }

    /**
     * @param array<mixed> $bind
     * @return array<stirng, mixed>
     */
    public function fetchAll(string $sql, array $bind = []): array
    {
        try {
            return parent::fetchAll($sql, $bind);
        } catch (Throwable $e) {
            throw new RuntimeException(sprintf('Error "%s" while executing query "%s"', $e->getMessage(), $sql));
        }
    }

    public function existsRole(string $role): bool
    {
        $sql = 'SELECT COUNT(*) AS CNT FROM INFORMATION_SCHEMA.ENABLED_ROLES  WHERE ROLE_NAME = %s';
        $args = [$this->quote($role)];

        $result = $this->fetchAll(vsprintf(
            $sql,
            $args,
        ));
        return $result[0]['CNT'] > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchSchemasLike(string $schemaName): array
    {
        return $this->fetchAll(vsprintf(
            'SHOW SCHEMAS LIKE %s',
            [
                $this->quote($schemaName),
            ],
        ));
    }

    /**
     * @param array<mixed> $grants
     */
    public function grantOnDatabaseToRole(string $database, string $role, array $grants): void
    {
        $this->grantToObjectTypeOnObjectType(
            Connection::OBJECT_TYPE_DATABASE,
            $database,
            Connection::OBJECT_TYPE_ROLE,
            $role,
            $grants,
        );
    }

    /**
     * @param array<mixed> $grants
     */
    public function grantOnSchemaToRole(string $schemaName, string $role, array $grants): void
    {
        $this->grantToObjectTypeOnObjectType(
            Connection::OBJECT_TYPE_SCHEMA,
            $schemaName,
            Connection::OBJECT_TYPE_ROLE,
            $role,
            $grants,
        );
    }

    /**
     * @param array<mixed> $grants
     */
    public function grantOnWarehouseToRole(string $warehouse, string $role, array $grants): void
    {
        $this->grantToObjectTypeOnObjectType(
            Connection::OBJECT_TYPE_WAREHOUSE,
            $warehouse,
            Connection::OBJECT_TYPE_ROLE,
            $role,
            $grants,
        );
    }

    private function grantRoleToObject(string $role, string $granteeName, string $objectType): void
    {
        $this->query(vsprintf(
            'GRANT ROLE %s TO ' . $objectType . ' %s',
            [
                $this->quoteIdentifier($role),
                $this->quoteIdentifier($granteeName),
            ],
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

    /**
     * @param array<mixed> $grant
     */
    private function grantToObjectTypeOnObjectType(
        string $grantOnObjectType,
        string $grantOnName,
        string $granteeObjectType,
        string $grantToName,
        array $grant,
    ): void {
        $this->query(vsprintf(
            'GRANT ' . implode(',', $grant) . ' 
            ON ' . $grantOnObjectType . ' %s
            TO ' . $granteeObjectType . ' %s',
            [
                $this->quoteIdentifier($grantOnName),
                $this->quoteIdentifier($grantToName),
            ],
        ));
    }

    /**
     * @param array<mixed> $grant
     */
    private function grantToObjectTypeOnFutureObjectTypesInSchema(
        string $grantOnObjectType,
        string $schemaName,
        string $granteeObjectType,
        string $grantToName,
        array $grant,
    ): void {
        $this->query(vsprintf(
            'GRANT ' . implode(',', $grant) . ' 
            ON FUTURE ' . $grantOnObjectType . 'S 
            IN SCHEMA %s
            TO ' . $granteeObjectType . ' %s',
            [
                $this->quoteIdentifier($schemaName),
                $this->quoteIdentifier($grantToName),
            ],
        ));
    }

    /**
     * @param array<mixed> $grant
     */
    public function grantOnFutureObjectTypesInSchemaToRole(
        string $grantOnObjectType,
        string $schemaName,
        string $roleName,
        array $grant,
    ): void {
        $this->grantToObjectTypeOnFutureObjectTypesInSchema(
            $grantOnObjectType,
            $schemaName,
            self::OBJECT_TYPE_ROLE,
            $roleName,
            $grant,
        );
    }

    /**
     * @param array<mixed> $bind
     */
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
        string $objectTypeGrantedTo,
    ): void {
        $this->query(vsprintf(
            'REVOKE ROLE %s
            FROM ' . $objectTypeGrantedTo . ' %s',
            [
                $this->quoteIdentifier($grantedRole),
                $this->quoteIdentifier($roleGrantedTo),
            ],
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
            ],
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
            ],
        ));
    }

    public function getCurrentRole(): string
    {
        $res = $this->fetchAll('SELECT CURRENT_ROLE() AS "name"');
        return $res[0]['name'];
    }
}
