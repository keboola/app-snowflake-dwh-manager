<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager;

use Keboola\Component\UserException;
use Keboola\SnowflakeDwhManager\Configuration\Schema;
use Keboola\SnowflakeDwhManager\Configuration\User;
use Keboola\SnowflakeDwhManager\Manager\Checker;
use Psr\Log\LoggerInterface;
use RandomLib\Factory;
use function sprintf;

class DwhManager
{
    private const GENERATED_PASSWORD_LENGHT = 12;
    private const SCHEMA_PRIVILEGES_FULL_ACCESS = [
        'CREATE EXTERNAL TABLE',
        'CREATE FILE FORMAT',
        'CREATE FUNCTION',
        'CREATE PIPE',
        'CREATE SEQUENCE',
        'CREATE STAGE',
        'CREATE TABLE',
        'CREATE VIEW',
        'MODIFY',
        'MONITOR',
        'USAGE',
    ];
    private const SCHEMA_READ_ONLY_PRIVILEGES = [
        'USAGE',
    ];
    private const WAREHOUSE_PRIVILEGES_MINIMAL = [
        'USAGE',
        'OPERATE',
    ];
    private const DATABASE_PRIVILEGES_MINIMAL = [
        'USAGE',
    ];

    /** @var LoggerInterface */
    private $logger;

    /** @var Checker */
    private $checker;

    /** @var Connection */
    private $connection;

    /** @var string */
    private $warehouse;

    /** @var string */
    private $database;

    public function __construct(
        Checker $checker,
        Connection $connection,
        LoggerInterface $logger,
        string $warehouse,
        string $database
    ) {
        $this->logger = $logger;
        $this->checker = $checker;
        $this->connection = $connection;
        $this->warehouse = $warehouse;
        $this->database = $database;
    }

    public function checkSchema(Schema $schema): void
    {
        $schemaName = $this->getSchemaNameFromSchema($schema);
        $rwRole = $this->getRwRoleFromSchema($schema);
        $rwUser = $this->getRwUserFromSchema($schema);

        if (!$this->checker->existsSchema($schemaName)) {
            $this->connection->createSchema($schemaName);
            $this->logger->info(sprintf(
                'Created schema "%s"',
                $schemaName
            ));
        } else {
            $this->logger->info(sprintf(
                'Schema "%s" exists',
                $schemaName
            ));
        }

        if (!$this->checker->existsRole($rwRole)) {
            $this->connection->createRole($rwRole);
            $this->logger->info(sprintf(
                'Created role "%s"',
                $rwRole
            ));
        } else {
            $this->logger->info(sprintf(
                'Role "%s" exists',
                $rwRole
            ));
        }

        if (!$this->checker->hasRolePrivilegesOnWarehouse(
            $rwRole,
            self::WAREHOUSE_PRIVILEGES_MINIMAL,
            $this->warehouse
        )) {
            $this->connection->grantOnWarehouseToRole(
                $this->warehouse,
                $rwRole,
                self::WAREHOUSE_PRIVILEGES_MINIMAL
            );
            $this->logger->info(sprintf(
                'Granted [%s] to role "%s" on warehouse "%s"',
                implode(',', self::WAREHOUSE_PRIVILEGES_MINIMAL),
                $rwRole,
                $this->warehouse
            ));
        } else {
            $this->logger->info(sprintf(
                'Role "%s" has all the required grants on "%s" warehouse',
                $rwRole,
                $this->warehouse
            ));
        }

        if (!$this->checker->hasRolePrivilegesOnDatabase(
            $rwRole,
            self::DATABASE_PRIVILEGES_MINIMAL,
            $this->database
        )) {
            $this->connection->grantOnDatabaseToRole(
                $this->database,
                $rwRole,
                self::DATABASE_PRIVILEGES_MINIMAL
            );
            $this->logger->info(sprintf(
                'Granted [%s] to role "%s" on database "%s"',
                implode(',', self::DATABASE_PRIVILEGES_MINIMAL),
                $rwRole,
                $this->database
            ));
        } else {
            $this->logger->info(sprintf(
                'Role "%s" has all the required grants on "%s" database',
                $rwRole,
                $this->database
            ));
        }

        if (!$this->checker->hasRolePrivilegesOnSchema(
            $rwRole,
            self::SCHEMA_PRIVILEGES_FULL_ACCESS,
            $schemaName
        )) {
            $this->connection->grantOnSchemaToRole($schemaName, $rwRole, ['ALL']);
            $this->logger->info(sprintf(
                'Granted ALL to role "%s" on schema "%s"',
                $rwRole,
                $schemaName
            ));
        } else {
            $this->logger->info(sprintf(
                'Role "%s" has all the required grants',
                $rwRole
            ));
        }

        if (!$this->checker->existsUser($rwUser)) {
            $password = $this->generatePassword();
            $this->connection->createUser(
                $rwUser,
                $password,
                [
                    'default_role' => $rwRole,
                    'default_warehouse' => $this->warehouse,
                    'default_namespace' => $this->database,
                ]
            );
            $this->logger->info(sprintf(
                'Created user "%s" with password "%s"',
                $rwUser,
                $password
            ));
        } else {
            $this->logger->info(sprintf(
                'User "%s" already exists',
                $rwUser
            ));
        }

        if (!$this->checker->isRoleGrantedToUser($rwRole, $rwUser)) {
            $this->connection->grantRoleToUser($rwRole, $rwUser);
            $this->logger->info(sprintf(
                'User "%s" has been granted the role "%s"',
                $rwUser,
                $rwRole
            ));
        } else {
            $this->logger->info(sprintf(
                'User "%s" is already granted the role "%s"',
                $rwUser,
                $rwRole
            ));
        }
    }

