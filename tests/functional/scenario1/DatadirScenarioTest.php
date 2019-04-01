<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\DatadirTests;

use Exception;
use Keboola\DatadirTests\AbstractDatadirTestCase;
use Keboola\DatadirTests\DatadirTestSpecification;
use Keboola\SnowflakeDwhManager\Config;
use Keboola\SnowflakeDwhManager\ConfigDefinition;
use Keboola\SnowflakeDwhManager\Configuration\Schema;
use Keboola\SnowflakeDwhManager\Configuration\User;
use Keboola\SnowflakeDwhManager\Configuration\UserDefinition;
use Keboola\SnowflakeDwhManager\Connection;
use Keboola\SnowflakeDwhManager\Manager\NamingConventions;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RandomLib\Factory;
use Throwable;

class DatadirScenarioTest extends AbstractDatadirTestCase
{
    /** @var LoggerInterface */
    private static $logger;

    /** @var NamingConventions */
    private $namingConventions;

    /**
     * @param array $userConfig
     * @return Config
     */
    private function getConfigFromConfigArray(array $userConfig): Config
    {
        return new Config($userConfig, new ConfigDefinition());
    }

    private function getConnectionForConfig(Config $config): Connection
    {
        return new Connection($config->getSnowflakeConnectionOptions());
    }

    /**
     * @param mixed[] $userConfig
     */
    private function getConnectionForUserFromUserConfig(array $userConfig): Connection
    {
        $config = $this->getConfigFromConfigArray($userConfig);
        if (!$config->isUserRow()) {
            throw new Exception('This is not a user config');
        }

        // get master connection to change the password
        $connection = $this->getConnectionForConfig($config);

        // change the password to known one
        $user1Username = $this->namingConventions->getUsernameFromEmail($config->getUser());
        $randomLibFactory = new Factory();
        $userNewPassword = $randomLibFactory
            ->getMediumStrengthGenerator()
            ->generateString(30);
        $connection->alterUser($user1Username, ['password' => $userNewPassword]);

        // alter the original config to use new user and password
        $loginAsUser1Config = $userConfig;
        $loginAsUser1Config['parameters']['master_user'] = $user1Username;
        $loginAsUser1Config['parameters']['#master_password'] = $userNewPassword;

        // force destructor to disconnect
        unset($connection);

        // get connection as the user
        $config = new Config($loginAsUser1Config, new ConfigDefinition());
        return $this->getConnectionForConfig($config);
    }

    /**
     * @return array
     */
    private static function getSchema1Config(): array
    {
        return [
            'parameters' => [
                'master_host' => getenv('HOST'),
                'master_user' => getenv('USER'),
                '#master_password' => getenv('PASSWORD'),
                'master_database' => getenv('DATABASE'),
                'warehouse' => getenv('WAREHOUSE'),
                'business_schema' => [
                    'schema_name' => 'my_dwh_schema',
                ],
            ],
        ];
    }

    /**
     * @return array
     */
    private static function getSchema2Config(): array
    {
        return [
            'parameters' => [
                'master_host' => getenv('HOST'),
                'master_user' => getenv('USER'),
                '#master_password' => getenv('PASSWORD'),
                'master_database' => getenv('DATABASE'),
                'warehouse' => getenv('WAREHOUSE'),
                'business_schema' => [
                    'schema_name' => 'my_dwh_schema2',
                ],
            ],
        ];
    }

    protected function getScript(): string
    {
        return $this->getTestFileDir() . '/../../../src/run.php';
    }

    /**
     * @return array
     */
    private static function getTestConfigs(): array
    {
        return [
            'create-schema-1' => [
                self::getSchema1Config(),
            ],
            'create-schema-2' => [
                self::getSchema2Config(),
            ],
            'create-user-user1' => [
                self::getUser1Config(),
            ],
            'create-user-user2' => [
                self::getUser2Config(),
            ],
        ];
    }

    /**
     * @return array
     */
    private static function getUser1Config(): array
    {
        return [
            'parameters' => [
                'master_host' => getenv('HOST'),
                'master_user' => getenv('USER'),
                '#master_password' => getenv('PASSWORD'),
                'master_database' => getenv('DATABASE'),
                'warehouse' => getenv('WAREHOUSE'),
                'user' => [
                    'email' => 'user1@keboola.com',
                    'business_schemas' => ['my_dwh_schema','my_dwh_schema2'],
                    'disabled' => false,
                ],
            ],
        ];
    }

