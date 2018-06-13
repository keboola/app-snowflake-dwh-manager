<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager;

use Keboola\Db\Import\Snowflake\Connection as SnowflakeConnection;

class Connection extends SnowflakeConnection
{
    /**
     * @return mixed[]
     */
    public function fetchSchemasLike(string $schemaName): array
    {
        return $this->fetchAll(vsprintf(
            'SHOW SCHEMAS LIKE %s',
            [
                $this->quote($schemaName),
            ]
        ));
    }

    /**
     * @return mixed[]
     */
    public function fetchRolesLike(string $role): array
    {
        return $this->fetchAll(vsprintf(
            'SHOW ROLES LIKE %s',
            [
                $this->quote($role),
            ]
        ));
    }

    public function quote(string $value): string
    {
        $q = "'";
        return ($q . str_replace("$q", "$q$q", $value) . $q);
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

}
