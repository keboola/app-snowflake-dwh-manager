<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager;

use Keboola\Db\Import\Snowflake\Connection;
use Keboola\SnowflakeDwhManager\Configuration\Schema;
use Keboola\SnowflakeDwhManager\Configuration\User;

class DwhManager
{
    /** @var Connection */
    private $connection;

    public function __construct(
        Connection $connection
    ) {
        $this->connection = $connection;
    }

    /**
     * @param string[] $schemaName
     */
    public function schemaSetupCorrectly(Schema $schemaName): bool
    {
        $this->connection->fetchAll('SELECT ')
    }

    /**
     * @param string[] $schemaName
     */
    public function updateSchema(Schema $schemaName): void
    {
    }

    /**
     * @param string[] $schemaName
     * @return mixed[]
     */
    public function createSchema(Schema $schemaName): array
    {
    }

    /**
     * @param mixed[] $user
     */
    public function userSetupCorrectly(User $user): bool
    {
    }

    /**
     * @param mixed[] $user
     */
    public function updateUser(User $user): void
    {
    }

    /**
     * @param mixed[] $user
     * @return mixed[]
     */
    public function createUser(User $user): array
    {
    }
}
