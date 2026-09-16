<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\DatadirTests;

use Keboola\DatadirTests\AbstractDatadirTestCase;
use Keboola\DatadirTests\Exception\DatadirTestsException;
use Keboola\SnowflakeDwhManager\Config;
use Keboola\SnowflakeDwhManager\ConfigDefinition;
use Keboola\SnowflakeDwhManager\Configuration\UserDefinition;
use Keboola\SnowflakeDwhManager\Connection;
use Keboola\SnowflakeDwhManager\Manager\NamingConventions;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class DatadirScenarioTest extends AbstractDatadirTestCase
{
    use DatadirTrait;

    private static LoggerInterface $logger;

    /**
     * @return array<string, array<mixed>>
     */
    private static function getSchema1Config(): array
    {
        return [
            'parameters' => [
                'master_host' => getenv('SNOWFLAKE_HOST'),
                'master_user' => getenv('SNOWFLAKE_USER'),
                '#master_private_key' => getenv('SNOWFLAKE_PRIVATE_KEY'),
                'master_database' => getenv('SNOWFLAKE_DATABASE'),
                'warehouse' => getenv('SNOWFLAKE_WAREHOUSE'),
                'business_schema' => [
                    'schema_name' => 'my_dwh_schema',
                    'public_key' => getenv('SNOWFLAKE_SCHEMA_PUBLIC_KEY'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<mixed>>
     */
    private static function getSchema2Config(): array
    {
        return [
            'parameters' => [
                'master_host' => getenv('SNOWFLAKE_HOST'),
                'master_user' => getenv('SNOWFLAKE_USER'),
                '#master_private_key' => getenv('SNOWFLAKE_PRIVATE_KEY'),
                'master_database' => getenv('SNOWFLAKE_DATABASE'),
                'warehouse' => getenv('SNOWFLAKE_WAREHOUSE'),
                'business_schema' => [
                    'schema_name' => 'my_dwh_schema2',
                    'public_key' => getenv('SNOWFLAKE_SCHEMA_PUBLIC_KEY'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<mixed>>
     */
    private static function getSchema3Config(): array
    {
        return [
            'parameters' => [
                'master_host' => getenv('SNOWFLAKE_HOST'),
                'master_user' => getenv('SNOWFLAKE_USER'),
                '#master_private_key' => getenv('SNOWFLAKE_PRIVATE_KEY'),
                'master_database' => getenv('SNOWFLAKE_DATABASE'),
                'warehouse' => getenv('SNOWFLAKE_WAREHOUSE'),
                'business_schema' => [
                    'schema_name' => 'my_dwh_schema3',
                    'public_key' => getenv('SNOWFLAKE_SCHEMA_PUBLIC_KEY'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<mixed>>
     */
    private static function getSchema4Config(): array
    {
        return [
            'parameters' => [
                'master_host' => getenv('SNOWFLAKE_HOST'),
                'master_user' => getenv('SNOWFLAKE_USER'),
                '#master_private_key' => getenv('SNOWFLAKE_PRIVATE_KEY'),
                'master_database' => getenv('SNOWFLAKE_DATABASE'),
                'warehouse' => getenv('SNOWFLAKE_WAREHOUSE'),
                'business_schema' => [
                    'schema_name' => 'my_dwh_schema4',
                    'public_key' => getenv('SNOWFLAKE_SCHEMA_PUBLIC_KEY'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<mixed>>
     */
    private static function getSchemaWithPublicKeyConfig(): array
    {
        return [
            'parameters' => [
                'master_host' => getenv('SNOWFLAKE_HOST'),
                'master_user' => getenv('SNOWFLAKE_USER'),
                '#master_private_key' => getenv('SNOWFLAKE_PRIVATE_KEY'),
                'master_database' => getenv('SNOWFLAKE_DATABASE'),
                'warehouse' => getenv('SNOWFLAKE_WAREHOUSE'),
                'business_schema' => [
                    'schema_name' => 'my_dwh_schema_5',
                    'public_key' => getenv('SNOWFLAKE_SCHEMA_PUBLIC_KEY'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<mixed>>
     */
    private static function getSchemaWithDifferentPublicKeyConfig(): array
    {
        return [
            'parameters' => [
                'master_host' => getenv('SNOWFLAKE_HOST'),
                'master_user' => getenv('SNOWFLAKE_USER'),
                '#master_private_key' => getenv('SNOWFLAKE_PRIVATE_KEY'),
                'master_database' => getenv('SNOWFLAKE_DATABASE'),
                'warehouse' => getenv('SNOWFLAKE_WAREHOUSE'),
                'business_schema' => [
                    'schema_name' => 'my_dwh_schema_5',
                    'public_key' => getenv('SNOWFLAKE_SCHEMA_PUBLIC_KEY_2'),
                ],
            ],
        ];
    }

    protected function getScript(): string
    {
        return $this->getTestFileDir() . '/../../src/run.php';
    }

    /**
     * @return array<string, array<mixed>>
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
            'create-user-user4' => [
                self::getUser4Config(),
            ],
            'create-user-user5-skip-password' => [
                self::getUser5ConfigSkipPassword(),
            ],
        ];
    }

    public function testCreateSchemaAsMasterUserWithPrivateKey(): void
    {
        $schema4config = $this->getConfigFromConfigArray(self::getSchema4Config());
        $connection = $this->getConnectionForConfig($schema4config);

        self::dropCreatedSchema($connection, $schema4config->getDatabase(), $schema4config->getSchema());

        $this->runAppWithConfig(self::getSchema4Config());

        $userName = implode('_', [$schema4config->getDatabase(), $schema4config->getSchema()->getName()]);

        /** @var array<int, array<string, string|int>> $users */
        $users = $connection->fetchAll('SHOW USERS LIKE \'%' . $userName . '%\' LIMIT 1');

        self::assertSame('SERVICE', $users[0]['type']);
        self::assertFalse($this->assertHasPassword($connection, $userName));
    }

    public function testCreateSchemaWithPrivateKeyUser(): void
    {
        $schema3config = $this->getConfigFromConfigArray(self::getSchema3Config());
        $connection = $this->getConnectionForConfig($schema3config);

        self::dropCreatedSchema($connection, $schema3config->getDatabase(), $schema3config->getSchema());

        $this->runAppWithConfig(self::getSchema3Config());

        $userName = implode('_', [$schema3config->getDatabase(), $schema3config->getSchema()->getName()]);

        /** @var array<int, array<string, string|int>> $users */
        $users = $connection->fetchAll('SHOW USERS LIKE \'%' . $userName . '%\' LIMIT 1');

        self::assertSame('SERVICE', $users[0]['type']);
        self::assertFalse($this->assertHasPassword($connection, $userName));
    }

    public function testChangeOfAlreadySetPublicKey(): void
    {
        $schemaConfig = $this->getConfigFromConfigArray(self::getSchemaWithPublicKeyConfig());
        $connection = $this->getConnectionForConfig($schemaConfig);
        $userName = implode('_', [$schemaConfig->getDatabase(), $schemaConfig->getSchema()->getName()]);

        self::dropCreatedSchema($connection, $schemaConfig->getDatabase(), $schemaConfig->getSchema());

        $this->runAppWithConfig(self::getSchemaWithPublicKeyConfig());

        $rsaPublicKey = $this->retrievePublicKey($connection, $userName);
        self::assertSame(getenv('SNOWFLAKE_SCHEMA_PUBLIC_KEY'), $rsaPublicKey);

        $this->runAppWithConfig(self::getSchemaWithDifferentPublicKeyConfig());

        $rsaPublicKey = $this->retrievePublicKey($connection, $userName);
        self::assertSame(getenv('SNOWFLAKE_SCHEMA_PUBLIC_KEY_2'), $rsaPublicKey);
        self::assertFalse($this->assertHasPassword($connection, $userName));
    }

    public function testCreateUserAsPersonType(): void
    {
        $user3config = $this->getConfigFromConfigArray(self::getUser3Config());
        $connection = $this->getConnectionForConfig($user3config);

        self::dropCreatedUser($connection, $user3config->getDatabase(), $user3config->getUser());

        $this->runAppWithConfig(self::getUser3Config());

        $userName = new NamingConventions($user3config->getDatabase())->getUsernameFromEmail($user3config->getUser());

        /** @var array<int, array<string, string|int>> $users */
        $users = $connection->fetchAll('SHOW USERS LIKE \'%' . $userName . '%\' LIMIT 1');

        self::assertSame('PERSON', $users[0]['type']);
        self::assertTrue($this->assertHasPassword($connection, $userName));
    }

    public function testCreateUserAsPersonTypeWithKeypair(): void
    {
        $user3config = $this->getConfigFromConfigArray(self::getUser3ConfigWithPublicKey());
        $connection = $this->getConnectionForConfig($user3config);

        self::dropCreatedUser($connection, $user3config->getDatabase(), $user3config->getUser());

        $this->runAppWithConfig(self::getUser3ConfigWithPublicKey());

        $userName = new NamingConventions($user3config->getDatabase())->getUsernameFromEmail($user3config->getUser());

        /** @var array<int, array<string, string|int>> $users */
        $users = $connection->fetchAll('SHOW USERS LIKE \'%' . $userName . '%\' LIMIT 1');

        self::assertSame('PERSON', $users[0]['type']);
        $rsaPublicKey = $this->retrievePublicKey($connection, $userName);
        self::assertSame(getenv('SNOWFLAKE_SCHEMA_PUBLIC_KEY_2'), $rsaPublicKey);
        self::assertTrue($this->assertHasPassword($connection, $userName));
    }

    public function testCreateUserAsPersonTypeWithKeypairAndSkipPassword(): void
    {
        $userConfig = $this->getConfigFromConfigArray(self::getUser5ConfigSkipPassword());
        $connection = $this->getConnectionForConfig($userConfig);

        self::dropCreatedUser($connection, $userConfig->getDatabase(), $userConfig->getUser());

        $this->runAppWithConfig(self::getUser5ConfigSkipPassword());

        $userName = new NamingConventions($userConfig->getDatabase())->getUsernameFromEmail($userConfig->getUser());

        /** @var array<int, array<string, string|int>> $users */
        $users = $connection->fetchAll('SHOW USERS LIKE \'%' . $userName . '%\' LIMIT 1');

        self::assertSame('PERSON', $users[0]['type']);
        $rsaPublicKey = $this->retrievePublicKey($connection, $userName);
        self::assertSame(getenv('SNOWFLAKE_SCHEMA_PUBLIC_KEY_2'), $rsaPublicKey);
        self::assertFalse($this->assertHasPassword($connection, $userName));

        // re-run to verify idempotency
        $this->runAppWithConfig(self::getUser5ConfigSkipPassword());

        /** @var array<int, array<string, string|int>> $users */
        $users = $connection->fetchAll('SHOW USERS LIKE \'%' . $userName . '%\' LIMIT 1');
        self::assertSame('PERSON', $users[0]['type']);
        self::assertSame(
            getenv('SNOWFLAKE_SCHEMA_PUBLIC_KEY_2'),
            $this->retrievePublicKey($connection, $userName),
        );
        self::assertFalse($this->assertHasPassword($connection, $userName));
    }

    public function testUnsetPasswordWhenSkipPasswordEnabledForExistingUser(): void
    {
        $userConfig = $this->getConfigFromConfigArray(self::getUser3Config());
        $connection = $this->getConnectionForConfig($userConfig);

        self::dropCreatedUser($connection, $userConfig->getDatabase(), $userConfig->getUser());

        // first run: create user with password (no skip_password)
        $this->runAppWithConfig(self::getUser3Config());

        $userName = new NamingConventions($userConfig->getDatabase())->getUsernameFromEmail($userConfig->getUser());
        self::assertTrue($this->assertHasPassword($connection, $userName));

        // second run: enable skip_password with public_key on the same user
        $this->runAppWithConfig(self::getUser3ConfigWithPublicKeyAndSkipPassword());

        /** @var array<int, array<string, string|int>> $users */
        $users = $connection->fetchAll('SHOW USERS LIKE \'%' . $userName . '%\' LIMIT 1');
        self::assertSame('PERSON', $users[0]['type']);
        self::assertSame(
            getenv('SNOWFLAKE_SCHEMA_PUBLIC_KEY_2'),
            $this->retrievePublicKey($connection, $userName),
        );
        self::assertFalse($this->assertHasPassword($connection, $userName));
    }

    /**
     * @return array<string, array<mixed>>
     */
    private static function getUser1Config(): array
    {
        return [
            'parameters' => [
                'master_host' => getenv('SNOWFLAKE_HOST'),
                'master_user' => getenv('SNOWFLAKE_USER'),
                '#master_private_key' => getenv('SNOWFLAKE_PRIVATE_KEY'),
                'master_database' => getenv('SNOWFLAKE_DATABASE'),
                'warehouse' => getenv('SNOWFLAKE_WAREHOUSE'),
                'user' => [
                    'email' => 'user1@keboola.com',
                    'business_schemas' => ['my_dwh_schema','my_dwh_schema2'],
                    'disabled' => false,
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<mixed>>
     */
    private static function getUser2Config(): array
    {
        return [
            'parameters' => [
                'master_host' => getenv('SNOWFLAKE_HOST'),
                'master_user' => getenv('SNOWFLAKE_USER'),
                '#master_private_key' => getenv('SNOWFLAKE_PRIVATE_KEY'),
                'master_database' => getenv('SNOWFLAKE_DATABASE'),
                'warehouse' => getenv('SNOWFLAKE_WAREHOUSE'),
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

    /**
     * @return array<string, array<mixed>>
     */
    private static function getUser3Config(): array
    {
        return [
            'parameters' => [
                'master_host' => getenv('SNOWFLAKE_HOST'),
                'master_user' => getenv('SNOWFLAKE_USER'),
                '#master_private_key' => getenv('SNOWFLAKE_PRIVATE_KEY'),
                'master_database' => getenv('SNOWFLAKE_DATABASE'),
                'warehouse' => getenv('SNOWFLAKE_WAREHOUSE'),
                'user' => [
                    'email' => 'user3@keboola.com',
                    'business_schemas' => ['my_dwh_schema3'],
                    'disabled' => false,
                ],
            ],
        ];
    }
    /**
     * @return array<string, array<mixed>>
     */
    private static function getUser3ConfigWithPublicKey(): array
    {
        return [
            'parameters' => [
                'master_host' => getenv('SNOWFLAKE_HOST'),
                'master_user' => getenv('SNOWFLAKE_USER'),
                '#master_private_key' => getenv('SNOWFLAKE_PRIVATE_KEY'),
                'master_database' => getenv('SNOWFLAKE_DATABASE'),
                'warehouse' => getenv('SNOWFLAKE_WAREHOUSE'),
                'user' => [
                    'email' => 'user3@keboola.com',
                    'business_schemas' => ['my_dwh_schema3'],
                    'disabled' => false,
                    'person_type' => true,
                    'public_key' => getenv('SNOWFLAKE_SCHEMA_PUBLIC_KEY_2'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<mixed>>
     */
    private static function getUser4Config(): array
    {
        return [
            'parameters' => [
                'master_host' => getenv('SNOWFLAKE_HOST'),
                'master_user' => getenv('SNOWFLAKE_USER'),
                '#master_private_key' => getenv('SNOWFLAKE_PRIVATE_KEY'),
                'master_database' => getenv('SNOWFLAKE_DATABASE'),
                'warehouse' => getenv('SNOWFLAKE_WAREHOUSE'),
                'user' => [
                    'email' => 'user4@keboola.com',
                    'business_schemas' => ['my_dwh_schema3'],
                    'disabled' => false,
                    'person_type' => true,
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<mixed>>
     */
    private static function getUser3ConfigWithPublicKeyAndSkipPassword(): array
    {
        return [
            'parameters' => [
                'master_host' => getenv('SNOWFLAKE_HOST'),
                'master_user' => getenv('SNOWFLAKE_USER'),
                '#master_private_key' => getenv('SNOWFLAKE_PRIVATE_KEY'),
                'master_database' => getenv('SNOWFLAKE_DATABASE'),
                'warehouse' => getenv('SNOWFLAKE_WAREHOUSE'),
                'user' => [
                    'email' => 'user3@keboola.com',
                    'business_schemas' => ['my_dwh_schema3'],
                    'disabled' => false,
                    'person_type' => true,
                    'public_key' => getenv('SNOWFLAKE_SCHEMA_PUBLIC_KEY_2'),
                    'skip_password' => true,
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<mixed>>
     */
    private static function getUser5ConfigSkipPassword(): array
    {
        return [
            'parameters' => [
                'master_host' => getenv('SNOWFLAKE_HOST'),
                'master_user' => getenv('SNOWFLAKE_USER'),
                '#master_private_key' => getenv('SNOWFLAKE_PRIVATE_KEY'),
                'master_database' => getenv('SNOWFLAKE_DATABASE'),
                'warehouse' => getenv('SNOWFLAKE_WAREHOUSE'),
                'user' => [
                    'email' => 'user5@keboola.com',
                    'business_schemas' => ['my_dwh_schema3'],
                    'disabled' => false,
                    'person_type' => true,
                    'public_key' => getenv('SNOWFLAKE_SCHEMA_PUBLIC_KEY_2'),
                    'skip_password' => true,
                ],
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    public function provideConfigs(): array
    {
        return self::getTestConfigs();
    }

    protected function runScript(string $datadirPath, ?string $runId = null): Process
    {
        $fs = new Filesystem();

        $script = $this->getScript();
        if (!$fs->exists($script)) {
            throw new DatadirTestsException(sprintf(
                'Cannot open script file "%s"',
                $script,
            ));
        }

        $runCommand = [
            'php',
            $script,
        ];
        $runProcess = new Process($runCommand);
        $runProcess->setEnv([
            'KBC_DATADIR' => $datadirPath,
            'KBC_RUNID' => 'dwhm_test_run_id',
        ]);
        $runProcess->setTimeout(0);
        $runProcess->run(function ($type, $buffer): void {
            if ($type === Process::ERR) {
                self::$logger->log(Logger::DEBUG, 'ERR > '.$buffer);
            } else {
                self::$logger->log(Logger::DEBUG, 'OUT > '.$buffer);
            }
        });
        return $runProcess;
    }

    /**
     * @param array<mixed> $config
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

    private function retrievePublicKey(Connection $connection, string $userName): string
    {
        /** @var array<int, array<string, string|int>> $users */
        $users = $connection->fetchAll('DESCRIBE USER ' . $userName);
        $filteredResult = array_filter(
            $users,
            /** @var array<string, int|string> $item */
            static fn (array $item): bool => $item['property'] === 'RSA_PUBLIC_KEY',
        );
        /** @var array<string, string> $rsaPublicKey */
        $rsaPublicKey = array_pop($filteredResult);

        return $rsaPublicKey['value'];
    }

    private function assertHasPassword(Connection $connection, string $userName): bool
    {
        /** @var array<int, array<string, string|int>> $users */
        $users = $connection->fetchAll('DESCRIBE USER ' . $userName);
        $filteredResult = array_filter(
            $users,
            /** @var array<string, int|string> $item */
            static fn (array $item): bool => $item['property'] === 'PASSWORD',
        );
        /** @var array<string, string> $value */
        $value = array_pop($filteredResult);
        echo 'password:';
        print_r($value);
        return $value['value'] !== 'null';
    }
}
