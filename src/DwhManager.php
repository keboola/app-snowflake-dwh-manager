<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager;

use Keboola\Db\Import\Snowflake\Connection;
use Keboola\SnowflakeDwhManager\Configuration\Schema;
use Keboola\SnowflakeDwhManager\Configuration\User;
use Keboola\SnowflakeDwhManager\Manager\Checker;
use Keboola\SnowflakeDwhManager\Manager\Generator;
use Psr\Log\LoggerInterface;
use RandomLib\Factory;
use Throwable;
use function array_filter;
use function sprintf;
use function strtolower;
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

    /** @var LoggerInterface */
    private $logger;

    /** @var Checker */
    private $checker;

    /** @var Generator */
    private $generator;

    public function __construct(
        Checker $checker,
        Generator $generator,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->checker = $checker;
        $this->generator = $generator;
    }

    public function checkSchema(Schema $schema): bool
    {
        $schemaName = $schema->getName();
        if (!$this->checker->existsSchema($schemaName)) {
            return false;
        }
        $this->logger->info(sprintf('Schema "%s" exists', $schemaName));

        $rwRole = $this->getRwRoleFromSchema($schema);
        if (!$this->checker->existsRole($rwRole)) {
            return false;
        }
        $this->logger->info(sprintf('Role "%s" exists', $rwRole));

        if (!$this->checker->hasRolePrivileges($rwRole, self::REQUIRED_RW_PRIVILEGES)) {
            return false;
        }
        $this->logger->info(sprintf('Role "%s" has all the required grants', $rwRole));

        $rwUser = $this->getRwUserFromSchema($schema);
        if (!$this->checker->userExists($rwUser)) {
            return false;
        }

        if (!$this->checker->isRoleGrantedToUser($rwRole, $rwUser)) {
            return false;
        }
        $this->logger->info(sprintf('User "%s" is granted the role "%s"', $rwUser, $rwRole));

        return true;
    }


    /**
     * @return mixed[]
     */
    public function createSchemaAndRwUser(Schema $schema): void
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

        $this->grantGrantsOnSchemaToRole($schemaName, $rwRole, ['ALL']);
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

        $this->grantRoleToUser($rwRole, $rwUser);
        /*
                CREATE ROLE "TF2_DWH_MANAGER";
                GRANT CREATE ROLE ON ACCOUNT TO ROLE "TF2_DWH_MANAGER" WITH GRANT OPTION;
                GRANT USAGE ON DATABASE TF2_DWH_MANAGER TO ROLE "TF2_DWH_MANAGER";
                CREATE USER "TF2_DWH_MANAGER"
        PASSWORD = "23x4ubs4sTFk"
        DEFAULT_ROLE = "TF2_DWH_MANAGER";

        GRANT ROLE "TF2_DWH_MANAGER" TO USER "TF2_DWH_MANAGER";
                */
        // WsGaSuwiGsVg
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


    protected function grantRoleToUser(string $role, string $user): void
    {
        $this->connection->query(vsprintf(
            'GRANT ROLE %s TO USER %s',
            [
                $this->quoteIdentifier($role),
                $this->quote($user),
            ]
        ));
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

    private function grantGrantsOnSchemaToRole(string $schemaName, string $role, array $grant): void
    {
        $this->connection->query(vsprintf(
            'GRANT ' . implode(',', $grant) . ' 
            ON SCHEMA %s
            TO ROLE %s',
            [
                $this->quoteIdentifier($schemaName),
                $this->quoteIdentifier($role),
            ]
        ));
    }

    private function getRwUserFromSchema(Schema $schema): string
    {
        return $schema->getName() . '_user_rw';
    }


}