    /**
     * @return array
     */
    private static function getUser2Config(): array
    {
        return [
            'parameters' => [
                'master_host' => getenv('HOST'),
                'master_user' => getenv('USER'),
                '#master_password' => getenv('PASSWORD'),
                'master_database' => getenv('DATABASE'),
                'warehouse' => getenv('WAREHOUSE'),
                'user' => [
                    'email' => 'user2@keboola.com',
                    'business_schemas' => ['my_dwh_schema'],
                    'schemas' => [
                        ['name' => 'my_dwh_schema2', 'permission' => UserDefinition::PERMISSION_WRITE],
                    ],
                    'disabled' => false,
                ],
            ],
        ];
    }

    public function provideConfigs(): array
    {
        return self::getTestConfigs();
    }

    private function runAppWithConfig(array $config): void
    {
        self::$logger->log(Logger::DEBUG, $this->getDataSetAsString());

        $specification = new DatadirTestSpecification(null, 0, null, null, null);

        $tempDatadir = $this->getTempDatadir($specification);

        file_put_contents($tempDatadir->getTmpFolder() . '/config.json', json_encode($config));

        $process = $this->runScript($tempDatadir->getTmpFolder());

        $this->assertMatchesSpecification($specification, $process, $tempDatadir->getTmpFolder());

        self::$logger->log(Logger::DEBUG, $process->getOutput() . \PHP_EOL . $process->getErrorOutput());
    }

    /**
     * @dataProvider provideConfigs
     */
    public function testDatadir(array $config): void
    {
        $this->runAppWithConfig($config);
    }

    /**
     * @depends testDatadir
     */
    // phpcs:disable SlevomatCodingStandard.TypeHints.TypeHintDeclaration.UselessDocComment
    public function testUser1HaveCorrectAccessAfterProvisioning(): void
    {
        // phpcs:enable
        $user1ConfigArray = self::getUser1Config();
        $user1config = $this->getConfigFromConfigArray($user1ConfigArray);

        $masterConnection = $this->getConnectionForConfig($user1config);

        // create table in read schema
        $readSchema = strtoupper($user1config->getUser()->getReadOnlySchemas()[0]);
        $masterConnection->query('USE SCHEMA ' . $masterConnection->quoteIdentifier($readSchema));
        $masterConnection->query('DROP TABLE IF EXISTS read_schema_table');
        $masterConnection->query('CREATE TABLE read_schema_table (id INT)');
        $masterConnection->query('INSERT INTO read_schema_table VALUES (9), (8), (7)');

        unset($masterConnection);

        $user1connection = $this->getConnectionForUserFromUserConfig($user1ConfigArray);
        try {
            $user1connection->fetchAll('SELECT * FROM read_schema_table');
            $this->fail('User does not have access to generated schema without re-running the schema config');
        } catch (Throwable $e) {
            $this->assertContains(
                'Object \'READ_SCHEMA_TABLE\' does not exist.',
                $e->getMessage()
            );
        }
        unset($user1connection);

        $this->runAppWithConfig(self::getSchema1Config());

        $user1connection = $this->getConnectionForUserFromUserConfig($user1ConfigArray);
        $userRwSchema = $this->namingConventions->getOwnSchemaNameFromUser($user1config->getUser());
        $user1connection->query('USE SCHEMA ' . $user1connection->quoteIdentifier($userRwSchema));
        $user1connection->query('DROP TABLE IF EXISTS user1table');

        // can create table in their schema
        $user1connection->query('CREATE TABLE user1table (id INT)');

        // can insert row into created table
        $user1connection->query('INSERT INTO user1table VALUES (1), (10)');

        // can fetch from created table
        $rows = $user1connection->fetchAll('SELECT * FROM user1table');
        $this->assertCount(2, $rows);

        // can read from read only schema
        $user1connection->query('USE SCHEMA ' . $user1connection->quoteIdentifier($readSchema));
        $rows = $user1connection->fetchAll('SELECT * FROM read_schema_table');
        $this->assertCount(3, $rows);

        // cannot write to read only schema
        try {
            $user1connection->query('CREATE TABLE table_in_read_schema (id INT)');
            $this->fail('User must not be allowed to create a table in shared read only schema');
        } catch (Throwable $e) {
            $this->assertContains(
                'Insufficient privileges to operate on schema \'MY_DWH_SCHEMA\'',
                $e->getMessage()
            );
        }

        $user2configArray = self::getUser2Config();
        $user2Config = $this->getConfigFromConfigArray($user2configArray);
        $user2Schema = $this->namingConventions->getOwnSchemaNameFromUser($user2Config->getUser());

        // cannot use other user's schema
        try {
            $user1connection->query('USE SCHEMA ' . $user1connection->quoteIdentifier($user2Schema));
            $this->fail('User must not be allowed to use other user\'s schema');
        } catch (Throwable $e) {
            $this->assertContains(
                "Object does not exist, or operation cannot be performed.",
                $e->getMessage()
            );
        }
    }

