<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\DatadirTests;

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

class DatadirScenarioTest extends AbstractDatadirTestCase
{
    /** @var LoggerInterface */
    private static $logger;

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
                [
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
                ],
            ],
            'create-schema-2' => [
                [
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
                ],
            ],
            'create-user-user1' => [
                [
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
                ],
            ],
            'create-user-user2' => [
                [
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
