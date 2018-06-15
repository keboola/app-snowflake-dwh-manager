<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\Manager;

use Keboola\SnowflakeDwhManager\Connection;
use Throwable;
use function array_filter;
use function strtoupper;

class Checker
{
    /** @var Connection */
    private $connection;

    public function __construct(
        Connection $connection
    ) {
        $this->connection = $connection;
    }

    public function existsRole(string $rwRole): bool
    {
        $roles = $this->connection->fetchRoles($rwRole);
        return count($roles) === 1;
    }

    public function existsSchema(string $schemaName): bool
    {
        $schemas = $this->connection->fetchSchemasLike($schemaName);
        return count($schemas) === 1;
    }

    public function existsUser(string $rwUserName): bool
    {
        try {
            $this->connection->describeUser($rwUserName);
        } catch (Throwable $e) {
            $position = strpos(
                $e->getMessage(),
                'User \'"' . $rwUserName . '"\' does not exist.'
            );
            if ($position !== false) {
                return false;
            }

            // other exception, but probably user still does not exist
            return false;
        }
        return true;
    }

    public function getCurrentRole(): string
    {
        $roles = $this->connection->fetchRoles();
        $result = array_values(array_filter($roles, function (array $role) {
            if ($role['is_current'] === 'Y') {
                return true;
            }
        }));
        return $result[0]['name'];
    }

    public function hasRolePrivilegesOnDatabase(string $role, array $grants, string $schema): bool
    {
        return $this->hasRolePrivilegesOnObjectType($role, $grants, $schema, Connection::OBJECT_TYPE_SCHEMA);
    }

    private function hasRolePrivilegesOnObjectType(
        string $role,
        array $grants,
        string $objectName,
        string $objectType
    ): bool {
        $objectName = strtoupper($objectName);
        $grantedGrants = $this->connection->showGrantsToRole($role);
        $grantedGrantsOnSchema = array_filter(
            $grantedGrants,
            function (array $grant) use ($objectName, $objectType) {
                $grantedOnSchema = $grant['granted_on'] === $objectType;
                $isSelectedSchema = strpos($grant['name'], $objectName) !== false;
                return $grantedOnSchema && $isSelectedSchema;
            }
        );
        $privileges = array_map(
            function (array $grant) {
                return $grant['privilege'];
            },
            $grantedGrantsOnSchema
        );
        return $privileges == $grants;
    }

    public function hasRolePrivilegesOnSchema(string $role, array $grants, string $schema): bool
    {
        return $this->hasRolePrivilegesOnObjectType($role, $grants, $schema, Connection::OBJECT_TYPE_SCHEMA);
    }

    public function hasRolePrivilegesOnWarehouse(string $role, array $grants, string $warehouse): bool
    {
        return $this->hasRolePrivilegesOnObjectType($role, $grants, $warehouse, Connection::OBJECT_TYPE_WAREHOUSE);
    }

    private function isRoleGrantedToObject(string $role, string $granteeName, string $objectType): bool
    {
        $roleGrants = $this->connection->showGrantsOfRole($role);
        $roleGrants = array_filter(
            $roleGrants,
            function ($grant) use ($granteeName, $objectType) {
                $grantedToUser = $grant['granted_to'] === $objectType;
                $userMatches = $grant['grantee_name'] === $granteeName;

                return $grantedToUser && $userMatches;
            }
        );
        return count($roleGrants) >= 1;
    }

    public function isRoleGrantedToRole(string $roleThatIsGranted, string $roleGrantedTo): bool
    {
        return $this->isRoleGrantedToObject($roleThatIsGranted, $roleGrantedTo, Connection::OBJECT_TYPE_ROLE);
    }

    public function isRoleGrantedToUser(string $role, string $userName): bool
    {
        return $this->isRoleGrantedToObject($role, $userName, Connection::OBJECT_TYPE_USER);
    }
}