    /**
     * @depends testDatadir
     */
    // phpcs:disable SlevomatCodingStandard.TypeHints.TypeHintDeclaration.UselessDocComment
    public function testUser2HaveCorrectAccessAfterProvisioning(): void
    {
        // phpcs:enable
        $user2ConfigArray = self::getUser2Config();
        $user2config = $this->getConfigFromConfigArray($user2ConfigArray);

        $masterConnection = $this->getConnectionForConfig($user2config);

        // create table in read schema
        $readSchema = strtoupper($user2config->getUser()->getReadOnlySchemas()[0]);
        $masterConnection->query('USE SCHEMA ' . $masterConnection->quoteIdentifier($readSchema));
        $masterConnection->query('DROP TABLE IF EXISTS read_schema_table');
        $masterConnection->query('CREATE TABLE read_schema_table (id INT)');
        $masterConnection->query('INSERT INTO read_schema_table VALUES (9), (8), (7)');

        // create table in read write schema
        $writeSchemas = $user2config->getUser()->getWriteSchemas();
        $writeSchema = strtoupper($writeSchemas[0]);
        $masterConnection->query('USE SCHEMA ' . $masterConnection->quoteIdentifier($writeSchema));
        $masterConnection->query('DROP TABLE IF EXISTS write_schema_table');
        $masterConnection->query('CREATE TABLE write_schema_table (id INT)');
        $masterConnection->query('INSERT INTO write_schema_table VALUES (9), (8), (7), (6)');

        // disconnect
        unset($masterConnection);

        $user2connection = $this->getConnectionForUserFromUserConfig($user2ConfigArray);
        try {
            $user2connection->fetchAll('SELECT * FROM read_schema_table');
            $this->fail('User does not have access to generated schema without re-running the schema config');
        } catch (Throwable $e) {
            $this->assertContains(
                'Object \'READ_SCHEMA_TABLE\' does not exist., SQL state 02000 in SQLPrepare',
                $e->getMessage()
            );
        }
        try {
            $user2connection->query('INSERT INTO write_schema_table VALUES (19)');
            $this->fail('User does not have write access to generated schema without re-running the schema config');
        } catch (Throwable $e) {
            $this->assertContains(
                'Table \'WRITE_SCHEMA_TABLE\' does not exist., SQL state 02000 in SQLPrepare',
                $e->getMessage()
            );
        }

        // rerun for read only schema
        $this->runAppWithConfig(self::getSchema1Config());
        // rerun for read/write schema
        $this->runAppWithConfig(self::getSchema2Config());

        $userRwSchema = $this->namingConventions->getOwnSchemaNameFromUser($user2config->getUser());
        $user2connection->query('USE SCHEMA ' . $user2connection->quoteIdentifier($userRwSchema));
        $user2connection->query('DROP TABLE IF EXISTS user2table');

        // can create table in their schema
        $user2connection->query('CREATE TABLE user2table (id INT)');

        // can insert row into created table
        $user2connection->query('INSERT INTO user2table VALUES (1), (10)');

        // can fetch from created table
        $rows = $user2connection->fetchAll('SELECT * FROM user2table');
        $this->assertCount(2, $rows);

        // can read from read only schema
        $user2connection->query('USE SCHEMA ' . $user2connection->quoteIdentifier($readSchema));
        $rows = $user2connection->fetchAll('SELECT * FROM read_schema_table');
        $this->assertCount(3, $rows);

        // cannot write to read only schema
        try {
            $user2connection->query('CREATE TABLE table_in_read_schema (id INT)');
            $this->fail('User must not be allowed to create a table in shared read only schema');
        } catch (Throwable $e) {
            $this->assertContains(
                'Insufficient privileges to operate on schema \'MY_DWH_SCHEMA\'',
                $e->getMessage()
            );
        }

        // can read from write schema
        $user2connection->query('USE SCHEMA ' . $user2connection->quoteIdentifier($writeSchema));
        $rows = $user2connection->fetchAll('SELECT * FROM write_schema_table');
        $this->assertCount(4, $rows);

        // can write to write schema
        $user2connection->query('DROP TABLE IF EXISTS user2_table_in_write_schema');
        $user2connection->query('CREATE TABLE user2_table_in_write_schema (id INT)');
        $user2connection->query('INSERT INTO user2_table_in_write_schema VALUES (1), (10)');

        // can write into existing table
        $rows = $user2connection->fetchAll('SELECT * FROM write_schema_table');
        $this->assertCount(4, $rows);
        $user2connection->query('INSERT INTO write_schema_table VALUES (5)');
        $rows = $user2connection->fetchAll('SELECT * FROM write_schema_table');
        $this->assertCount(5, $rows);

        $user1configArray = self::getUser1Config();
        $user1Config = $this->getConfigFromConfigArray($user1configArray);
        $user1Schema = $this->namingConventions->getOwnSchemaNameFromUser($user1Config->getUser());

        // cannot use other user's schema
        try {
            $user2connection->query('USE SCHEMA ' . $user2connection->quoteIdentifier($user1Schema));
            $this->fail('User must not be allowed to use other user\'s schema');
        } catch (Throwable $e) {
            $this->assertContains(
                "Object does not exist, or operation cannot be performed.",
                $e->getMessage()
            );
        }
        unset($user2connection);

        // user can not manipulate schema object created by other users before regranting
        try {
            $user1connection = $this->getConnectionForUserFromUserConfig($user1configArray);
            $user1connection->query('USE SCHEMA ' . $user1connection->quoteIdentifier($writeSchema));
            $user1connection->fetchAll('SELECT * FROM user2_table_in_write_schema');
            $this->fail('User cannot use other user\'s table before regranting');
        } catch (Throwable $e) {
            $this->assertContains(
                "Object 'USER2_TABLE_IN_WRITE_SCHEMA' does not exist.",
                $e->getMessage()
            );
        }

        // regrant
        $this->runAppWithConfig(self::getSchema2Config());

        // user can manipulate schema object created by other users after regranting
        $user1connection = $this->getConnectionForUserFromUserConfig($user1configArray);
        $readSchemaRole = $this->namingConventions->getRoRoleFromSchemaName($writeSchema);
        $user1connection->query('USE ROLE ' . $user1connection->quoteIdentifier($readSchemaRole));
        $user1connection->query('USE SCHEMA ' . $user1connection->quoteIdentifier($writeSchema));
        $user2TableRows = $user1connection->fetchAll('SELECT * FROM user2_table_in_write_schema');
        $this->assertCount(2, $user2TableRows);
    }

