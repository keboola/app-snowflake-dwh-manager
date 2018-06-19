<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager;

use Keboola\Component\UserException;
use Keboola\SnowflakeDwhManager\Configuration\Schema;
use Keboola\SnowflakeDwhManager\Configuration\User;
use Keboola\SnowflakeDwhManager\Connection\Expr;
use Keboola\SnowflakeDwhManager\Manager\Checker;
use Psr\Log\LoggerInterface;
use RandomLib\Factory;
use function sprintf;

class DwhManager
{
    private const GENERATED_PASSWORD_LENGHT = 12;
    private const PRIVILEGES_DATABASE_MINIMAL = [
        'USAGE',
    ];
    private const PRIVILEGES_SCHEMA_FULL_ACCESS = [
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
    private const PRIVILEGES_SCHEMA_READ_ONLY = [
        'USAGE',
    ];
    private const PRIVILEGES_WAREHOUSE_MINIMAL = [
        'USAGE',
    ];
    private const SUFFIX_ROLE_RO = '_role_ro';

    /** @var string */
    private $uniquePrefix;

    /** @var Checker */
    private $checker;

    /** @var Connection */
    private $connection;

    /** @var LoggerInterface */
    private $logger;

    /** @var string */
    private $warehouse;

    /** @var string */
    private $database;

    public function __construct(
        string $uniquePrefix,
        Checker $checker,
        Connection $connection,
        LoggerInterface $logger,
        string $warehouse,
        string $database
    ) {
        $this->uniquePrefix = $uniquePrefix;
        $this->checker = $checker;
        $this->connection = $connection;
        $this->logger = $logger;
        $this->warehouse = $warehouse;
        $this->database = $database;
    }

    public function checkSchema(Schema $schema): void
    {
        $schemaName = $this->getSchemaNameFromSchema($schema);
        $rwUser = $this->getRwUserFromSchema($schema);
        $rwRole = $this->getRwRoleFromSchema($schema);
        $roRole = $this->getRoRoleFromSchema($schema);
        $currentRole = $this->checker->getCurrentRole();

        $this->ensureSchemaExists($schemaName);

        $this->ensureRoleExists($rwRole);
        $this->ensureRoleHasWarehousePrivileges($rwRole, self::PRIVILEGES_WAREHOUSE_MINIMAL);
        $this->ensureRoleHasDatabasePrivileges($rwRole, self::PRIVILEGES_DATABASE_MINIMAL);
        $this->ensureRoleHasSchemaPrivileges($rwRole, self::PRIVILEGES_SCHEMA_FULL_ACCESS, $schemaName);

        $this->ensureRoleExists($roRole);
        $this->ensureRoleHasWarehousePrivileges($roRole, self::PRIVILEGES_WAREHOUSE_MINIMAL);
        $this->ensureRoleHasDatabasePrivileges($roRole, self::PRIVILEGES_DATABASE_MINIMAL);
        $this->ensureRoleHasSchemaPrivileges($roRole, self::PRIVILEGES_SCHEMA_READ_ONLY, $schemaName);

        $this->ensureUserExists($rwUser, [
            'default_role' => $rwRole,
            'default_warehouse' => $this->warehouse,
            'default_namespace' => new Expr(
                $this->connection->quoteIdentifier($this->database) .
                '.' .
                $this->connection->quoteIdentifier($schemaName)
            ),
        ]);

        $this->ensureRoleGrantedToUser($rwRole, $rwUser);

        $this->ensureRoleGrantedToRole($rwRole, $currentRole);
        $this->ensureRoleGrantedToRole($roRole, $currentRole);

        $this->ensureGrantedSelectOnAllTablesInSchemaToRole($schemaName, $roRole);
    }

    public function checkUser(User $user): void
    {
        $userSchemaName = $this->getOwnSchemaNameFromUser($user);
        $userRole = $this->getRoleNameFromUser($user);
        $currentRole = $this->checker->getCurrentRole();

        $this->ensureSchemaExists($userSchemaName);

        $this->ensureRoleExists($userRole);

        $this->ensureRoleHasSchemaPrivileges($userRole, self::PRIVILEGES_SCHEMA_FULL_ACCESS, $userSchemaName);

        $userName = $this->getUsernameFromEmail($user);
        $this->ensureUserExists($userName, [
            'login_name' => $user->getEmail(),
            'default_role' => $userRole,
            'default_warehouse' => $this->warehouse,
            'default_namespace' => new Expr(
                $this->connection->quoteIdentifier($this->database) .
                '.' .
                $this->connection->quoteIdentifier($userSchemaName)
            ),
        ]);

        $this->ensureRoleGrantedToUser($userRole, $userName);

        $this->ensureRoleGrantedToRole($userRole, $currentRole);

        foreach ($user->getSchemes() as $linkedSchemaName) {
            if (!$this->checker->existsSchema($linkedSchemaName)) {
                throw new UserException(sprintf(
                    'The schema "%s" to link to user "%s" does not exist',
                    $linkedSchemaName,
                    $userName
                ));
            }

            $schemaLinkedToRole = $this->getRoRoleFromSchemaName($linkedSchemaName);
            $this->ensureRoleGrantedToRole($schemaLinkedToRole, $userRole);
        }
    }

    private function ensureGrantedSelectOnAllTablesInSchemaToRole(
        string $schemaName,
        string $role
    ): void {
        $this->connection->grantSelectOnAllTablesInSchemaToRole($schemaName, $role);
        $this->logger->info(sprintf(
            'Granted SELECT to all tables in "%s" to "%s"',
            $schemaName,
            $role
        ));
    }

    private function ensureRoleExists(string $role): void
    {
        if (!$this->checker->existsRole($role)) {
            $this->connection->createRole($role);
            $this->logger->info(sprintf(
                'Created role "%s"',
                $role
            ));
        } else {
            $this->logger->info(sprintf(
                'Role "%s" exists',
                $role
            ));
        }
    }

    private function ensureRoleGrantedToRole(string $role, string $userName): void
    {
        if (!$this->checker->isRoleGrantedToRole($role, $userName)) {
            $this->connection->grantRoleToRole($role, $userName);
            $this->logger->info(sprintf(
                'Role "%s" granted to role "%s"',
                $role,
                $userName
            ));
        } else {
            $this->logger->info(sprintf(
                'Role "%s" has already been granted role "%s"',
                $userName,
                $role
            ));
        }
    }

    private function ensureRoleGrantedToUser(string $role, string $userName): void
    {
        if (!$this->checker->isRoleGrantedToUser($role, $userName)) {
            $this->connection->grantRoleToUser($role, $userName);
            $this->logger->info(sprintf(
                'User "%s" has been granted the role "%s"',
                $userName,
                $role
            ));
        } else {
            $this->logger->info(sprintf(
                'User "%s" is already granted the role "%s"',
                $userName,
                $role
            ));
        }
    }

    private function ensureRoleHasDatabasePrivileges(string $role, array $databasePrivileges): void
    {
        if (!$this->checker->hasRolePrivilegesOnDatabase(
            $role,
            $databasePrivileges,
            $this->database
        )) {
            $this->connection->grantOnDatabaseToRole(
                $this->database,
                $role,
                $databasePrivileges
            );
            $this->logger->info(sprintf(
                'Granted [%s] to role "%s" on database "%s"',
                implode(',', $databasePrivileges),
                $role,
                $this->database
            ));
        } else {
            $this->logger->info(sprintf(
                'Role "%s" has all the required grants on "%s" database',
                $role,
                $this->database
            ));
        }
    }

    private function ensureRoleHasSchemaPrivileges(string $role, array $schemaPrivileges, string $schemaName): void
    {
        if (!$this->checker->hasRolePrivilegesOnSchema(
            $role,
            $schemaPrivileges,
            $schemaName
        )) {
            $this->connection->grantOnSchemaToRole($schemaName, $role, $schemaPrivileges);
            $this->logger->info(sprintf(
                'Granted [%s] to role "%s" on schema "%s"',
                implode(',', $schemaPrivileges),
                $role,
                $schemaName
            ));
        } else {
            $this->logger->info(sprintf(
                'Role "%s" has all the required grants',
                $role
            ));
        }
    }

    private function ensureRoleHasWarehousePrivileges(string $role, array $warehousePrivileges): void
    {
        if (!$this->checker->hasRolePrivilegesOnWarehouse(
            $role,
            $warehousePrivileges,
            $this->warehouse
        )) {
            $this->connection->grantOnWarehouseToRole(
                $this->warehouse,
                $role,
                $warehousePrivileges
            );
            $this->logger->info(sprintf(
                'Granted [%s] to role "%s" on warehouse "%s"',
                implode(',', $warehousePrivileges),
                $role,
                $this->warehouse
            ));
        } else {
            $this->logger->info(sprintf(
                'Role "%s" has all the required grants on "%s" warehouse',
                $role,
                $this->warehouse
            ));
        }
    }

    private function ensureSchemaExists(string $schemaName): void
    {
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
    }

    private function ensureUserExists(string $userName, array $options): void
    {
        if (!$this->checker->existsUser($userName)) {
            $password = $this->generatePassword();
            $this->connection->createUser(
                $userName,
                $password,
                $options
            );
            if (isset($options['login_name'])) {
                $this->logger->info(sprintf(
                    'Created user "%s" (%s) with password "%s"',
                    $options['login_name'],
                    $userName,
                    $password
                ));
            } else {
                $this->logger->info(sprintf(
                    'Created user "%s" with password "%s"',
                    $userName,
                    $password
                ));
            }
        } else {
            $this->logger->info(sprintf(
                'User "%s" already exists',
                $userName
            ));
        }
    }

    private function generatePassword(): string
    {
        $randomLibFactory = new Factory();
        $password = $randomLibFactory
            ->getMediumStrengthGenerator()
            ->generateString(self::GENERATED_PASSWORD_LENGHT);
        return $password;
    }

    private function getOwnSchemaNameFromUser(User $user): string
    {
        $schemaName = $this->sanitizeAsIdentifier($user->getEmail()) . '_schema_rw';
        $this->checkLength($schemaName, $user->getEmail(), 'Maximum email length is %s characters');
        return $schemaName;
    }

    private function getRoRoleFromSchemaName(string $schemaName): string
    {
        $role = $this->uniquePrefix . '_' . $schemaName . self::SUFFIX_ROLE_RO;
        $this->checkLength($role, $schemaName, 'Maximum schema name length is %s characters');
        return $role;
    }

    private function getRoRoleFromSchema(Schema $schema): string
    {
        $role = $this->getRoRoleFromSchemaName($schema->getName());
        $this->checkLength($role, $schema->getName(), 'Maximum schema name length is %s characters');
        return $role;
    }

    private function getRoleNameFromUser(User $user): string
    {
        $role = $this->sanitizeAsIdentifier($user->getEmail()) . '_role';
        $this->checkLength($role, $user->getEmail(), 'Maximum email length is %s characters');
        return $role;
    }

    private function getRwRoleFromSchema(Schema $schema): string
    {
        $role = $this->uniquePrefix . '_' . $schema->getName() . '_role_rw';
        $this->checkLength($role, $schema->getName(), 'Maximum schema name length is %s characters');
        return $role;
    }

    private function getRwUserFromSchema(Schema $schema): string
    {
        $user = $this->uniquePrefix . '_' . $schema->getName() . '_user_rw';
        $this->checkLength($user, $schema->getName(), 'Maximum schema name is %s characters');
        return $user;
    }

    private function getSchemaNameFromSchema(Schema $schema): string
    {
        $schemaName = $schema->getName();
        $this->checkLength($schemaName, $schema->getName(), 'Maximum schema name length is %s characters');
        return $schemaName;
    }

    private function getUsernameFromEmail(User $user): string
    {
        $username = $this->sanitizeAsIdentifier($user->getEmail());
        $this->checkLength($username, $user->getEmail(), 'Maximum email length is %s characters');
        return $username;
    }

    private function sanitizeAsIdentifier(string $string): string
    {
        return (string) preg_replace('~[^a-z0-9]+~', '_', strtolower($string));
    }

    private function checkLength(string $var, string $source, string $message): void
    {
        if (strlen($var) > 255) {
            $sourceMaxLength = 255 - (strlen($var) - strlen($source));
            throw new UserException(sprintf($message, $sourceMaxLength));
        }
    }
}
