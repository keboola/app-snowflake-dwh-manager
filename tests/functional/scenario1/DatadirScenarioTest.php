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
use Keboola\SnowflakeDwhManager\Connection;
use Keboola\SnowflakeDwhManager\Manager\NamingConventions;
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
        return new Connection(new NullLogger(), $config->getSnowflakeConnectionOptions());
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
                    'business_schemas' => ['my_dwh_schema'],
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
                    'business_schemas' => ['my_dwh_schema', 'my_dwh_schema2'],
                    'disabled' => false,
                ],
            ],
        ];
    }

    public function provideConfigs(): array
    {
        return self::getTestConfigs();
    }

    /**
     * @dataProvider provideConfigs
     */
    public function testDatadir(array $config): void
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
     * @depends testDatadir
     */
    // phpcs:disable SlevomatCodingStandard.TypeHints.TypeHintDeclaration.UselessDocComment
    public function testUsersHaveCorrectAccessAfterProvisioning(): void
    {
        // phpcs:enable
        $user1ConfigArray = self::getUser1Config();
        $user1config = $this->getConfigFromConfigArray($user1ConfigArray);

        $masterConnection = $this->getConnectionForConfig($user1config);

        // create table in read schema
        $readSchema = strtoupper($user1config->getUser()->getSchemas()[0]);
        $masterConnection->query('USE SCHEMA ' . $masterConnection->quoteIdentifier($readSchema));
        $masterConnection->query('DROP TABLE IF EXISTS read_schema_table');
        $masterConnection->query('CREATE TABLE read_schema_table (id INT)');
        $masterConnection->query('INSERT INTO read_schema_table VALUES (9), (8), (7)');
        // need to re-grant to make new table visible
        $readOnlyRole = $this->namingConventions->getRoRoleFromSchemaName($readSchema);
        $masterConnection->grantSelectOnAllTablesInSchemaToRole($readSchema, $readOnlyRole);

        // disconnect
        unset($masterConnection);

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

    public static function setUpBeforeClass(): void
    {
        self::setUpLogging();
        $testConfigs = self::getTestConfigs();

        $config = new Config(reset($testConfigs)[0], new ConfigDefinition());
        $connection = new Connection(new NullLogger(), $config->getSnowflakeConnectionOptions());

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
        $this->namingConventions = new NamingConventions(getenv('DATABASE'));
    }

    private static function setUpLogging(): void
    {
        $logger = new Logger('', [new StreamHandler('php://output')]);
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
