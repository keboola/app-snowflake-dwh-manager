<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager;

use Keboola\Component\UserException;
use Keboola\SnowflakeDwhManager\Configuration\Schema;
use Keboola\SnowflakeDwhManager\Configuration\User;
use Keboola\SnowflakeDwhManager\Connection\Expr;
use Keboola\SnowflakeDwhManager\Manager\Checker;
use Keboola\SnowflakeDwhManager\Manager\NamingConventions;
use Psr\Log\LoggerInterface;
use RandomLib\Factory;
use function array_diff;
use function sprintf;

class DwhManager
{
    private const GENERATED_PASSWORD_LENGTH = 32;
    private const PRIVILEGES_DATABASE_MINIMAL = [
        'USAGE',
    ];
    private const PRIVILEGES_SCHEMA_WRITER_ACCESS = [
        'CREATE STAGE',
        'CREATE TABLE',
        'CREATE VIEW',
        'USAGE',
    ];
    private const PRIVILEGES_SCHEMA_READ_ONLY = [
        'USAGE',
    ];
    private const PRIVILEGES_OBJECT_READ = [
        Connection::OBJECT_TYPE_TABLE => [
            'SELECT',
        ],
        Connection::OBJECT_TYPE_VIEW => [
            'SELECT',
        ],
        Connection::OBJECT_TYPE_STAGE => [
            'READ',
        ],
    ];
    private const PRIVILEGES_OBJECT_WRITE = [
        Connection::OBJECT_TYPE_TABLE => [
            'SELECT',
            'INSERT',
            'UPDATE',
            'DELETE',
            'TRUNCATE',
            'REFERENCES',
        ],
        Connection::OBJECT_TYPE_VIEW => [
            'SELECT',
        ],
        Connection::OBJECT_TYPE_STAGE => [
            'WRITE',
            'READ',
        ],
    ];
    private const PRIVILEGES_WAREHOUSE_MINIMAL = [
        'USAGE',
    ];
    private const DATA_RETENTION_TIME_IN_DAYS = 7;

    /** @var Checker */
    private $checker;

    /** @var Connection */
    private $connection;

    /** @var LoggerInterface */
    private $logger;

    /** @var NamingConventions */
    private $namingConventions;

    /** @var string */
    private $warehouse;

    /** @var string */
    private $database;

    public function __construct(
        Checker $checker,
        Connection $connection,
        LoggerInterface $logger,
        NamingConventions $namingConventions,
        string $warehouse,
        string $database
    ) {
        $this->checker = $checker;
        $this->connection = $connection;
        $this->logger = $logger;
        $this->namingConventions = $namingConventions;
        $this->warehouse = $warehouse;
        $this->database = $database;

        $this->ensureMaximumDataRetention();
    }

    public function checkSchema(Schema $schema): void
    {
        $schemaName = $this->namingConventions->getSchemaNameFromSchema($schema);
        $rwUser = $this->namingConventions->getRwUserFromSchema($schema);
        $rwRole = $this->namingConventions->getRwRoleFromSchema($schema);
        $roRole = $this->namingConventions->getRoRoleFromSchema($schema);
        $currentRole = $this->checker->getCurrentRole();

        $this->ensureSchemaExists($schemaName);

        // create RW role and give it RW access to schema
        $this->ensureRoleExists($rwRole);
        $this->ensureRoleHasWarehousePrivileges($rwRole, self::PRIVILEGES_WAREHOUSE_MINIMAL);
        $this->ensureRoleHasDatabasePrivileges($rwRole, self::PRIVILEGES_DATABASE_MINIMAL);
        $this->ensureRoleHasSchemaPrivileges($rwRole, self::PRIVILEGES_SCHEMA_WRITER_ACCESS, $schemaName);
        $this->ensureRoleHasFutureObjectPrivilegesOnSchema($rwRole, self::PRIVILEGES_OBJECT_WRITE, $schemaName);

        // create RO role and give it RO access to schema
        $this->ensureRoleExists($roRole);
        $this->ensureRoleHasWarehousePrivileges($roRole, self::PRIVILEGES_WAREHOUSE_MINIMAL);
        $this->ensureRoleHasDatabasePrivileges($roRole, self::PRIVILEGES_DATABASE_MINIMAL);
        $this->ensureRoleHasSchemaPrivileges($roRole, self::PRIVILEGES_SCHEMA_READ_ONLY, $schemaName);
        $this->ensureRoleHasFutureObjectPrivilegesOnSchema($roRole, self::PRIVILEGES_OBJECT_READ, $schemaName);

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

        // ensure RW role has all the RO role privileges
        $this->ensureRoleGrantedToRole($roRole, $rwRole);

        // ensure that DWHM role has all the roles
        $this->ensureRoleGrantedToRole($rwRole, $currentRole);
        $this->ensureRoleGrantedToRole($roRole, $currentRole);
    }