    public function checkUser(User $user): void
    {
        $userSchemaName = $this->getOwnSchemaNameFromUser($user);
        if (!$this->checker->existsSchema($userSchemaName)) {
            $this->connection->createSchema($userSchemaName);
            $this->logger->info(sprintf(
                'Created schema "%s"',
                $userSchemaName
            ));
        } else {
            $this->logger->info(sprintf(
                'Schema "%s" already exists',
                $userSchemaName
            ));
        }

        $userRole = $this->getRoleNameFromUser($user);
        if (!$this->checker->existsRole($userRole)) {
            $this->connection->createRole($userRole);
            $this->logger->info(sprintf(
                'Created role "%s"',
                $userRole
            ));
        } else {
            $this->logger->info(sprintf(
                'Role "%s" exists',
                $userRole
            ));
        }

        if (!$this->checker->hasRolePrivilegesOnWarehouse(
            $userRole,
            self::WAREHOUSE_PRIVILEGES_MINIMAL,
            $this->warehouse
        )) {
            $this->connection->grantOnWarehouseToRole(
                $this->warehouse,
                $userRole,
                self::WAREHOUSE_PRIVILEGES_MINIMAL
            );
            $this->logger->info(sprintf(
                'Granted [%s] to role "%s" on warehouse "%s"',
                implode(',', self::WAREHOUSE_PRIVILEGES_MINIMAL),
                $userRole,
                $this->warehouse
            ));
        } else {
            $this->logger->info(sprintf(
                'Role "%s" has all the required grants on "%s" warehouse',
                $userRole,
                $this->warehouse
            ));
        }

        if (!$this->checker->hasRolePrivilegesOnDatabase(
            $userRole,
            self::DATABASE_PRIVILEGES_MINIMAL,
            $this->database
        )) {
            $this->connection->grantOnDatabaseToRole(
                $this->database,
                $userRole,
                self::DATABASE_PRIVILEGES_MINIMAL
            );
            $this->logger->info(sprintf(
                'Granted [%s] to role "%s" on database "%s"',
                implode(',', self::DATABASE_PRIVILEGES_MINIMAL),
                $userRole,
                $this->database
            ));
        } else {
            $this->logger->info(sprintf(
                'Role "%s" has all the required grants on "%s" database',
                $userRole,
                $this->database
            ));
        }

        if (!$this->checker->hasRolePrivilegesOnSchema(
            $userRole,
            self::SCHEMA_PRIVILEGES_FULL_ACCESS,
            $userSchemaName
        )) {
            $this->connection->grantOnSchemaToRole($userSchemaName, $userRole, ['ALL']);
            $this->logger->info(sprintf(
                'Granted ALL to role "%s" on schema "%s"',
                $userRole,
                $userSchemaName
            ));
        } else {
            $this->logger->info(sprintf(
                'Role "%s" has all the required grants on schema "%s"',
                $userRole,
                $userSchemaName
            ));
        }

        $userName = $this->getUsernameFromEmail($user);
        if (!$this->checker->existsUser($userName)) {
            $password = $this->generatePassword();
            $this->connection->createUser(
                $userName,
                $password,
                [
                    'login_name' => $user->getEmail(),
                    'default_role' => $userRole,
                    'default_warehouse' => $this->warehouse,
                    'default_namespace' => $this->database,
                ]
            );
            $this->logger->info(sprintf(
                'Created user "%s" with password "%s"',
                $userName,
                $password
            ));
        } else {
            $this->logger->info(sprintf(
                'User "%s" already exists',
                $userName
            ));
        }

        if (!$this->checker->isRoleGrantedToUser($userRole, $userName)) {
            $this->connection->grantRoleToUser($userRole, $userName);
            $this->logger->info(sprintf(
                'User "%s" has been granted the role "%s"',
                $userName,
                $userRole
            ));
        } else {
            $this->logger->info(sprintf(
                'User "%s" is already granted the role "%s"',
                $userName,
                $userRole
            ));
        }

        foreach ($user->getSchemes() as $linkedSchemaName) {
            if (!$this->checker->existsSchema($linkedSchemaName)) {
                throw new UserException(sprintf(
                    'The schema "%s" to link to user "%s" does not exist',
                    $linkedSchemaName,
                    $userName
                ));
            }

            $linkedSchemaRoRole = $this->getReadOnlyRoleNameFromSchemaName($linkedSchemaName);
            if (!$this->checker->existsRole($linkedSchemaRoRole)) {
                $this->connection->createRole($linkedSchemaRoRole);
                $this->logger->info(sprintf(
                    'Created role "%s"',
                    $linkedSchemaRoRole
                ));
            } else {
                $this->logger->info(sprintf(
                    'Role "%s" already exists',
                    $linkedSchemaRoRole
                ));
            }

            if (!$this->checker->hasRolePrivilegesOnSchema(
                $linkedSchemaRoRole,
                self::SCHEMA_READ_ONLY_PRIVILEGES,
                $linkedSchemaName
            )) {
                $this->connection->grantOnSchemaToRole(
                    $linkedSchemaName,
                    $linkedSchemaRoRole,
                    self::SCHEMA_READ_ONLY_PRIVILEGES
                );
                $this->logger->info(sprintf(
                    'Granted [%s] on "%s" to "%s"',
                    implode(',', self::SCHEMA_READ_ONLY_PRIVILEGES),
                    $linkedSchemaName,
                    $linkedSchemaRoRole
                ));
            } else {
                $this->logger->info(sprintf(
                    'Role "%s" already has all the required privledges on "%s"',
                    $linkedSchemaRoRole,
                    $linkedSchemaName
                ));
            }

            if (!$this->checker->isRoleGrantedToRole($linkedSchemaRoRole, $userRole)) {
                $this->connection->grantRoleToRole($linkedSchemaRoRole, $userRole);
                $this->logger->info(sprintf(
                    'Role "%s" granted to role "%s"',
                    $linkedSchemaRoRole,
                    $userRole
                ));
            } else {
                $this->logger->info(sprintf(
                    'Role "%s" has already been granted role "%s"',
                    $userRole,
                    $linkedSchemaRoRole
                ));
            }

            $this->connection->grantSelectOnAllTablesInSchemaToRole($linkedSchemaName, $linkedSchemaRoRole);
            $this->logger->info(sprintf(
                'Granted SELECT to all tables in "%s" to "%s"',
                $linkedSchemaName,
                $linkedSchemaRoRole
            ));
            // Created user "tomas_fejfar_keboola_com" with password "N/fOHq5jSPRc"
        }
    }

