<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager;

use ErrorException;
use Keboola\Db\Import\Snowflake\Connection;
use Keboola\SnowflakeDwhManager\Configuration\Schema;
use Keboola\SnowflakeDwhManager\Configuration\User;
use Psr\Log\LoggerInterface;
use RandomLib\Factory;
use function sprintf;
use function var_dump;
use function vsprintf;

class DwhManager
{
    private const GENERATED_PASSWORD_LENGHT = 12;
    private const REQUIRED_RW_PRIVILEGES = [
        "CREATE EXTERNAL TABLE",
        "CREATE FILE FORMAT",
        "CREATE FUNCTION",
        "CREATE PIPE",
        "CREATE SEQUENCE",
        "CREATE STAGE",
        "CREATE TABLE",
        "CREATE VIEW",
        "MODIFY",
        "MONITOR",
        "USAGE",
    ];

    /** @var Connection */
    private $connection;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        Connection $connection,
        LoggerInterface $logger
    ) {
        $this->connection = $connection;
        $this->logger = $logger;
    }

    public function schemaSetupCorrectly(Schema $schema): bool
    {
        $schemaName = $schema->getName();
        $schemas = $this->connection->fetchAll(vsprintf(
            'SHOW SCHEMAS LIKE %s',
            [
                $this->quote($schemaName),
            ]
        ));
        if (count($schemas) !== 1) {
            return false;
        }
        $this->logger->info(sprintf('Schema "%s" exists', $schemas[0]['name']));

        $rwRole = $this->getRwRoleFromSchema($schema);
        $roles = $this->connection->fetchAll(vsprintf(
            'SHOW ROLES LIKE %s',
            [
                $this->quote($rwRole),
            ]
        ));
        if (count($roles) !== 1) {
            return false;
        }
        $this->logger->info(sprintf('Role "%s" exists', $roles[0]['name']));

        $grants = $this->connection->fetchAll(vsprintf(
            'SHOW GRANTS TO ROLE %s',
            [
                $this->quoteIdentifier($schema->getName()),
            ]
        ));

        if (!$this->hasAllRequiredPrivileges($grants)) {
            return false;
        }

        $this->logger->info(sprintf('Role "%s" has all the required grants', $roles[0]['name']));

        try {
            $userName = $this->getRwUserFromSchema($schema);
            $users = $this->connection->fetchAll(vsprintf(
                'DESC USER %s',
                [
                    $this->quoteIdentifier($userName),
                ]
            ));
            $this->logger->info(sprintf('User "%s" exists', $users[0]['name']));
        } catch (ErrorException $e) {
            $containsUserMissingError = strpos(
                $e->getMessage(),
                'User \'"my-dwh-schema"\' does not exist.'
            );
            if ($containsUserMissingError !== false) {
                return false;
            }
            die(var_dump($containsUserMissingError, $e->getMessage()));
        }

        return true;
    }

    public function updateSchema(Schema $schema): void
    {
    }

    /**
     * @return mixed[]
     */
    public function createSchemaAndRwUser(Schema $schema): array
    {
        $schemaName = $schema->getName();
        $this->createSchema($schemaName);
        $this->logger->info(sprintf(
            'Created schema "%s"',
            $schemaName
        ));

        $rwRole = $this->getRwRoleFromSchema($schema);
        $this->createRoleInternal($rwRole);
        $this->logger->info(sprintf(
            'Created role "%s"',
            $rwRole
        ));

        $this->grantToRwRole($schema, $rwRole);
        $this->logger->info(sprintf(
            'Granted ALL to role "%s"',
            $rwRole
        ));

        $randomLibFactory = new Factory();
        $password = $randomLibFactory->getMediumStrengthGenerator()->generateString(self::GENERATED_PASSWORD_LENGHT);
        $rwUser = $this->getRwUserFromSchema($schema);
        $this->createUserWithRoleInternal($rwUser, $password, $rwRole);
        $this->logger->info(sprintf(
            'Created user "%s" with password "%s"',
            $rwUser,
            $password
        ));
        /*
                CREATE ROLE "TF2_DWH_MANAGER";
                GRANT CREATE ROLE ON ACCOUNT TO ROLE "TF2_DWH_MANAGER" WITH GRANT OPTION;
                GRANT USAGE ON DATABASE TF2_DWH_MANAGER TO ROLE "TF2_DWH_MANAGER";
                CREATE USER "TF2_DWH_MANAGER"
        PASSWORD = "23x4ubs4sTFk"
        DEFAULT_ROLE = "TF2_DWH_MANAGER";

        GRANT ROLE "TF2_DWH_MANAGER" TO USER "TF2_DWH_MANAGER";
                */
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

    public function quoteIdentifier(string $value): string
    {
        return $this->connection->quoteIdentifier($value);
    }

    public function quote(string $value): string
    {
        $q = "'";
        return ($q . str_replace("$q", "$q$q", $value) . $q);
    }

    /**
     * @param mixed[] $grants
     * @return bool
     */
    private function hasAllRequiredPrivileges(array $grants): bool
    {
        $privileges = array_map(function (array $grant) {
            return $grant['privilege'];
        },
            $grants);
        return $privileges == self::REQUIRED_RW_PRIVILEGES;
    }

    private function createSchema(string $schema): void
    {
        $this->connection->query(vsprintf(
            'CREATE SCHEMA IF NOT EXISTS %s',
            [
                $this->quoteIdentifier($schema),
            ]
        ));
    }

    private function createRoleInternal(string $roleName): void
    {
        $this->connection->query(vsprintf(
            'CREATE ROLE IF NOT EXISTS %s',
            [
                $this->quoteIdentifier($roleName),
            ]
        ));
    }

    private function grantToRwRole(Schema $schema, string $role): void
    {
        $grant = 'ALL';
        $this->grantToRoleInternal($schema, $role, $grant);
    }

    private function createUserWithRoleInternal(string $userName, string $password, string $role): void
    {
        $this->connection->query(vsprintf(
            'CREATE USER IF NOT EXISTS 
            %s
            PASSWORD = %s
            DEFAULT_ROLE = %s
            ',
            [
                $this->quoteIdentifier($userName),
                $this->quote($password),
                $this->quote($role),
            ]
        ));
        $this->connection->query(vsprintf(
            'GRANT ROLE %s TO USER %s',
            [
                $this->quoteIdentifier($role),
                $this->quoteIdentifier($userName),
            ]
        ));
    }

    private function getRwRoleFromSchema(Schema $schema): string
    {
        return $schema->getName() . '_role_rw';
    }

    private function grantToRoleInternal(Schema $schema, string $role, string $grant): void
    {
        $this->connection->query(vsprintf(
            'GRANT ' . $grant . ' 
            ON SCHEMA %s
            TO ROLE %s',
            [
                $this->quoteIdentifier($schema->getName()),
                $role,
            ]
        ));
    }

    private function getRwUserFromSchema(Schema $schema): string
    {
        return $schema->getName() . '_user_rw';
    }
}