    public function checkUser(User $user): void
    {
        $userSchemaName = $this->namingConventions->getOwnSchemaNameFromUser($user);
        $userRole = $this->namingConventions->getRoleNameFromUser($user);
        $currentRole = $this->checker->getCurrentRole();

        // create user's schema and role
        $this->ensureSchemaExists($userSchemaName);
        $this->ensureRoleExists($userRole);
        $this->ensureRoleHasSchemaPrivileges($userRole, self::PRIVILEGES_SCHEMA_WRITER_ACCESS, $userSchemaName);
        $this->ensureRoleHasFutureObjectPrivilegesOnSchema($userRole, self::PRIVILEGES_OBJECT_WRITE, $userSchemaName);

        // create user itself and grant them their role
        $userName = $this->namingConventions->getUsernameFromEmail($user);
        $this->ensureUserExists($userName, [
            'default_role' => $userRole,
            'default_warehouse' => $this->warehouse,
            'default_namespace' => new Expr(
                $this->connection->quoteIdentifier($this->database) .
                '.' .
                $this->connection->quoteIdentifier($userSchemaName)
            ),
            'disabled' => new Expr($user->isDisabled() ? 'TRUE' : 'FALSE'),
            'email' => $user->getEmail(),
        ]);

        if ($user->isResetPassword()) {
            $this->ensureUserResetPassword($userName);
        }

        $this->ensureRoleGrantedToUser($userRole, $userName);

        // grant user's role to DWHM role
        $this->ensureRoleGrantedToRole($userRole, $currentRole);

        // grant user's role roles with access to respective schemas
        $desiredRoles = [];
        foreach ($user->getReadOnlySchemas() as $linkedRoSchemaName) {
            if (!$this->checker->existsSchema($linkedRoSchemaName)) {
                throw new UserException(sprintf(
                    'The schema "%s" to link to user "%s" does not exist',
                    $linkedRoSchemaName,
                    $userName
                ));
            }
            $roRoleFromSchemaName = $this->namingConventions->getRoRoleFromSchemaName($linkedRoSchemaName);
            $this->ensureRoleGrantedToRole($roRoleFromSchemaName, $currentRole);
            $desiredRoles[] = $roRoleFromSchemaName;
        }

        foreach ($user->getWriteSchemas() as $linkedRwSchemaName) {
            if (!$this->checker->existsSchema($linkedRwSchemaName)) {
                throw new UserException(sprintf(
                    'The schema "%s" to link to user "%s" to write into does not exist',
                    $linkedRwSchemaName,
                    $userName
                ));
            }
            $rwRoleFromSchemaName = $this->namingConventions->getRwRoleFromSchemaName($linkedRwSchemaName);
            $this->ensureRoleGrantedToRole($rwRoleFromSchemaName, $currentRole);
            $desiredRoles[] = $rwRoleFromSchemaName;
        }

        $this->ensureRoleOnlyHasRolesGranted($userRole, $desiredRoles);
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

    private function ensureRoleGrantedToRole(string $roleThatIsGranted, string $roleToGrantTo): void
    {
        if (!$this->checker->isRoleGrantedToRole($roleThatIsGranted, $roleToGrantTo)) {
            $this->connection->grantRoleToRole($roleThatIsGranted, $roleToGrantTo);
            $this->logger->info(sprintf(
                'Role "%s" has been granted to role "%s"',
                $roleThatIsGranted,
                $roleToGrantTo
            ));
        } else {
            $this->logger->info(sprintf(
                'Role "%s" has already been granted to role "%s"',
                $roleThatIsGranted,
                $roleToGrantTo
            ));
        }
    }

    private function ensureRoleGrantedToUser(string $role, string $userName): void
    {
        if (!$this->checker->isRoleGrantedToUser($role, $userName)) {
            $this->connection->grantRoleToUser($role, $userName);
            $this->logger->info(sprintf(
                'Role "%s" has been granted to user "%s"',
                $role,
                $userName
            ));
        } else {
            $this->logger->info(sprintf(
                'Role "%s" has already been granted to user "%s"',
                $role,
                $userName
            ));
        }
    }

    private function ensureRoleHasDatabasePrivileges(string $role, array $databasePrivileges): void
    {
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
    }

    private function ensureRoleHasFutureObjectPrivilegesOnSchema(
        string $role,
        array $privileges,
        string $schemaName
    ): void {
        foreach ($privileges as $objectType => $privilege) {
            $this->connection->grantOnFutureObjectTypesInSchemaToRole($objectType, $schemaName, $role, $privilege);
            $this->logger->info(sprintf(
                'Granted [%s] to role "%s" on future  %ss in schema "%s"',
                implode(',', $privilege),
                $role,
                $objectType,
                $schemaName
            ));
        }
    }

    private function ensureRoleHasSchemaPrivileges(string $role, array $schemaPrivileges, string $schemaName): void
    {
        $this->connection->grantOnSchemaToRole($schemaName, $role, $schemaPrivileges);
        $this->logger->info(sprintf(
            'Granted [%s] to role "%s" on schema "%s"',
            implode(',', $schemaPrivileges),
            $role,
            $schemaName
        ));
    }

    private function ensureRoleHasWarehousePrivileges(string $role, array $warehousePrivileges): void
    {
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
    }

    private function ensureRoleOnlyHasRolesGranted(string $roleName, array $desiredRoles): void
    {
        $grantedRoles = $this->checker->getGrantedRolesOfRole($roleName);
        $toBeRevoked = array_diff($grantedRoles, $desiredRoles);
        $toBeGranted = array_diff($desiredRoles, $grantedRoles);
        foreach ($toBeRevoked as $revokedRole) {
            $this->connection->revokeRoleGrantFromRole($revokedRole, $roleName);
            $this->logger->info(sprintf(
                'Role "%s" has been revoked from role "%s"',
                $revokedRole,
                $roleName
            ));
        }
        foreach ($toBeGranted as $grantedRole) {
            $this->connection->grantRoleToRole($grantedRole, $roleName);
            $this->logger->info(sprintf(
                'Role "%s" has been granted to role "%s"',
                $grantedRole,
                $roleName
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
            $options['must_change_password'] = new Expr('TRUE');
            $password = $this->generatePassword();
            $this->connection->createUser(
                $userName,
                $password,
                $options
            );
            $this->logger->info(sprintf(
                'Created user "%s" with password "%s"',
                $userName,
                $password
            ));
        } else {
            $this->connection->alterUser(
                $userName,
                $options
            );
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
            ->generateString(self::GENERATED_PASSWORD_LENGTH);
        return $password;
    }

    private function ensureMaximumDataRetention(): void
    {
        $query = sprintf(
            'SHOW DATABASES LIKE %s',
            $this->connection->quote($this->database)
        );
        $databaseInfo = $this->connection->fetchAll($query);
        $retentionTime = $databaseInfo[0]['retention_time'];
        if ($retentionTime > self::DATA_RETENTION_TIME_IN_DAYS) {
            $this->connection->query(sprintf(
                'ALTER DATABASE %s SET DATA_RETENTION_TIME_IN_DAYS = %s;',
                $this->connection->quoteIdentifier($this->database),
                self::DATA_RETENTION_TIME_IN_DAYS
            ));
        }
    }

    private function ensureUserResetPassword(string $userName): void
    {
        $password = $this->connection->resetUserPassword($userName);
        $this->logger->info(sprintf(
            'Reset password for user "%s" use "%s". ' .
            'Old password will keep working until you reset the password using provided link',
            $userName,
            $password
        ));
    }
}