    public static function setUpBeforeClass(): void
    {
        self::setUpLogging();
        $testConfigs = self::getTestConfigs();

        $config = new Config(reset($testConfigs)[0], new ConfigDefinition());
        $connection = new Connection($config->getSnowflakeConnectionOptions());

        foreach ($testConfigs as $config) {
            $config = new Config($config[0], new ConfigDefinition());
            if ($config->isSchemaRow()) {
                self::dropCreatedSchema($connection, $config->getDatabase(), $config->getSchema());
            } elseif ($config->isUserRow()) {
                self::dropCreatedUser($connection, $config->getDatabase(), $config->getUser());
            }
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $database = (string) getenv('DATABASE');
        $this->namingConventions = new NamingConventions($database);
    }

    private static function setUpLogging(): void
    {
        $handler = new StreamHandler('php://output');
        $handler->setFormatter(new LineFormatter(null, null, true, true));
        $logger = new Logger('', [$handler]);
        if (getenv('CI')) {
            // to prevent login leaking on Travis
            $logger = new NullLogger();
        }
        self::$logger = $logger;
    }

    private static function dropCreatedSchema(Connection $connection, string $prefix, Schema $schema): void
    {
        $namingConventions = new NamingConventions($prefix);
        $connection->query(
            'DROP SCHEMA IF EXISTS '
            . $connection->quoteIdentifier($namingConventions->getSchemaNameFromSchema($schema))
        );
        $connection->query(
            'DROP ROLE IF EXISTS '
            . $connection->quoteIdentifier($namingConventions->getRwRoleFromSchema($schema))
        );
        $connection->query(
            'DROP ROLE IF EXISTS '
            . $connection->quoteIdentifier($namingConventions->getRoRoleFromSchema($schema))
        );
        $connection->query(
            'DROP USER IF EXISTS '
            . $connection->quoteIdentifier($namingConventions->getRwUserFromSchema($schema))
        );
        self::$logger->log(Logger::DEBUG, sprintf('Dropped schema "%s"' . \PHP_EOL, $schema->getName()));
    }

    private static function dropCreatedUser(Connection $connection, string $prefix, User $user): void
    {
        $namingConventions = new NamingConventions($prefix);

        $connection->query(
            'DROP SCHEMA IF EXISTS '
            . $connection->quoteIdentifier($namingConventions->getOwnSchemaNameFromUser($user))
        );
        $connection->query(
            'DROP ROLE IF EXISTS '
            . $connection->quoteIdentifier($namingConventions->getRoleNameFromUser($user))
        );
        $connection->query(
            'DROP USER IF EXISTS '
            . $connection->quoteIdentifier($namingConventions->getUsernameFromEmail($user))
        );
        self::$logger->log(Logger::DEBUG, sprintf('Dropped user "%s"' . \PHP_EOL, $user->getEmail()));
    }
}
