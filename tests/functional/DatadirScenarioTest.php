<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\DatadirTests;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
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
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Throwable;

class DatadirScenarioTest extends AbstractDatadirTestCase
{
    use DatadirTrait;

    private static LoggerInterface $logger;

    private NamingConventions $namingConventions;

    /**
     * @return array<string, array<mixed>>
     */
    private static function getSchema1Config(): array
    {
        return [
            'parameters' => [
                'master_host' => getenv('SNOWFLAKE_HOST'),
                'master_user' => getenv('SNOWFLAKE_USER'),
                '#master_password' => getenv('SNOWFLAKE_PASSWORD'),
                'master_database' => getenv('SNOWFLAKE_DATABASE'),
                'warehouse' => getenv('SNOWFLAKE_WAREHOUSE'),
                'business_schema' => [
                    'schema_name' => 'my_dwh_schema',
                    'reset_password' => true,
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
                '#master_password' => getenv('SNOWFLAKE_PASSWORD'),
                'master_database' => getenv('SNOWFLAKE_DATABASE'),
                'warehouse' => getenv('SNOWFLAKE_WAREHOUSE'),
                'business_schema' => [
                    'schema_name' => 'my_dwh_schema2',
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
                '#master_password' => getenv('SNOWFLAKE_PASSWORD'),
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
    private static function getSchemaWithoutPublicKeyConfig(): array
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
    }

    public function testSetPublicKeyForSchemaUser(): void
    {
        $schemaConfig = $this->getConfigFromConfigArray(self::getSchemaWithoutPublicKeyConfig());
        $connection = $this->getConnectionForConfig($schemaConfig);

        self::dropCreatedSchema($connection, $schemaConfig->getDatabase(), $schemaConfig->getSchema());

        $this->runAppWithConfig(self::getSchemaWithoutPublicKeyConfig());

        $userName = implode('_', [$schemaConfig->getDatabase(), $schemaConfig->getSchema()->getName()]);

        /** @var array<int, array<string, string|int>> $users */
        $users = $connection->fetchAll('SHOW USERS LIKE \'%' . $userName . '%\' LIMIT 1');

        self::assertSame('false', $users[0]['has_rsa_public_key']);

        $this->runAppWithConfig(self::getSchemaWithPublicKeyConfig());

        /** @var array<int, array<string, string|int>> $users */
        $users = $connection->fetchAll('SHOW USERS LIKE \'%' . $userName . '%\' LIMIT 1');

        self::assertSame('true', $users[0]['has_rsa_public_key']);
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
    }

    public function testChangeUserToPersonType(): void
    {
        $user4config = $this->getConfigFromConfigArray(self::getUser4Config());
        $connection = $this->getConnectionForConfig($user4config);

        $this->runAppWithConfig(self::getUser4Config());

        $userName = new NamingConventions($user4config->getDatabase())->getUsernameFromEmail($user4config->getUser());

        $connection->query(sprintf('ALTER USER %s SET TYPE=LEGACY_SERVICE', $userName));

        /** @var array<int, array<string, string|int>> $users */
        $users = $connection->fetchAll('SHOW USERS LIKE \'%' . $userName . '%\' LIMIT 1');
        self::assertSame('LEGACY_SERVICE', $users[0]['type']);

        $this->runAppWithConfig(self::getUser4Config());

        /** @var array<int, array<string, string|int>> $users */
        $users = $connection->fetchAll('SHOW USERS LIKE \'%' . $userName . '%\' LIMIT 1');
        self::assertSame('PERSON', $users[0]['type']);
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
                '#master_password' => getenv('SNOWFLAKE_PASSWORD'),
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
                '#master_password' => getenv('SNOWFLAKE_PASSWORD'),
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
                '#master_password' => getenv('SNOWFLAKE_PASSWORD'),
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
                '#master_password' => getenv('SNOWFLAKE_PASSWORD'),
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
                '#master_password' => getenv('SNOWFLAKE_PASSWORD'),
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
            $this->assertStringContainsString(
                'Object \'READ_SCHEMA_TABLE\' does not exist or not authorized',
                $e->getMessage(),
            );
        }

        unset($user1connection);

        $process = $this->runAppWithConfig(self::getSchema1Config());

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
            $this->assertStringContainsString(
                'Insufficient privileges to operate on schema \'MY_DWH_SCHEMA\'',
                $e->getMessage(),
            );
        }
        $this->assertStringContainsString('resetPasswordToken', $process->getOutput());

        $user2configArray = self::getUser2Config();
        $user2Config = $this->getConfigFromConfigArray($user2configArray);
        $user2Schema = $this->namingConventions->getOwnSchemaNameFromUser($user2Config->getUser());

        // cannot use other user's schema
        try {
            $user1connection->query('USE SCHEMA ' . $user1connection->quoteIdentifier($user2Schema));
            $this->fail('User must not be allowed to use other user\'s schema');
        } catch (Throwable $e) {
            $this->assertStringContainsString(
                'Cannot access object or it does not exist',
                $e->getMessage(),
            );
        }

        $user1ConfigArray = self::getUser1Config();
        $user1config = $this->getConfigFromConfigArray($user1ConfigArray);

        $masterConnection = $this->getConnectionForConfig($user1config);

        // Check query tags are present
        sleep(30);
        // to make sure query is propagated to history table
        $history = $masterConnection->fetchAll("
            select 
                QUERY_TEXT, QUERY_TAG, END_TIME 
            from 
                table(information_schema.query_history_by_user()) 
            WHERE 
                query_text='SELECT CURRENT_ROLE() AS \"name\"' 
            order by end_time DESC
            LIMIT 1;
        ");
        $this->assertSame(
            '{"runId":"dwhm_test_run_id"}',
            $history[0]['QUERY_TAG'],
        );
        $this->assertGreaterThan(
            (new DateTimeImmutable())->sub(new DateInterval('PT5M'))->setTimezone(new DateTimeZone('UTC')),
            (new DateTimeImmutable($history[0]['END_TIME']))->setTimezone(new DateTimeZone('UTC')),
        );
        unset($masterConnection);

        $user1ConfigArray['parameters']['user']['reset_password'] = true;

        $process = $this->runAppWithConfig($user1ConfigArray);

        $this->assertStringContainsString('resetPasswordToken', $process->getOutput());
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
            $this->assertStringContainsString(
                'Object \'READ_SCHEMA_TABLE\' does not exist or not authorized',
                $e->getMessage(),
            );
        }
        try {
            $user2connection->query('INSERT INTO write_schema_table VALUES (19)');
            $this->fail('User does not have write access to generated schema without re-running the schema config');
        } catch (Throwable $e) {
            $this->assertStringContainsString(
                'Table \'WRITE_SCHEMA_TABLE\' does not exist',
                $e->getMessage(),
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
            $this->assertStringContainsString(
                'Insufficient privileges to operate on schema \'MY_DWH_SCHEMA\'',
                $e->getMessage(),
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
            $this->assertStringContainsString(
                'Cannot access object or it does not exist',
                $e->getMessage(),
            );
        }
        unset($user2connection);

        // user can manipulate schema object created by other users
        $user1connection = $this->getConnectionForUserFromUserConfig($user1configArray);
        $readSchemaRole = $this->namingConventions->getRoRoleFromSchemaName($writeSchema);
        $user1connection->query('USE ROLE ' . $user1connection->quoteIdentifier($readSchemaRole));
        $user1connection->query('USE SCHEMA ' . $user1connection->quoteIdentifier($writeSchema));
        $user2TableRows = $user1connection->fetchAll('SELECT * FROM user2_table_in_write_schema');
        $this->assertCount(2, $user2TableRows);
    }

    /**
     * @depends testDatadir
     */
    // phpcs:disable SlevomatCodingStandard.TypeHints.TypeHintDeclaration.UselessDocComment
    public function testUserStatementTimeout(): void
    {
        // phpcs:enable
        $userConfigArray = self::getUser1Config();
        $userConfigArray['parameters']['user']['statement_timeout'] = 2;
        $this->runAppWithConfig($userConfigArray);

        $userConnection = $this->getConnectionForUserFromUserConfig($userConfigArray);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Query reached its timeout 2 second(s)" while executing query "call system$wait(10);',
        );
        $userConnection->query('call system$wait(10);');
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
        $database = (string) getenv('SNOWFLAKE_DATABASE');
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
}
