<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\Tests;

use Keboola\Component\UserException;
use Keboola\SnowflakeDwhManager\Config;
use Keboola\SnowflakeDwhManager\ConfigDefinition;
use Keboola\SnowflakeDwhManager\Configuration\Schema;
use Keboola\SnowflakeDwhManager\Configuration\User;
use PHPUnit\Framework\TestCase;
use Throwable;

class ConfigTest extends TestCase
{
    public function testGetUser(): void
    {
        $configData = [
            'parameters' => [
                'master_host' => 'host',
                'master_user' => 'user',
                '#master_password' => 'password',
                'master_database' => 'database',
                'warehouse' => 'warehouse',
                'user' => [
                    'email' => 'test@example.com',
                    'business_schemas' => [
                        'dwh1',
                        'dwh2',
                    ],
                ],
            ],
        ];
        $config = new Config($configData, new ConfigDefinition());

        $user = $config->getUser();

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('test@example.com', $user->getEmail());
        $this->assertFalse($user->isDisabled());
    }

    public function testGetSchema(): void
    {
        $configData = [
            'parameters' => [
                'master_host' => 'host',
                'master_user' => 'user',
                '#master_password' => 'password',
                'master_database' => 'database',
                'warehouse' => 'warehouse',
                'business_schema' => [
                    'schema_name' => 'dwh1',
                ],
            ],
        ];

        $config = new Config($configData, new ConfigDefinition());

        $schema = $config->getSchema();

        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertSame('dwh1', $schema->getName());
    }

    /**
     * @param class-string<Throwable> $exceptionClass
     * @param array<string, array<class-string|string|array<mixed>>> $rawConfig
     *
     * @dataProvider configsDataProvider
     */
    public function testConfigs(string $exceptionClass, string $exceptionMessage, array $rawConfig): void
    {
        self::expectException($exceptionClass);
        self::expectExceptionMessage($exceptionMessage);
        new Config($rawConfig, new ConfigDefinition());
    }

    /**
     * @return array<string, array<class-string|string|array<mixed>>>
     */
    public static function configsDataProvider(): array
    {
        return [
            'empty master_password & master_key_pair' => [
                UserException::class,
                'Either "password" or "keyPair" must be provided.',
                [
                    'parameters' => [
                        'master_host' => 'host',
                        'master_user' => 'user',
                        '#master_password' => '',
                        'master_database' => 'database',
                        'warehouse' => 'warehouse',
                        'user' => [
                            'email' => 'test@example.com',
                        ],
                    ],
                ],
            ],
        ];
    }
}
