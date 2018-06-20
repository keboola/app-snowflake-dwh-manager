<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\Manager;

use Keboola\SnowflakeDwhManager\Connection;
use Throwable;
use function array_filter;

class Checker
{
    /** @var Connection */
    private $connection;

    public function __construct(
        Connection $connection
    ) {
        $this->connection = $connection;
    }

    public function existsRole(string $role): bool
    {
        $roles = $this->connection->fetchRoles($role);
        return count($roles) === 1;
    }

    public function existsSchema(string $schemaName): bool
    {
        $schemas = $this->connection->fetchSchemasLike($schemaName);
        return count($schemas) === 1;
    }

    public function existsUser(string $userName): bool
    {
        try {
            $this->connection->describeUser($userName);
        } catch (Throwable $e) {
            $position = strpos(
                $e->getMessage(),
                'User \'"' . $userName . '"\' does not exist.'
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
        return $this->connection->getCurrentRole();
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
