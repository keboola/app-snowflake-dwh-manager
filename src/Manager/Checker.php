<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\Manager;

use Keboola\SnowflakeDwhManager\Connection;
use Throwable;

class Checker
{
    /** @var Connection */
    private $connection;

    public function __construct(
        Connection $connection
    ) {
        $this->connection = $connection;
    }

    public function existsSchema(string $schemaName): bool
    {
        $schemas = $this->connection->fetchSchemasLike($schemaName);
        if (count($schemas) !== 1) {
            return false;
        }
    }

    public function existsRole(string $rwRole): bool
    {
        $roles = $this->connection->fetchRolesLike($rwRole);
        if (count($roles) !== 1) {
            return false;
        }
    }


    /**
     * @param string[] $grants
     * @return bool
     */
    public function hasRolePrivileges(string $role, array $grants): bool
    {
        $grantedGrants = $this->connection->showGrantsToRole($role);
        $privileges = array_map(
            function (array $grant) {
                return $grant['privilege'];
            },
            $grantedGrants
        );
        return $privileges == $grants;
    }

    public function userExists(string $rwUserName): bool
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

    public function isRoleGrantedToUser(string $role, string $userName): bool
    {
        $roleGrants = $this->connection->showGrantsOfRole($role);
        $roleGrants = array_filter(
            $roleGrants,
            function ($grant) use ($userName) {
                $grantedToUser = $grant['granted_to'] === 'USER';
                $userMatches = $grant['grantee_name'] === $userName;

                return $grantedToUser && $userMatches;
            }
        );

        return $roleGrants >= 1;
    }
}