    private function getRwRoleFromSchema(Schema $schema): string
    {
        return $schema->getName() . '_role_rw';
    }

    private function getRwUserFromSchema(Schema $schema): string
    {
        return $schema->getName() . '_user_rw';
    }

    private function getSchemaNameFromSchema(Schema $schema): string
    {
        return $schema->getName();
    }

    private function getUsernameFromEmail(User $user): string
    {
        return $this->sanitizeAsIdentifier($user->getEmail());
    }

    private function getOwnSchemaNameFromUser(User $user): string
    {
        return $this->sanitizeAsIdentifier($user->getEmail()) . '_schema_rw';
    }

    private function sanitizeAsIdentifier(string $string): string
    {
        return (string) preg_replace('~[^a-z0-9]+~', '_', $string);
    }

    private function getRoleNameFromUser(User $user): string
    {
        return $this->sanitizeAsIdentifier($user->getEmail()) . '_role';
    }

    private function generatePassword(): string
    {
        $randomLibFactory = new Factory();
        $password = $randomLibFactory
            ->getMediumStrengthGenerator()
            ->generateString(self::GENERATED_PASSWORD_LENGHT);
        return $password;
    }

    private function getReadOnlyRoleNameFromSchemaName(string $linkedSchemaName): string
    {
        return $linkedSchemaName . '_role_ro';
    }
}
